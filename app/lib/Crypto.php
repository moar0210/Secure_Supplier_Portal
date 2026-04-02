<?php

declare(strict_types=1);

final class Crypto
{
    private const ENVELOPE_PREFIX = 'enc';
    private const KEY_BYTES = 32;
    private const NONCE_BYTES = 24;
    private const TAG_BYTES = 16;
    private const DRIVER_NAME = 'libsodium-xchacha20poly1305-ietf';

    private bool $enabled;
    private string $activeKeyId;
    /** @var array<string, string> */
    private array $keys;

    public function __construct(array $config = [])
    {
        $this->enabled = (bool)($config['enabled'] ?? false);
        $this->activeKeyId = trim((string)($config['active_key_id'] ?? ''));
        $this->keys = $this->loadKeys((array)($config['keys'] ?? []));

        if (!$this->enabled) {
            return;
        }

        if (!extension_loaded('sodium')) {
            throw new RuntimeException('Application encryption requires the sodium PHP extension.');
        }

        if ($this->activeKeyId === '') {
            throw new RuntimeException('Application encryption is enabled but no active key id is configured.');
        }

        if (!isset($this->keys[$this->activeKeyId])) {
            throw new RuntimeException('Application encryption is enabled but the active key is missing or invalid.');
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function activeKeyId(): string
    {
        return $this->enabled ? $this->activeKeyId : '';
    }

    public function configuredKeyCount(): int
    {
        return count($this->keys);
    }

    public function driverName(): string
    {
        return $this->enabled ? self::DRIVER_NAME : 'disabled';
    }

    public function encryptString(string $plaintext, string $aad, ?string $keyId = null): string
    {
        if ($plaintext === '') {
            return '';
        }

        if (!$this->enabled) {
            return $plaintext;
        }

        $keyId = $keyId === null ? $this->activeKeyId : trim($keyId);
        if ($keyId === '') {
            throw new RuntimeException('No key id was provided for encryption.');
        }

        $key = $this->keys[$keyId] ?? null;
        if ($key === null) {
            throw new RuntimeException('Unknown encryption key id: ' . $keyId);
        }

        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);

        return self::ENVELOPE_PREFIX
            . ':'
            . $keyId
            . ':'
            . $this->base64UrlEncode($nonce . $ciphertext);
    }

    public function decryptString(string $ciphertext, string $aad): string
    {
        if ($ciphertext === '') {
            return '';
        }

        if (!$this->isEncryptedValue($ciphertext)) {
            return $ciphertext;
        }

        if (!$this->enabled) {
            throw new RuntimeException('Encrypted data was found but application encryption is disabled.');
        }

        $parts = explode(':', $ciphertext, 3);
        if (count($parts) !== 3 || $parts[0] !== self::ENVELOPE_PREFIX || trim($parts[1]) === '' || trim($parts[2]) === '') {
            throw new RuntimeException('Malformed encrypted value envelope.');
        }

        $keyId = trim($parts[1]);
        $payload = $this->base64UrlDecode($parts[2]);
        if (strlen($payload) <= self::NONCE_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Malformed encrypted value payload.');
        }

        $key = $this->keys[$keyId] ?? null;
        if ($key === null) {
            throw new RuntimeException('No configured key can decrypt encrypted value with key id: ' . $keyId);
        }

        $nonce = substr($payload, 0, self::NONCE_BYTES);
        $encrypted = substr($payload, self::NONCE_BYTES);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($encrypted, $aad, $nonce, $key);
        if ($plaintext === false) {
            throw new RuntimeException('Encrypted value could not be authenticated.');
        }

        return $plaintext;
    }

    public function encryptNullable(?string $plaintext, string $aad): ?string
    {
        if ($plaintext === null) {
            return null;
        }

        if ($plaintext === '') {
            return '';
        }

        return $this->encryptString($plaintext, $aad);
    }

    public function decryptNullable(?string $ciphertext, string $aad): ?string
    {
        if ($ciphertext === null) {
            return null;
        }

        if ($ciphertext === '') {
            return '';
        }

        return $this->decryptString($ciphertext, $aad);
    }

    public function isEncryptedValue(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::ENVELOPE_PREFIX . ':');
    }

    /**
     * @param array<string, string> $rawKeys
     * @return array<string, string>
     */
    private function loadKeys(array $rawKeys): array
    {
        $keys = [];

        foreach ($rawKeys as $keyId => $rawValue) {
            $keyId = trim((string)$keyId);
            $rawValue = trim((string)$rawValue);

            if ($keyId === '' || $rawValue === '') {
                continue;
            }

            $keys[$keyId] = $this->normalizeKey($rawValue, $keyId);
        }

        return $keys;
    }

    private function normalizeKey(string $rawValue, string $keyId): string
    {
        if (strlen($rawValue) === self::KEY_BYTES) {
            return $rawValue;
        }

        if (ctype_xdigit($rawValue) && strlen($rawValue) === self::KEY_BYTES * 2) {
            $decoded = hex2bin($rawValue);
            if ($decoded === false) {
                throw new RuntimeException('Invalid hex encryption key for key id: ' . $keyId);
            }

            return $decoded;
        }

        $decoded = base64_decode($this->normalizeBase64($rawValue), true);
        if ($decoded !== false && strlen($decoded) === self::KEY_BYTES) {
            return $decoded;
        }

        throw new RuntimeException('Encryption key "' . $keyId . '" must decode to exactly 32 bytes.');
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $encoded): string
    {
        $decoded = base64_decode($this->normalizeBase64($encoded), true);
        if ($decoded === false) {
            throw new RuntimeException('Encrypted value payload is not valid base64url.');
        }

        return $decoded;
    }

    private function normalizeBase64(string $value): string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        return $normalized;
    }
}
