<?php

declare(strict_types=1);

final class ProfileEncryptionBackfill
{
    private PDO $pdo;
    private Crypto $crypto;

    public function __construct(PDO $pdo, Crypto $crypto)
    {
        $this->pdo = $pdo;
        $this->crypto = $crypto;
    }

    public function run(?array $supplierIds = null): array
    {
        if (!$this->crypto->isEnabled()) {
            throw new RuntimeException('Profile encryption backfill requires crypto.enabled=true with a valid key configuration.');
        }

        $supplierIds = $this->normalizeSupplierIds($supplierIds);
        $supplierScope = $this->supplierScopePredicate($supplierIds, 's');

        $this->pdo->beginTransaction();

        try {
            $summary = [
                'suppliers' => $this->backfillRows(
                    'suppliers',
                    'id_supplier',
                    "
                        SELECT s.id_supplier, s.email, s.unique_id
                        FROM suppliers s
                        WHERE 1 = 1{$supplierScope}
                        ORDER BY s.id_supplier ASC
                    ",
                    SupplierProfileEncryptionMap::SUPPLIERS
                ),
                'persons' => $this->backfillRows(
                    'persons',
                    'ID_Person',
                    "
                        SELECT p.ID_Person, p.first_name, p.last_name, p.full_name, p.email_address
                        FROM persons p
                        WHERE p.uuid_entity IN (
                            SELECT DISTINCT s.uuid_person_contact
                            FROM suppliers s
                            WHERE s.uuid_person_contact IS NOT NULL
                              AND s.uuid_person_contact <> ''
                              AND UPPER(s.uuid_person_contact) <> 'EMPTY'
                              {$supplierScope}
                        )
                        ORDER BY p.ID_Person ASC
                    ",
                    SupplierProfileEncryptionMap::PERSONS
                ),
                'addresses' => $this->backfillRows(
                    'addresses',
                    'id_address',
                    "
                        SELECT a.id_address, a.description, a.complement, a.city, a.region, a.postal_code
                        FROM addresses a
                        WHERE a.uuid_address IN (
                            SELECT DISTINCT s.uuid_address_main
                            FROM suppliers s
                            WHERE s.uuid_address_main IS NOT NULL
                              AND s.uuid_address_main <> ''
                              AND UPPER(s.uuid_address_main) <> 'EMPTY'
                              {$supplierScope}
                            UNION
                            SELECT DISTINCT ae.uuid_address
                            FROM addresses_entities ae
                            INNER JOIN suppliers s
                              ON s.uuid_supplier = ae.uuid_entity
                             AND ae.entity_name = 'SUPPLIER'
                            WHERE ae.entity_name = 'SUPPLIER'
                              AND ae.uuid_address IS NOT NULL
                              AND ae.uuid_address <> ''
                              AND UPPER(ae.uuid_address) <> 'EMPTY'
                              {$supplierScope}
                            UNION
                            SELECT DISTINCT p.uuid_address_main
                            FROM persons p
                            INNER JOIN suppliers s ON s.uuid_person_contact = p.uuid_entity
                            WHERE p.uuid_address_main IS NOT NULL
                              AND p.uuid_address_main <> ''
                              AND UPPER(p.uuid_address_main) <> 'EMPTY'
                              {$supplierScope}
                        )
                        ORDER BY a.id_address ASC
                    ",
                    SupplierProfileEncryptionMap::ADDRESSES
                ),
                'phones_entities' => $this->backfillRows(
                    'phones_entities',
                    'id_phone_entity',
                    "
                        SELECT pe.id_phone_entity, pe.country_prefix, pe.area_code, pe.phone_number
                        FROM phones_entities pe
                        INNER JOIN suppliers s
                          ON s.uuid_supplier = pe.uuid_entity
                         AND pe.entity_name = 'SUPPLIER'
                        WHERE 1 = 1{$supplierScope}
                        ORDER BY pe.id_phone_entity ASC
                    ",
                    SupplierProfileEncryptionMap::PHONES_ENTITIES
                ),
            ];

            $this->pdo->commit();

            return $summary;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function backfillRows(string $table, string $idColumn, string $selectSql, array $fieldMap): array
    {
        $stats = [
            '_rows_scanned' => 0,
            '_rows_updated' => 0,
        ];

        foreach (array_keys($fieldMap) as $column) {
            $stats[$column] = [
                'scanned' => 0,
                'encrypted' => 0,
                'already_encrypted' => 0,
                'skipped_empty' => 0,
            ];
        }

        $stmt = $this->pdo->prepare($selectSql);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats['_rows_scanned']++;

            $assignments = [];
            $params = [
                ':row_id' => $row[$idColumn],
            ];

            foreach ($fieldMap as $column => $aad) {
                $stats[$column]['scanned']++;

                $value = $row[$column] ?? null;
                if ($value === null || $value === '') {
                    $stats[$column]['skipped_empty']++;
                    continue;
                }

                $value = (string)$value;
                if ($this->crypto->isEncryptedValue($value)) {
                    $this->crypto->decryptString($value, $aad);
                    $stats[$column]['already_encrypted']++;
                    continue;
                }

                $assignments[] = "`{$column}` = :{$column}";
                $params[':' . $column] = $this->crypto->encryptString($value, $aad);
                $stats[$column]['encrypted']++;
            }

            if ($assignments === []) {
                continue;
            }

            $sql = sprintf(
                'UPDATE `%s` SET %s WHERE `%s` = :row_id',
                $table,
                implode(', ', $assignments),
                $idColumn
            );

            $update = $this->pdo->prepare($sql);
            $update->execute($params);
            $stats['_rows_updated']++;
        }

        return $stats;
    }

    private function normalizeSupplierIds(?array $supplierIds): ?array
    {
        if ($supplierIds === null) {
            return null;
        }

        $normalized = array_values(array_filter(array_map(
            static fn(mixed $value): int => (int)$value,
            $supplierIds
        ), static fn(int $value): bool => $value > 0));

        return $normalized === [] ? [-1] : $normalized;
    }

    private function supplierScopePredicate(?array $supplierIds, string $alias): string
    {
        if ($supplierIds === null) {
            return '';
        }

        return ' AND ' . $alias . '.id_supplier IN (' . implode(', ', $supplierIds) . ')';
    }
}
