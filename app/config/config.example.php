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
        "enabled" => false,
        "active_key_id" => "v1",
        "keys" => [
            "v1" => getenv("SUPPLIER_PORTAL_KEY_V1") ?: "",
        ],
    ],
];
