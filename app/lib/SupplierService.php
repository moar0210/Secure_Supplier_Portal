<?php

declare(strict_types=1);

final class SupplierService
{
    private const EMPTY_UUID = 'EMPTY';

    private PDO $pdo;
    private Crypto $crypto;

    public function __construct(PDO $pdo, Crypto $crypto)
    {
        $this->pdo = $pdo;
        $this->crypto = $crypto;
    }

    public function listSuppliers(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $stmt = $this->pdo->query("
            SELECT id_supplier, supplier_name, short_name, email, homepage, is_inactive
            FROM suppliers
            ORDER BY id_supplier ASC
            LIMIT {$limit}
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row = $this->decryptFields($row, [
                'email' => SupplierProfileEncryptionMap::SUPPLIERS['email'],
            ]);
        }
        unset($row);

        return $rows;
    }

    public function getProfile(int $supplierId): ?array
    {
        $supplier = $this->getSupplierRow($supplierId);
        if ($supplier === null) {
            return null;
        }
        $supplier = $this->decryptFields($supplier, SupplierProfileEncryptionMap::SUPPLIERS);

        $address = $this->loadAddress($supplier['uuid_address_main'] ?? '');
        $contact = $this->loadContact($supplier['uuid_person_contact'] ?? '');
        $phone = $this->loadMainPhone((string)$supplier['uuid_supplier']);

        return [
            'id_supplier' => (int)$supplier['id_supplier'],
            'uuid_supplier' => (string)$supplier['uuid_supplier'],
            'company_name' => (string)$supplier['supplier_name'],
            'short_name' => (string)$supplier['short_name'],
            'contact_person' => $this->contactDisplayName($contact),
            'email' => (string)$supplier['email'],
            'homepage' => (string)$supplier['homepage'],
            'vat_number' => (string)$supplier['unique_id'],
            'address_line_1' => (string)($address['description'] ?? ''),
            'address_line_2' => (string)($address['complement'] ?? ''),
            'city' => (string)($address['city'] ?? ''),
            'region' => (string)($address['region'] ?? ''),
            'postal_code' => (string)($address['postal_code'] ?? ''),
            'country_code' => (string)(($address['country_code_ISO2'] ?? '') !== '' ? $address['country_code_ISO2'] : $supplier['country_code_ISO2']),
            'phone_country_prefix' => $phone === null ? '' : $this->normalizePhoneCodeForForm($phone['country_prefix'] ?? null),
            'phone_area_code' => $phone === null ? '' : $this->normalizePhoneCodeForForm($phone['area_code'] ?? null),
            'phone_number' => $phone === null ? '' : (string)$phone['phone_number'],
            'phone_display' => $phone === null ? '' : $this->formatPhoneDisplay($phone),
        ];
    }

