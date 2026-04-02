<?php

declare(strict_types=1);

/**
 * Practical thesis scope:
 * encrypt only sensitive supplier profile data, not every column in the legacy database.
 */
final class SupplierProfileEncryptionMap
{
    /** @var array<string, string> */
    public const SUPPLIERS = [
        'email' => 'suppliers.email',
        'unique_id' => 'suppliers.unique_id',
    ];

    /** @var array<string, string> */
    public const PERSONS = [
        'first_name' => 'persons.first_name',
        'last_name' => 'persons.last_name',
        'full_name' => 'persons.full_name',
        'email_address' => 'persons.email_address',
    ];

    /** @var array<string, string> */
    public const ADDRESSES = [
        'description' => 'addresses.description',
        'complement' => 'addresses.complement',
        'city' => 'addresses.city',
        'region' => 'addresses.region',
        'postal_code' => 'addresses.postal_code',
    ];

    /** @var array<string, string> */
    public const PHONES_ENTITIES = [
        'country_prefix' => 'phones_entities.country_prefix',
        'area_code' => 'phones_entities.area_code',
        'phone_number' => 'phones_entities.phone_number',
    ];
}
