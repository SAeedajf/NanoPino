<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use InvalidArgumentException;

final class WordPressThemeProvenance
{
    public const ALGORITHM = 'ed25519-theme-manifest-v1';

    /** @return array<string,mixed> */
    public function sign(
        string $manifestSha256,
        ?string $sourceSha256,
        ?string $themeRoot,
        string $keyId,
        string $secretKey,
    ): array {
        $manifestSha256 = $this->digest($manifestSha256, 'manifest_sha256');
        $sourceSha256 = $sourceSha256 !== null ? $this->digest($sourceSha256, 'source_sha256') : null;
        $keyId = trim($keyId);
        if ($keyId === '' || preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $keyId) !== 1) {
            throw new InvalidArgumentException('Provenance key id is invalid.');
        }

        $secret = $this->binaryKey($secretKey, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, 'secret key');
        $payload = $this->payload($manifestSha256, $sourceSha256, $themeRoot);
        return [
            'schema' => 1,
            'algorithm' => self::ALGORITHM,
            'key_id' => $keyId,
            'manifest_sha256' => $manifestSha256,
            'source_sha256' => $sourceSha256,
            'theme_root' => $themeRoot,
            'signature' => base64_encode(sodium_crypto_sign_detached($payload, $secret)),
            'public_key' => base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret)),
        ];
    }

    /**
     * @param array<string,mixed> $envelope
     * @param array<string,string> $trustedKeys key id to raw or base64 public key
     * @return array{present:bool,verified:bool,key_id:?string,reason:string}
     */
    public function verify(
        array $envelope,
        string $manifestSha256,
        ?string $sourceSha256,
        ?string $themeRoot,
        array $trustedKeys,
    ): array {
        $keyId = isset($envelope['key_id']) ? trim((string)$envelope['key_id']) : null;
        if ($envelope === []) {
            return ['present' => false, 'verified' => false, 'key_id' => null, 'reason' => 'missing'];
        }
        if (($envelope['algorithm'] ?? null) !== self::ALGORITHM || ($envelope['schema'] ?? null) !== 1) {
            return ['present' => true, 'verified' => false, 'key_id' => $keyId ?: null, 'reason' => 'unsupported_envelope'];
        }
        if ($keyId === null || !isset($trustedKeys[$keyId])) {
            return ['present' => true, 'verified' => false, 'key_id' => $keyId ?: null, 'reason' => 'untrusted_key'];
        }

        try {
            $manifestSha256 = $this->digest($manifestSha256, 'manifest_sha256');
            $sourceSha256 = $sourceSha256 !== null ? $this->digest($sourceSha256, 'source_sha256') : null;
            if (($envelope['manifest_sha256'] ?? null) !== $manifestSha256
                || ($envelope['source_sha256'] ?? null) !== $sourceSha256
                || ($envelope['theme_root'] ?? null) !== $themeRoot
            ) {
                return ['present' => true, 'verified' => false, 'key_id' => $keyId, 'reason' => 'digest_mismatch'];
            }
            $signature = base64_decode((string)($envelope['signature'] ?? ''), true);
            $publicKey = $this->binaryKey($trustedKeys[$keyId], SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, 'public key');
            if (!is_string($signature) || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
                return ['present' => true, 'verified' => false, 'key_id' => $keyId, 'reason' => 'invalid_signature'];
            }
            $verified = sodium_crypto_sign_verify_detached(
                $signature,
                $this->payload($manifestSha256, $sourceSha256, $themeRoot),
                $publicKey,
            );
            return [
                'present' => true,
                'verified' => $verified,
                'key_id' => $keyId,
                'reason' => $verified ? 'verified' : 'invalid_signature',
            ];
        } catch (\Throwable) {
            return ['present' => true, 'verified' => false, 'key_id' => $keyId, 'reason' => 'invalid_envelope'];
        }
    }

    private function payload(string $manifestSha256, ?string $sourceSha256, ?string $themeRoot): string
    {
        return json_encode([
            'algorithm' => self::ALGORITHM,
            'manifest_sha256' => $manifestSha256,
            'source_sha256' => $sourceSha256,
            'theme_root' => $themeRoot,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function digest(string $value, string $field): string
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $value) !== 1) {
            throw new InvalidArgumentException($field . ' must be a SHA-256 digest.');
        }
        return strtolower($value);
    }

    private function binaryKey(string $value, int $length, string $label): string
    {
        $decoded = base64_decode($value, true);
        $key = is_string($decoded) && strlen($decoded) === $length ? $decoded : $value;
        if (strlen($key) !== $length) throw new InvalidArgumentException('Invalid provenance ' . $label . '.');
        return $key;
    }
}
