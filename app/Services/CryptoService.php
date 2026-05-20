<?php

declare(strict_types=1);

namespace App\Services;

final class CryptoService
{
    private const CIPHER = 'aes-256-cbc';

    public function encrypt(?string $plain): string
    {
        $plain = (string) $plain;
        if ($plain === '') {
            return '';
        }

        $iv = random_bytes(16);
        $key = $this->keyMaterial();
        $ciphertext = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if (! is_string($ciphertext) || $ciphertext === '') {
            return '';
        }

        return base64_encode(json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'value' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR));
    }

    public function decrypt(?string $payload): string
    {
        $payload = (string) $payload;
        if ($payload === '') {
            return '';
        }

        $decoded = json_decode((string) base64_decode($payload, true), true);
        if (! is_array($decoded)) {
            return '';
        }

        $iv = base64_decode((string) ($decoded['iv'] ?? ''), true);
        $ciphertext = base64_decode((string) ($decoded['value'] ?? ''), true);
        if (! is_string($iv) || $iv === '' || ! is_string($ciphertext) || $ciphertext === '') {
            return '';
        }

        $plain = openssl_decrypt($ciphertext, self::CIPHER, $this->keyMaterial(), OPENSSL_RAW_DATA, $iv);

        return is_string($plain) ? $plain : '';
    }

    public function strongKeyConfigured(): bool
    {
        return trim((string) env('APP_KEY', '')) !== '';
    }

    private function keyMaterial(): string
    {
        $material = trim((string) env('APP_KEY', ''));
        if ($material === '') {
            $material = implode('|', [
                (string) env('APP_URL', 'uc200.local'),
                (string) env('DB_DATABASE', 'uc200_core'),
                (string) env('DB_USERNAME', 'root'),
                (string) env('DB_PASSWORD', ''),
            ]);
        }

        return hash('sha256', $material, true);
    }
}
