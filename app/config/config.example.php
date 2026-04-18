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
    "portal" => [
        // Absolute base URL of the portal, used when the shop API returns
        // fully-qualified URLs (e.g. supplier logo links). Leave empty to
        // return relative paths instead.
        "base_url" => "",
    ],
    "api" => [
        // Origins allowed to call the public shop endpoints (?page=api_shop_*).
        // Use ["*"] to allow any origin, or a concrete list to restrict.
        "cors_allowed_origins" => ["*"],
    ],
];
