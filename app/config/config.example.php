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
        // Encryption is ON by default. The app will refuse to boot unless an
        // active key is configured; see README for the one-liner that
        // generates a SUPPLIER_PORTAL_KEY_V1 value.
        "enabled" => true,
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
        // Provide a concrete list of origins, e.g.
        //     ["https://hedvc.com", "https://www.hedvc.com"]
        // Use ["*"] to explicitly allow any origin. An empty list denies all
        // cross-origin requests.
        "cors_allowed_origins" => [],
        // Minimum seconds between tracked impressions/clicks from a single
        // caller (identified by IP + User-Agent). Prevents stat inflation.
        "track_min_interval_seconds" => 30,
    ],
];
