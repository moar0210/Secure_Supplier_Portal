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
        // See README for generating SUPPLIER_PORTAL_KEY_V1.
        "enabled" => true,
        "active_key_id" => "v1",
        "keys" => [
            "v1" => getenv("SUPPLIER_PORTAL_KEY_V1") ?: "",
        ],
    ],
    "portal" => [
        // Empty means the shop API returns relative logo URLs.
        "base_url" => "",
    ],
    "api" => [
        // Use ["*"] only for a public catalogue.
        "cors_allowed_origins" => [],
        // Per IP + User-Agent.
        "track_min_interval_seconds" => 30,
    ],
    "auth" => [
        // Local demo only.
        "password_reset_reveal_link" => false,
    ],
];
