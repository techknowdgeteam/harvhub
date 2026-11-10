<?php
/**
 * Private VPS provisioning value encryption.
 * Each VPS secret is encrypted as its own scalar value and persisted in its own
 * *_enc column. No structured/JSON payload is used for active storage.
 * Configure SERVER_VPS_ENCRYPTION_KEY in the PHP-FPM/hosting environment.
 */
if (!function_exists('serverVpsEncryptionKey')) {
    function serverVpsEncryptionKey(): ?string
    {
        $configured = (string)getenv('SERVER_VPS_ENCRYPTION_KEY');
        if (strlen($configured) < 24) return null;
        return hash('sha256', $configured, true);
    }
}

if (!function_exists('serverVpsEncryptValue')) {
    function serverVpsEncryptValue(string $value): ?string
    {
        $key = serverVpsEncryptionKey();
        if ($key === null) return null;
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL is required for encrypted VPS provisioning fields.');
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new RuntimeException('Unable to encrypt private VPS provisioning value.');
        return 'v2:' . base64_encode($iv . $tag . $cipher);
    }
}

if (!function_exists('serverVpsDecryptValue')) {
    function serverVpsDecryptValue(?string $encoded): ?string
    {
        if ($encoded === null || $encoded === '') return '';
        $key = serverVpsEncryptionKey();
        if ($key === null || !function_exists('openssl_decrypt')) return null;
        $versioned = str_starts_with($encoded, 'v2:');
        $payload = $versioned ? substr($encoded, 3) : $encoded;
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 29) return null;
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }
}

/**
 * Migration-only reader for the former encrypted aggregate payload column.
 * Do not call this from normal VPS save, read, purchase or registration flows.
 */
if (!function_exists('serverVpsDecryptLegacyPayload')) {
    function serverVpsDecryptLegacyPayload(?string $encoded): array
    {
        $plain = serverVpsDecryptValue($encoded);
        if ($plain === null || $plain === '') return [];
        $decoded = json_decode($plain, true);
        return is_array($decoded) ? $decoded : [];
    }
}

/**
 * Migration-only compatibility for individual columns written by the prior
 * implementation as encrypted {"value":"..."} envelopes. New values are raw.
 */
if (!function_exists('serverVpsNormalizeLegacyEncryptedValue')) {
    function serverVpsNormalizeLegacyEncryptedValue(?string $encoded): ?string
    {
        $plain = serverVpsDecryptValue($encoded);
        if ($plain === null || $plain === '' || str_starts_with((string)$encoded, 'v2:')) return $plain;
        $decoded = json_decode($plain, true);
        if (is_array($decoded) && array_key_exists('value', $decoded) && is_scalar($decoded['value'])) {
            return (string)$decoded['value'];
        }
        return $plain;
    }
}
