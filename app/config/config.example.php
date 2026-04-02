<?php
return [
    "db" => [
        "host" => "127.0.0.1",
        "name" => "gpp4_0_sv_accounting",
        "user" => "CHANGE_ME",
        "pass" => "CHANGE_ME",
        "charset" => "utf8mb4",
        "port" => 3306
    ],
    "crypto" => [
        // When false, sensitive profile writes remain plaintext in the database.
        "enabled" => false,
        // New writes use this key id. Keep older key ids below during rotation so old data can still decrypt.
        "active_key_id" => "v1",
        "keys" => [
            // Never commit real keys. Load one or more local/environment-provided keys here.
            // Multiple key ids may remain configured temporarily for decryption during key rotation.
            "v1" => getenv("SUPPLIER_PORTAL_KEY_V1") ?: "",
        ],
    ],
];
