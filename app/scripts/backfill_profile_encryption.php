<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}

try {
    $bootstrap = require __DIR__ . '/bootstrap.php';
    $backfill = $bootstrap['backfill'];
    $summary = $backfill->run();

    echo "Profile encryption backfill completed.\n";

    foreach ($summary as $table => $stats) {
        echo "\n[{$table}]\n";
        echo 'rows_scanned=' . $stats['_rows_scanned'] . ' rows_updated=' . $stats['_rows_updated'] . "\n";

        foreach ($stats as $column => $columnStats) {
            if (str_starts_with((string)$column, '_')) {
                continue;
            }

            echo $column
                . ' scanned=' . $columnStats['scanned']
                . ' encrypted=' . $columnStats['encrypted']
                . ' already_encrypted=' . $columnStats['already_encrypted']
                . ' skipped_empty=' . $columnStats['skipped_empty']
                . "\n";
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

