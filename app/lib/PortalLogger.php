<?php

declare(strict_types=1);

final class PortalLogger
{
    private static ?bool $tableAvailable = null;

    public static function write(?PDO $pdo, string $level, string $message, array $context = []): void
    {
        $level = strtoupper(trim($level));
        if (!in_array($level, ['ACTIVITY', 'ERROR', 'AUTH'], true)) {
            $level = 'ACTIVITY';
        }

        $payload = '';
        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $payload = ' ' . $encoded;
            }
        }

        error_log('[Supplier Portal][' . $level . '] ' . $message . $payload);

        if ($pdo === null || !self::tableAvailable($pdo)) {
            return;
        }

        try {
            $contextJson = null;
            if ($context !== []) {
                $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($encoded !== false) {
                    $contextJson = $encoded;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO portal_activity_logs (
                    level,
                    event,
                    context_json,
                    page,
                    user_id,
                    supplier_id
                ) VALUES (
                    :level,
                    :event,
                    :context_json,
                    :page,
                    :user_id,
                    :supplier_id
                )
            ");
            $stmt->execute([
                ':level' => $level,
                ':event' => mb_substr(trim($message), 0, 255),
                ':context_json' => $contextJson,
                ':page' => self::stringOrNull($context['page'] ?? null, 100),
                ':user_id' => self::intOrNull($context['actor_user_id'] ?? ($context['user_id'] ?? null)),
                ':supplier_id' => self::intOrNull($context['supplier_id'] ?? null),
            ]);
        } catch (Throwable $e) {
            error_log('[Supplier Portal][ERROR] Failed to persist activity log ' . $e->getMessage());
        }
    }

    private static function tableAvailable(PDO $pdo): bool
    {
        if (self::$tableAvailable !== null) {
            return self::$tableAvailable;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = 'portal_activity_logs'
                LIMIT 1
            ");
            $stmt->execute();
            self::$tableAvailable = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            self::$tableAvailable = false;
        }

        return self::$tableAvailable;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $intValue = (int)$value;
            return $intValue > 0 ? $intValue : null;
        }

        return null;
    }

    private static function stringOrNull(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $string = trim((string)$value);
        if ($string === '') {
            return null;
        }

        return mb_substr($string, 0, $maxLength);
    }
}
