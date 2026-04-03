<?php

declare(strict_types=1);

final class SupplierService
{
    private const EMPTY_UUID = 'EMPTY';
    private const MAX_LOGO_BYTES = 2097152;
    /** @var array<string, string> */
    private const LOGO_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private PDO $pdo;
    private Crypto $crypto;
    private ?bool $logoTableAvailable = null;

    public function __construct(PDO $pdo, Crypto $crypto)
    {
        $this->pdo = $pdo;
        $this->crypto = $crypto;
    }

    public function listSuppliers(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $stmt = $this->pdo->prepare("
            SELECT id_supplier, supplier_name, short_name, email, homepage, is_inactive
            FROM suppliers
            ORDER BY id_supplier ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

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

    public function updateProfile(
        int $supplierId,
        array $input,
        ?array $logoUpload = null,
        bool $removeLogo = false,
        ?int $actorUserId = null
    ): void
    {
        $supplier = $this->getSupplierRow($supplierId);
        if ($supplier === null) {
            throw new UserFacingException('Supplier not found.');
        }

        $clean = $this->validateProfileData($input, $supplier);
        $hasLogoUpload = is_array($logoUpload)
            && (int)($logoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if (($hasLogoUpload || $removeLogo) && ($actorUserId === null || $actorUserId < 1)) {
            throw new RuntimeException('A valid actor user id is required for logo updates.');
        }

        if (($hasLogoUpload || $removeLogo) && !$this->logoTableAvailable()) {
            throw new UserFacingException('Logo storage is not ready yet. Apply database migration 007_supplier_logos.sql first.');
        }

        $pendingLogo = $hasLogoUpload ? $this->prepareLogoUpload($logoUpload) : null;
        $removeLogo = $removeLogo && $pendingLogo === null;
        $previousLogo = null;

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

            if ($pendingLogo !== null || $removeLogo) {
                $previousLogo = $this->fetchLogoRow($supplierId, true);

                if ($pendingLogo !== null) {
                    $this->upsertLogoRow($supplierId, $pendingLogo, (int)$actorUserId);
                } elseif ($removeLogo) {
                    $this->deleteLogoRow($supplierId);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($pendingLogo !== null) {
                $this->deleteLogoFileByName((string)$pendingLogo['stored_filename']);
            }

            throw $e;
        }

        if (($pendingLogo !== null || $removeLogo) && is_array($previousLogo)) {
            $this->deleteLogoFileByName((string)$previousLogo['stored_filename']);
        }
    }

    public function getLogoMeta(int $supplierId): ?array
    {
        if (!$this->logoTableAvailable()) {
            return null;
        }

        return $this->fetchLogoRow($supplierId, false);
    }

    public function getLogoAsset(int $supplierId): ?array
    {
        $logo = $this->getLogoMeta($supplierId);
        if ($logo === null) {
            return null;
        }

        $path = $this->logoPath((string)$logo['stored_filename']);
        if (!is_file($path)) {
            return null;
        }

        $extension = pathinfo((string)$logo['stored_filename'], PATHINFO_EXTENSION);
        $logo['path'] = $path;
        $logo['download_name'] = 'supplier-logo-' . $supplierId . ($extension === '' ? '' : '.' . $extension);

        return $logo;
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
        $supplierUuid = (string)$supplier['uuid_supplier'];
        $currentAddressUuid = $this->normalizeUuid($supplier['uuid_address_main'] ?? '');
        $currentContactUuid = $this->normalizeUuid($supplier['uuid_person_contact'] ?? '');

        if ($currentAddressUuid === null) {
            $addressUuid = $this->newUuid();
            $this->insertAddressRow($addressUuid, $clean);
        } else {
            $sourceInfo = $this->loadAddressSourceInfo($currentAddressUuid);
            if ($sourceInfo === null) {
                $addressUuid = $this->newUuid();
                $this->insertAddressRow($addressUuid, $clean);
            } else {
                $addressUuid = $this->shouldCloneAddress($currentAddressUuid, $supplierUuid, $currentContactUuid, $sourceInfo)
                    ? $this->cloneAddressRow($currentAddressUuid)
                    : $currentAddressUuid;
                $this->updateAddressRow($addressUuid, $clean);
            }
        }

        $this->ensureAddressLink($supplierUuid, $addressUuid);

        return $addressUuid;
    }

    private function upsertContactPerson(array $supplier, array $clean, string $addressUuid): string
    {
        $supplierUuid = (string)$supplier['uuid_supplier'];
        $currentContactUuid = $this->normalizeUuid($supplier['uuid_person_contact'] ?? '');

        if ($currentContactUuid === null) {
            $contactUuid = $this->newUuid();
            $this->insertContactRow($contactUuid, $clean, $addressUuid);
            return $contactUuid;
        }

        $sourceInfo = $this->loadContactSourceInfo($currentContactUuid);
        if ($sourceInfo === null) {
            $contactUuid = $this->newUuid();
            $this->insertContactRow($contactUuid, $clean, $addressUuid);
            return $contactUuid;
        }

        $contactUuid = $this->shouldCloneContact($currentContactUuid, $supplierUuid, $sourceInfo)
            ? $this->cloneContactRow($currentContactUuid)
            : $currentContactUuid;

        $this->updateContactRow($contactUuid, $clean, $addressUuid);

        return $contactUuid;
    }

    private function upsertPhone(string $supplierUuid, array $clean): void
    {
        $rows = $this->loadSupplierPhoneRows($supplierUuid);

        if ($clean['phone_number'] === '') {
            $this->deleteSupplierPhoneRows($supplierUuid);
            return;
        }

        if ($rows === []) {
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

        $canonicalId = (int)$rows[0]['id_phone_entity'];
        $this->deleteSupplierPhoneRows($supplierUuid, $canonicalId);

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
            ':id' => $canonicalId,
        ]);
    }

    private function insertAddressRow(string $addressUuid, array $clean): void
    {
        $addressWebsite = mb_substr($clean['homepage'], 0, 60);

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
            ':uuid_address' => $addressUuid,
            ':country_code' => $clean['country_code'],
            ':city' => (string)$this->crypto->encryptNullable($clean['city'], SupplierProfileEncryptionMap::ADDRESSES['city']),
            ':description' => (string)$this->crypto->encryptNullable($clean['address_line_1'], SupplierProfileEncryptionMap::ADDRESSES['description']),
            ':complement' => (string)$this->crypto->encryptNullable($clean['address_line_2'], SupplierProfileEncryptionMap::ADDRESSES['complement']),
            ':region' => (string)$this->crypto->encryptNullable($clean['region'], SupplierProfileEncryptionMap::ADDRESSES['region']),
            ':postal_code' => (string)$this->crypto->encryptNullable($clean['postal_code'], SupplierProfileEncryptionMap::ADDRESSES['postal_code']),
            ':website' => $addressWebsite,
        ]);
    }

    private function updateAddressRow(string $addressUuid, array $clean): void
    {
        $addressWebsite = mb_substr($clean['homepage'], 0, 60);

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
            ':uuid_address' => $addressUuid,
            ':country_code' => $clean['country_code'],
            ':city' => (string)$this->crypto->encryptNullable($clean['city'], SupplierProfileEncryptionMap::ADDRESSES['city']),
            ':description' => (string)$this->crypto->encryptNullable($clean['address_line_1'], SupplierProfileEncryptionMap::ADDRESSES['description']),
            ':complement' => (string)$this->crypto->encryptNullable($clean['address_line_2'], SupplierProfileEncryptionMap::ADDRESSES['complement']),
            ':region' => (string)$this->crypto->encryptNullable($clean['region'], SupplierProfileEncryptionMap::ADDRESSES['region']),
            ':postal_code' => (string)$this->crypto->encryptNullable($clean['postal_code'], SupplierProfileEncryptionMap::ADDRESSES['postal_code']),
            ':website' => $addressWebsite,
        ]);
    }

    private function cloneAddressRow(string $addressUuid): string
    {
        $newUuid = $this->newUuid();

        $stmt = $this->pdo->prepare("
            INSERT INTO addresses (
                uuid_address,
                LanguageId,
                country_code_ISO2,
                ID_TimeZone,
                city,
                care_of,
                description,
                complement,
                district,
                region,
                postal_code,
                access_information,
                website,
                geo_latitude,
                geo_longitude,
                uuid_address_connected,
                uuid_reporter,
                source_info,
                digital_document,
                uuid_recorder,
                IDC_role_recorder,
                time_updated,
                additional_notes
            )
            SELECT
                :new_uuid,
                LanguageId,
                country_code_ISO2,
                ID_TimeZone,
                city,
                care_of,
                description,
                complement,
                district,
                region,
                postal_code,
                access_information,
                website,
                geo_latitude,
                geo_longitude,
                uuid_address_connected,
                uuid_reporter,
                'PORTAL',
                digital_document,
                uuid_recorder,
                IDC_role_recorder,
                NOW(),
                additional_notes
            FROM addresses
            WHERE uuid_address = :old_uuid
            LIMIT 1
        ");
        $stmt->execute([
            ':new_uuid' => $newUuid,
            ':old_uuid' => $addressUuid,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Unable to clone the supplier address row.');
        }

        return $newUuid;
    }

    private function shouldCloneAddress(string $addressUuid, string $supplierUuid, ?string $currentContactUuid, ?string $sourceInfo = null): bool
    {
        $sourceInfo = $sourceInfo ?? $this->loadAddressSourceInfo($addressUuid);
        if ($sourceInfo === null) {
            return false;
        }

        if (!$this->isPortalOwned($sourceInfo)) {
            return true;
        }

        if ($currentContactUuid !== null && $this->shouldCloneContact($currentContactUuid, $supplierUuid)) {
            return true;
        }

        return $this->addressHasExternalReferences($addressUuid, $supplierUuid, $currentContactUuid);
    }

    private function addressHasExternalReferences(string $addressUuid, string $supplierUuid, ?string $currentContactUuid): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM suppliers
            WHERE uuid_address_main = :uuid
              AND uuid_supplier <> :supplier_uuid
            LIMIT 1
        ");
        $stmt->execute([
            ':uuid' => $addressUuid,
            ':supplier_uuid' => $supplierUuid,
        ]);
        if ($stmt->fetchColumn()) {
            return true;
        }

        if ($currentContactUuid === null) {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM persons
                WHERE uuid_address_main = :uuid
                LIMIT 1
            ");
            $stmt->execute([':uuid' => $addressUuid]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM persons
                WHERE uuid_address_main = :uuid
                  AND uuid_entity <> :contact_uuid
                LIMIT 1
            ");
            $stmt->execute([
                ':uuid' => $addressUuid,
                ':contact_uuid' => $currentContactUuid,
            ]);
        }
        if ($stmt->fetchColumn()) {
            return true;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM addresses_entities
            WHERE uuid_address = :uuid
              AND (entity_name <> 'SUPPLIER' OR uuid_entity <> :supplier_uuid)
            LIMIT 1
        ");
        $stmt->execute([
            ':uuid' => $addressUuid,
            ':supplier_uuid' => $supplierUuid,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    private function loadAddressSourceInfo(string $addressUuid): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT source_info
            FROM addresses
            WHERE uuid_address = :uuid
            LIMIT 1
        ");
        $stmt->execute([':uuid' => $addressUuid]);

        $sourceInfo = $stmt->fetchColumn();

        return $sourceInfo === false ? null : (string)$sourceInfo;
    }

    private function insertContactRow(string $contactUuid, array $clean, string $addressUuid): void
    {
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
            ':uuid_entity' => $contactUuid,
            ':abbreviation' => $clean['contact_abbreviation'],
            ':country_code' => $clean['country_code'],
            ':uuid_address_main' => $addressUuid,
            ':first_name' => (string)$this->crypto->encryptNullable($clean['contact_first_name'], SupplierProfileEncryptionMap::PERSONS['first_name']),
            ':last_name' => (string)$this->crypto->encryptNullable($clean['contact_last_name'], SupplierProfileEncryptionMap::PERSONS['last_name']),
            ':full_name' => (string)$this->crypto->encryptNullable($clean['contact_full_name'], SupplierProfileEncryptionMap::PERSONS['full_name']),
            ':email_address' => (string)$this->crypto->encryptNullable($clean['email'], SupplierProfileEncryptionMap::PERSONS['email_address']),
        ]);
    }

    private function updateContactRow(string $contactUuid, array $clean, string $addressUuid): void
    {
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
            ':uuid_entity' => $contactUuid,
            ':abbreviation' => $clean['contact_abbreviation'],
            ':country_code' => $clean['country_code'],
            ':uuid_address_main' => $addressUuid,
            ':first_name' => (string)$this->crypto->encryptNullable($clean['contact_first_name'], SupplierProfileEncryptionMap::PERSONS['first_name']),
            ':last_name' => (string)$this->crypto->encryptNullable($clean['contact_last_name'], SupplierProfileEncryptionMap::PERSONS['last_name']),
            ':full_name' => (string)$this->crypto->encryptNullable($clean['contact_full_name'], SupplierProfileEncryptionMap::PERSONS['full_name']),
            ':email_address' => (string)$this->crypto->encryptNullable($clean['email'], SupplierProfileEncryptionMap::PERSONS['email_address']),
        ]);
    }

    private function cloneContactRow(string $contactUuid): string
    {
        $newUuid = $this->newUuid();

        $stmt = $this->pdo->prepare("
            INSERT INTO persons (
                uuid_entity,
                abbreviation,
                username_App,
                country_code_ISO2,
                contact_preferred_time_of_the_day,
                uuid_address_main,
                first_name,
                last_name,
                full_name,
                Title,
                Suffix,
                country_code_ISO2_birth,
                multiple_birth,
                date_birth,
                time_birth,
                country_code_ISO2_death,
                Date_death,
                Time_death,
                gender,
                occupation,
                type_meal,
                nationality,
                SMS_Reminders,
                EMAIL_Reminders,
                system_language_1st,
                system_language_2nd,
                picture,
                blood_type,
                blood_type_variant,
                main_meal,
                marital_status,
                email_address,
                email_address_additional,
                additional_notes,
                date_created,
                How_did_you_find_us,
                How_did_you_find_us_complement,
                is_organ_donor,
                is_active,
                is_special_needs,
                geo_location_tracking_approved,
                visible_identification_marks,
                eye_color,
                idc_ethnic_group,
                IDC_Religion,
                IDC_Education,
                social_economic_class,
                authorize_share_of_data_for_research,
                suggested_days_between_appointments,
                uuid_reporter,
                source_info,
                digital_document,
                uuid_recorder,
                IDC_role_recorder,
                time_updated
            )
            SELECT
                :new_uuid,
                abbreviation,
                username_App,
                country_code_ISO2,
                contact_preferred_time_of_the_day,
                uuid_address_main,
                first_name,
                last_name,
                full_name,
                Title,
                Suffix,
                country_code_ISO2_birth,
                multiple_birth,
                date_birth,
                time_birth,
                country_code_ISO2_death,
                Date_death,
                Time_death,
                gender,
                occupation,
                type_meal,
                nationality,
                SMS_Reminders,
                EMAIL_Reminders,
                system_language_1st,
                system_language_2nd,
                picture,
                blood_type,
                blood_type_variant,
                main_meal,
                marital_status,
                email_address,
                email_address_additional,
                additional_notes,
                date_created,
                How_did_you_find_us,
                How_did_you_find_us_complement,
                is_organ_donor,
                is_active,
                is_special_needs,
                geo_location_tracking_approved,
                visible_identification_marks,
                eye_color,
                idc_ethnic_group,
                IDC_Religion,
                IDC_Education,
                social_economic_class,
                authorize_share_of_data_for_research,
                suggested_days_between_appointments,
                uuid_reporter,
                'PORTAL',
                digital_document,
                uuid_recorder,
                IDC_role_recorder,
                NOW()
            FROM persons
            WHERE uuid_entity = :old_uuid
            LIMIT 1
        ");
        $stmt->execute([
            ':new_uuid' => $newUuid,
            ':old_uuid' => $contactUuid,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Unable to clone the supplier contact row.');
        }

        return $newUuid;
    }

    private function shouldCloneContact(string $contactUuid, string $supplierUuid, ?string $sourceInfo = null): bool
    {
        $sourceInfo = $sourceInfo ?? $this->loadContactSourceInfo($contactUuid);
        if ($sourceInfo === null) {
            return false;
        }

        if (!$this->isPortalOwned($sourceInfo)) {
            return true;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM suppliers
            WHERE uuid_person_contact = :uuid
              AND uuid_supplier <> :supplier_uuid
            LIMIT 1
        ");
        $stmt->execute([
            ':uuid' => $contactUuid,
            ':supplier_uuid' => $supplierUuid,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    private function loadContactSourceInfo(string $contactUuid): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT source_info
            FROM persons
            WHERE uuid_entity = :uuid
            LIMIT 1
        ");
        $stmt->execute([':uuid' => $contactUuid]);

        $sourceInfo = $stmt->fetchColumn();

        return $sourceInfo === false ? null : (string)$sourceInfo;
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
        $rows = $this->loadSupplierPhoneRows($supplierUuid);
        $row = $rows[0] ?? null;

        return $row ? $this->decryptFields($row, SupplierProfileEncryptionMap::PHONES_ENTITIES) : null;
    }

    private function loadSupplierPhoneRows(string $supplierUuid): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id_phone_entity, country_prefix, area_code, phone_number
            FROM phones_entities
            WHERE entity_name = 'SUPPLIER'
              AND uuid_entity = :uuid_entity
            ORDER BY is_main DESC, display_order ASC, id_phone_entity ASC
        ");
        $stmt->execute([':uuid_entity' => $supplierUuid]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function deleteSupplierPhoneRows(string $supplierUuid, ?int $keepId = null): void
    {
        if ($keepId === null) {
            $stmt = $this->pdo->prepare("
                DELETE FROM phones_entities
                WHERE entity_name = 'SUPPLIER'
                  AND uuid_entity = :uuid_entity
            ");
            $stmt->execute([':uuid_entity' => $supplierUuid]);
            return;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM phones_entities
            WHERE entity_name = 'SUPPLIER'
              AND uuid_entity = :uuid_entity
              AND id_phone_entity <> :keep_id
        ");
        $stmt->execute([
            ':uuid_entity' => $supplierUuid,
            ':keep_id' => $keepId,
        ]);
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

    private function fetchLogoRow(int $supplierId, bool $forUpdate): ?array
    {
        $sql = "
            SELECT
                supplier_id,
                stored_filename,
                original_filename,
                mime_type,
                file_size,
                sha256,
                uploaded_by_user_id,
                uploaded_at,
                updated_at
            FROM supplier_logos
            WHERE supplier_id = :supplier_id
            LIMIT 1
        ";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':supplier_id' => $supplierId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function logoTableAvailable(): bool
    {
        if ($this->logoTableAvailable !== null) {
            return $this->logoTableAvailable;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => 'supplier_logos']);

        $this->logoTableAvailable = (bool)$stmt->fetchColumn();

        return $this->logoTableAvailable;
    }

    private function upsertLogoRow(int $supplierId, array $logo, int $actorUserId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO supplier_logos (
                supplier_id,
                stored_filename,
                original_filename,
                mime_type,
                file_size,
                sha256,
                uploaded_by_user_id
            ) VALUES (
                :supplier_id,
                :stored_filename,
                :original_filename,
                :mime_type,
                :file_size,
                :sha256,
                :uploaded_by_user_id
            )
            ON DUPLICATE KEY UPDATE
                stored_filename = VALUES(stored_filename),
                original_filename = VALUES(original_filename),
                mime_type = VALUES(mime_type),
                file_size = VALUES(file_size),
                sha256 = VALUES(sha256),
                uploaded_by_user_id = VALUES(uploaded_by_user_id),
                uploaded_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':supplier_id' => $supplierId,
            ':stored_filename' => (string)$logo['stored_filename'],
            ':original_filename' => (string)$logo['original_filename'],
            ':mime_type' => (string)$logo['mime_type'],
            ':file_size' => (int)$logo['file_size'],
            ':sha256' => (string)$logo['sha256'],
            ':uploaded_by_user_id' => $actorUserId,
        ]);
    }

    private function deleteLogoRow(int $supplierId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM supplier_logos WHERE supplier_id = :supplier_id');
        $stmt->execute([':supplier_id' => $supplierId]);
    }

    private function prepareLogoUpload(?array $logoUpload): ?array
    {
        if (!is_array($logoUpload)) {
            return null;
        }

        $error = (int)($logoUpload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new UserFacingException($this->logoUploadErrorMessage($error));
        }

        $tmpName = trim((string)($logoUpload['tmp_name'] ?? ''));
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new UserFacingException('The uploaded logo file could not be verified.');
        }

        $size = (int)($logoUpload['size'] ?? 0);
        if ($size < 1) {
            throw new UserFacingException('The uploaded logo file is empty.');
        }

        if ($size > self::MAX_LOGO_BYTES) {
            throw new UserFacingException('Logo files must be 2 MB or smaller.');
        }

        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            throw new UserFacingException('Logo upload must be a valid image file.');
        }

        $mimeType = (string)($imageInfo['mime'] ?? '');
        $extension = self::LOGO_MIME_TYPES[$mimeType] ?? null;
        if ($extension === null) {
            throw new UserFacingException('Logo upload must be a PNG, JPG, or WebP image.');
        }

        $sha256 = hash_file('sha256', $tmpName);
        if ($sha256 === false) {
            throw new RuntimeException('Unable to hash the uploaded logo file.');
        }

        $storedFilename = bin2hex(random_bytes(24)) . '.' . $extension;
        $storageDir = $this->logoStorageDir();
        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            throw new RuntimeException('Unable to create the supplier logo storage directory.');
        }

        $targetPath = $this->logoPath($storedFilename);
        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Unable to store the uploaded logo file.');
        }

        return [
            'stored_filename' => $storedFilename,
            'original_filename' => $this->sanitizeLogoFilename((string)($logoUpload['name'] ?? ''), $extension),
            'mime_type' => $mimeType,
            'file_size' => (int)(filesize($targetPath) ?: $size),
            'sha256' => $sha256,
        ];
    }

    private function logoUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Logo files must be 2 MB or smaller.',
            UPLOAD_ERR_PARTIAL => 'The logo upload was interrupted. Please try again.',
            default => 'Unable to process the uploaded logo file.',
        };
    }

    private function sanitizeLogoFilename(string $filename, string $extension): string
    {
        $filename = trim(basename($filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?? '';

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return 'supplier-logo.' . $extension;
        }

        return $filename;
    }

    private function logoStorageDir(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'supplier_logos';
    }

    private function logoPath(string $storedFilename): string
    {
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $storedFilename)) {
            throw new RuntimeException('Invalid stored logo filename.');
        }

        return $this->logoStorageDir() . DIRECTORY_SEPARATOR . $storedFilename;
    }

    private function deleteLogoFileByName(string $storedFilename): void
    {
        if ($storedFilename === '') {
            return;
        }

        $path = $this->logoPath($storedFilename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function newUuid(): string
    {
        $stmt = $this->pdo->prepare('SELECT UUID()');
        $stmt->execute();

        return (string)$stmt->fetchColumn();
    }

    private function isPortalOwned(?string $sourceInfo): bool
    {
        return strtoupper(trim((string)$sourceInfo)) === 'PORTAL';
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