    public function updateProfile(int $supplierId, array $input): void
    {
        $supplier = $this->getSupplierRow($supplierId);
        if ($supplier === null) {
            throw new UserFacingException('Supplier not found.');
        }

        $clean = $this->validateProfileData($input, $supplier);
        $this->pdo->beginTransaction();

        try {
            $addressUuid = $this->upsertAddress($supplier, $clean);
            $contactUuid = $this->upsertContactPerson($supplier, $clean, $addressUuid);
            $this->upsertPhone((string)$supplier['uuid_supplier'], $clean);

            $stmt = $this->pdo->prepare("
                UPDATE suppliers
                SET supplier_name = :supplier_name,
                    short_name = :short_name,
                    email = :email,
                    homepage = :homepage,
                    unique_id = :unique_id,
                    country_code_ISO2 = :country_code,
                    uuid_address_main = :uuid_address_main,
                    uuid_person_contact = :uuid_person_contact,
                    time_updated = NOW()
                WHERE id_supplier = :id
            ");
            $stmt->execute([
                ':supplier_name' => $clean['company_name'],
                ':short_name' => $clean['short_name'],
                ':email' => (string)$this->crypto->encryptNullable($clean['email'], SupplierProfileEncryptionMap::SUPPLIERS['email']),
                ':homepage' => $clean['homepage'],
                ':unique_id' => (string)$this->crypto->encryptNullable($clean['vat_number'], SupplierProfileEncryptionMap::SUPPLIERS['unique_id']),
                ':country_code' => $clean['country_code'],
                ':uuid_address_main' => $addressUuid,
                ':uuid_person_contact' => $contactUuid,
                ':id' => $supplierId,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function getSupplierRow(int $supplierId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id_supplier, uuid_supplier, uuid_address_main, uuid_person_contact, supplier_name, short_name, email, homepage, unique_id, country_code_ISO2
            FROM suppliers
            WHERE id_supplier = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $supplierId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function validateProfileData(array $input, array $supplier): array
    {
        $companyName = trim((string)($input['company_name'] ?? ''));
        $shortName = trim((string)($input['short_name'] ?? ''));
        $contactPerson = trim((string)($input['contact_person'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $homepage = trim((string)($input['homepage'] ?? ''));
        $vatNumber = trim((string)($input['vat_number'] ?? ''));
        $addressLine1 = trim((string)($input['address_line_1'] ?? ''));
        $addressLine2 = trim((string)($input['address_line_2'] ?? ''));
        $city = trim((string)($input['city'] ?? ''));
        $region = trim((string)($input['region'] ?? ''));
        $postalCode = trim((string)($input['postal_code'] ?? ''));
        $countryCode = strtoupper(trim((string)($input['country_code'] ?? (string)($supplier['country_code_ISO2'] ?? ''))));
        $phoneCountryPrefix = $this->digitsOnly((string)($input['phone_country_prefix'] ?? ''));
        $phoneAreaCode = $this->digitsOnly((string)($input['phone_area_code'] ?? ''));
        $phoneNumber = $this->digitsOnly((string)($input['phone_number'] ?? ''));

        if ($companyName === '' || mb_strlen($companyName) > 100) {
            throw new UserFacingException('Company name is required and must be 100 characters or fewer.');
        }

        if ($shortName === '') {
            $shortName = $companyName;
        }
        if (mb_strlen($shortName) > 100) {
            throw new UserFacingException('Short name must be 100 characters or fewer.');
        }

        if ($contactPerson === '' || mb_strlen($contactPerson) > 100) {
            throw new UserFacingException('Contact person is required and must be 100 characters or fewer.');
        }

        if ($email === '' || mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new UserFacingException('A valid email address is required.');
        }

        if ($homepage !== '' && (mb_strlen($homepage) > 100 || filter_var($homepage, FILTER_VALIDATE_URL) === false)) {
            throw new UserFacingException('Homepage must be a valid URL and 100 characters or fewer.');
        }

        if ($addressLine1 === '' || mb_strlen($addressLine1) > 100) {
            throw new UserFacingException('Address line 1 is required and must be 100 characters or fewer.');
        }

        if (mb_strlen($addressLine2) > 50) {
            throw new UserFacingException('Address line 2 must be 50 characters or fewer.');
        }

        if ($city === '' || mb_strlen($city) > 30) {
            throw new UserFacingException('City is required and must be 30 characters or fewer.');
        }

        if (mb_strlen($region) > 30) {
            throw new UserFacingException('Region must be 30 characters or fewer.');
        }

        if ($postalCode !== '' && mb_strlen($postalCode) > 10) {
            throw new UserFacingException('Postal code must be 10 characters or fewer.');
        }

        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new UserFacingException('Country code must be a valid ISO-2 code such as SE or US.');
        }

        if ($vatNumber !== '' && mb_strlen($vatNumber) > 50) {
            throw new UserFacingException('VAT / tax number must be 50 characters or fewer.');
        }

        if (($phoneCountryPrefix !== '' || $phoneAreaCode !== '' || $phoneNumber !== '') && $phoneNumber === '') {
            throw new UserFacingException('Phone number is required when phone details are provided.');
        }

        if (mb_strlen($phoneCountryPrefix) > 4) {
            throw new UserFacingException('Phone country prefix must be 4 digits or fewer.');
        }

        if (mb_strlen($phoneAreaCode) > 6) {
            throw new UserFacingException('Phone area code must be 6 digits or fewer.');
        }

        if (mb_strlen($phoneNumber) > 20) {
            throw new UserFacingException('Phone number must be 20 digits or fewer.');
        }

        [$contactFirstName, $contactLastName, $contactFullName, $abbreviation] = $this->splitContactName($contactPerson);

        return [
            'company_name' => $companyName,
            'short_name' => $shortName,
            'contact_person' => $contactPerson,
            'contact_first_name' => $contactFirstName,
            'contact_last_name' => $contactLastName,
            'contact_full_name' => $contactFullName,
            'contact_abbreviation' => $abbreviation,
            'email' => $email,
            'homepage' => $homepage,
            'vat_number' => $vatNumber,
            'address_line_1' => $addressLine1,
            'address_line_2' => $addressLine2,
            'city' => $city,
            'region' => $region,
            'postal_code' => $postalCode,
            'country_code' => $countryCode,
            'phone_country_prefix' => $this->normalizePhoneCodeForStorage($phoneCountryPrefix),
            'phone_area_code' => $this->normalizePhoneCodeForStorage($phoneAreaCode),
            'phone_number' => $phoneNumber,
        ];
    }

    private function upsertAddress(array $supplier, array $clean): string
    {
        $uuid = $this->normalizeUuid($supplier['uuid_address_main'] ?? '');
        $existing = $uuid === null ? null : $this->loadAddress($uuid);

        $addressWebsite = mb_substr($clean['homepage'], 0, 60);

        if ($existing === null) {
            $uuid = $this->newUuid();

            $stmt = $this->pdo->prepare("
                INSERT INTO addresses (
                    uuid_address,
                    country_code_ISO2,
                    city,
                    description,
                    complement,
                    region,
                    postal_code,
                    website,
                    time_updated,
                    source_info
                ) VALUES (
                    :uuid_address,
                    :country_code,
                    :city,
                    :description,
                    :complement,
                    :region,
                    :postal_code,
                    :website,
                    NOW(),
                    'PORTAL'
                )
            ");
            $stmt->execute([
                ':uuid_address' => $uuid,
                ':country_code' => $clean['country_code'],
                ':city' => (string)$this->crypto->encryptNullable($clean['city'], SupplierProfileEncryptionMap::ADDRESSES['city']),
                ':description' => (string)$this->crypto->encryptNullable($clean['address_line_1'], SupplierProfileEncryptionMap::ADDRESSES['description']),
                ':complement' => (string)$this->crypto->encryptNullable($clean['address_line_2'], SupplierProfileEncryptionMap::ADDRESSES['complement']),
                ':region' => (string)$this->crypto->encryptNullable($clean['region'], SupplierProfileEncryptionMap::ADDRESSES['region']),
                ':postal_code' => (string)$this->crypto->encryptNullable($clean['postal_code'], SupplierProfileEncryptionMap::ADDRESSES['postal_code']),
                ':website' => $addressWebsite,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE addresses
                SET country_code_ISO2 = :country_code,
                    city = :city,
                    description = :description,
                    complement = :complement,
                    region = :region,
                    postal_code = :postal_code,
                    website = :website,
                    time_updated = NOW()
                WHERE uuid_address = :uuid_address
            ");
            $stmt->execute([
                ':uuid_address' => $uuid,
                ':country_code' => $clean['country_code'],
                ':city' => (string)$this->crypto->encryptNullable($clean['city'], SupplierProfileEncryptionMap::ADDRESSES['city']),
                ':description' => (string)$this->crypto->encryptNullable($clean['address_line_1'], SupplierProfileEncryptionMap::ADDRESSES['description']),
                ':complement' => (string)$this->crypto->encryptNullable($clean['address_line_2'], SupplierProfileEncryptionMap::ADDRESSES['complement']),
                ':region' => (string)$this->crypto->encryptNullable($clean['region'], SupplierProfileEncryptionMap::ADDRESSES['region']),
                ':postal_code' => (string)$this->crypto->encryptNullable($clean['postal_code'], SupplierProfileEncryptionMap::ADDRESSES['postal_code']),
                ':website' => $addressWebsite,
            ]);
        }

        $this->ensureAddressLink((string)$supplier['uuid_supplier'], $uuid);

        return $uuid;
    }

    private function upsertContactPerson(array $supplier, array $clean, string $addressUuid): string
    {
        $uuid = $this->normalizeUuid($supplier['uuid_person_contact'] ?? '');
        $existing = $uuid === null ? null : $this->loadContact($uuid);

        if ($existing === null) {
            $uuid = $this->newUuid();

            $stmt = $this->pdo->prepare("
                INSERT INTO persons (
                    uuid_entity,
                    abbreviation,
                    country_code_ISO2,
                    uuid_address_main,
                    first_name,
                    last_name,
                    full_name,
                    gender,
                    email_address,
                    time_updated,
                    source_info
                ) VALUES (
                    :uuid_entity,
                    :abbreviation,
                    :country_code,
                    :uuid_address_main,
                    :first_name,
                    :last_name,
                    :full_name,
                    'NO_INFO',
                    :email_address,
                    NOW(),
                    'PORTAL'
                )
            ");
            $stmt->execute([
                ':uuid_entity' => $uuid,
                ':abbreviation' => $clean['contact_abbreviation'],
                ':country_code' => $clean['country_code'],
                ':uuid_address_main' => $addressUuid,
                ':first_name' => (string)$this->crypto->encryptNullable($clean['contact_first_name'], SupplierProfileEncryptionMap::PERSONS['first_name']),
                ':last_name' => (string)$this->crypto->encryptNullable($clean['contact_last_name'], SupplierProfileEncryptionMap::PERSONS['last_name']),
                ':full_name' => (string)$this->crypto->encryptNullable($clean['contact_full_name'], SupplierProfileEncryptionMap::PERSONS['full_name']),
                ':email_address' => (string)$this->crypto->encryptNullable($clean['email'], SupplierProfileEncryptionMap::PERSONS['email_address']),
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE persons
                SET abbreviation = :abbreviation,
                    country_code_ISO2 = :country_code,
                    uuid_address_main = :uuid_address_main,
                    first_name = :first_name,
                    last_name = :last_name,
                    full_name = :full_name,
                    email_address = :email_address,
                    time_updated = NOW()
                WHERE uuid_entity = :uuid_entity
            ");
            $stmt->execute([
                ':uuid_entity' => $uuid,
                ':abbreviation' => $clean['contact_abbreviation'],
                ':country_code' => $clean['country_code'],
                ':uuid_address_main' => $addressUuid,
                ':first_name' => (string)$this->crypto->encryptNullable($clean['contact_first_name'], SupplierProfileEncryptionMap::PERSONS['first_name']),
                ':last_name' => (string)$this->crypto->encryptNullable($clean['contact_last_name'], SupplierProfileEncryptionMap::PERSONS['last_name']),
                ':full_name' => (string)$this->crypto->encryptNullable($clean['contact_full_name'], SupplierProfileEncryptionMap::PERSONS['full_name']),
                ':email_address' => (string)$this->crypto->encryptNullable($clean['email'], SupplierProfileEncryptionMap::PERSONS['email_address']),
            ]);
        }

        return $uuid;
    }

    private function upsertPhone(string $supplierUuid, array $clean): void
    {
        $existing = $this->loadMainPhone($supplierUuid);

        if ($clean['phone_number'] === '') {
            if ($existing !== null) {
                $stmt = $this->pdo->prepare('DELETE FROM phones_entities WHERE id_phone_entity = :id');
                $stmt->execute([':id' => (int)$existing['id_phone_entity']]);
            }
            return;
        }

        if ($existing === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO phones_entities (
                    entity_name,
                    uuid_entity,
                    country_prefix,
                    area_code,
                    phone_number,
                    phone_type,
                    display_order,
                    is_main,
                    time_updated,
                    source_info
                ) VALUES (
                    'SUPPLIER',
                    :uuid_entity,
                    :country_prefix,
                    :area_code,
                    :phone_number,
                    'MAIN',
                    0,
                    1,
                    NOW(),
                    'PORTAL'
                )
            ");
            $stmt->execute([
                ':uuid_entity' => $supplierUuid,
                ':country_prefix' => $this->crypto->encryptNullable($clean['phone_country_prefix'], SupplierProfileEncryptionMap::PHONES_ENTITIES['country_prefix']),
                ':area_code' => $this->crypto->encryptNullable($clean['phone_area_code'], SupplierProfileEncryptionMap::PHONES_ENTITIES['area_code']),
                ':phone_number' => (string)$this->crypto->encryptNullable($clean['phone_number'], SupplierProfileEncryptionMap::PHONES_ENTITIES['phone_number']),
            ]);
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE phones_entities
            SET country_prefix = :country_prefix,
                area_code = :area_code,
                phone_number = :phone_number,
                phone_type = 'MAIN',
                is_main = 1,
                display_order = 0,
                time_updated = NOW()
            WHERE id_phone_entity = :id
        ");
        $stmt->execute([
            ':country_prefix' => $this->crypto->encryptNullable($clean['phone_country_prefix'], SupplierProfileEncryptionMap::PHONES_ENTITIES['country_prefix']),
            ':area_code' => $this->crypto->encryptNullable($clean['phone_area_code'], SupplierProfileEncryptionMap::PHONES_ENTITIES['area_code']),
            ':phone_number' => (string)$this->crypto->encryptNullable($clean['phone_number'], SupplierProfileEncryptionMap::PHONES_ENTITIES['phone_number']),
            ':id' => (int)$existing['id_phone_entity'],
        ]);
    }

    private function loadAddress(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '' || strtoupper($uuid) === self::EMPTY_UUID) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT uuid_address, city, description, complement, region, postal_code, country_code_ISO2, website
            FROM addresses
            WHERE uuid_address = :uuid
            LIMIT 1
        ");
        $stmt->execute([':uuid' => $uuid]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->decryptFields($row, SupplierProfileEncryptionMap::ADDRESSES) : null;
    }

    private function loadContact(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '' || strtoupper($uuid) === self::EMPTY_UUID) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT uuid_entity, first_name, last_name, full_name, email_address, uuid_address_main
            FROM persons
            WHERE uuid_entity = :uuid
            LIMIT 1
        ");
        $stmt->execute([':uuid' => $uuid]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->decryptFields($row, SupplierProfileEncryptionMap::PERSONS) : null;
    }

    private function loadMainPhone(string $supplierUuid): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id_phone_entity, country_prefix, area_code, phone_number
            FROM phones_entities
            WHERE entity_name = 'SUPPLIER'
              AND uuid_entity = :uuid_entity
            ORDER BY is_main DESC, display_order ASC, id_phone_entity ASC
            LIMIT 1
        ");
        $stmt->execute([':uuid_entity' => $supplierUuid]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->decryptFields($row, SupplierProfileEncryptionMap::PHONES_ENTITIES) : null;
    }

    private function ensureAddressLink(string $supplierUuid, string $addressUuid): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id_address_entity
            FROM addresses_entities
            WHERE entity_name = 'SUPPLIER'
              AND uuid_entity = :uuid_entity
              AND uuid_address = :uuid_address
            LIMIT 1
        ");
        $stmt->execute([
            ':uuid_entity' => $supplierUuid,
            ':uuid_address' => $addressUuid,
        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO addresses_entities (
                uuid_address,
                entity_name,
                uuid_entity,
                address_type,
                time_updated,
                source_info
            ) VALUES (
                :uuid_address,
                'SUPPLIER',
                :uuid_entity,
                'MAIN',
                NOW(),
                'PORTAL'
            )
        ");
        $stmt->execute([
            ':uuid_address' => $addressUuid,
            ':uuid_entity' => $supplierUuid,
        ]);
    }

    private function newUuid(): string
    {
        return (string)$this->pdo->query('SELECT UUID()')->fetchColumn();
    }

    private function normalizeUuid(string $uuid): ?string
    {
        $uuid = trim($uuid);
        if ($uuid === '' || strtoupper($uuid) === self::EMPTY_UUID) {
            return null;
        }

        return $uuid;
    }

    private function contactDisplayName(?array $contact): string
    {
        if ($contact === null) {
            return '';
        }

        $fullName = trim((string)($contact['full_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        return trim(((string)($contact['first_name'] ?? '')) . ' ' . ((string)($contact['last_name'] ?? '')));
    }

    private function splitContactName(string $contactPerson): array
    {
        $parts = preg_split('/\s+/', trim($contactPerson)) ?: [];
        $firstName = $parts[0] ?? '';
        $lastName = count($parts) > 1 ? trim(implode(' ', array_slice($parts, 1))) : '';
        $fullName = trim($contactPerson);

        $abbrParts = array_filter([$firstName, $lastName], static fn(string $part): bool => $part !== '');
        $abbreviation = '';
        foreach ($abbrParts as $part) {
            $abbreviation .= strtoupper(substr($part, 0, 1));
        }
        if ($abbreviation === '') {
            $abbreviation = 'CP';
        }

        return [$firstName, $lastName, $fullName, substr($abbreviation, 0, 20)];
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function normalizePhoneCodeForStorage(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function normalizePhoneCodeForForm(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string)$value);

        return $value === '' || $value === '0' ? '' : $value;
    }

    private function formatPhoneDisplay(array $phone): string
    {
        $phoneNumber = trim((string)($phone['phone_number'] ?? ''));
        if ($phoneNumber === '') {
            return '';
        }

        $parts = [];

        $countryPrefix = $this->normalizePhoneCodeForForm($phone['country_prefix'] ?? null);
        if ($countryPrefix !== '') {
            $parts[] = '+' . $countryPrefix;
        }

        $areaCode = $this->normalizePhoneCodeForForm($phone['area_code'] ?? null);
        if ($areaCode !== '') {
            $parts[] = '(' . $areaCode . ')';
        }

        $parts[] = $phoneNumber;

        return implode(' ', $parts);
    }

    private function decryptFields(array $row, array $fieldMap): array
    {
        foreach ($fieldMap as $column => $aad) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $value = $row[$column];
            $row[$column] = $value === null
                ? null
                : $this->crypto->decryptNullable((string)$value, $aad);
        }

        return $row;
    }
}
