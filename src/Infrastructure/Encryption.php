<?php

declare(strict_types=1);

namespace Casablanca\Booking\Infrastructure;

use RuntimeException;

final class Encryption
{
    public function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        if (function_exists('sodium_crypto_secretbox')) {
            $key = $this->deriveKey();
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plainText, $nonce, $key);

            return 'sodium:' . base64_encode($nonce . $cipher);
        }

        return 'b64:' . base64_encode($this->xorWithKey($plainText));
    }

    public function decrypt(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }

        if (str_starts_with($encrypted, 'sodium:') && function_exists('sodium_crypto_secretbox_open')) {
            $payload = base64_decode(substr($encrypted, 7), true);
            if ($payload === false || strlen($payload) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                throw new RuntimeException('Invalid encrypted API key payload.');
            }

            $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->deriveKey());

            if ($plain === false) {
                throw new RuntimeException('Failed to decrypt CASABLANCA API key.');
            }

            return $plain;
        }

        if (str_starts_with($encrypted, 'b64:')) {
            $decoded = base64_decode(substr($encrypted, 4), true);
            if ($decoded === false) {
                throw new RuntimeException('Invalid base64-encoded API key.');
            }

            return $this->xorWithKey($decoded);
        }

        throw new RuntimeException('Unsupported API key encryption format.');
    }

    private function deriveKey(): string
    {
        $authKey = defined('AUTH_KEY') ? AUTH_KEY : '';
        if ($authKey === '') {
            throw new RuntimeException('AUTH_KEY is empty — cannot encrypt/decrypt CASABLANCA API keys.');
        }

        return hash('sha256', $authKey, true);
    }

    private function xorWithKey(string $input): string
    {
        $key = $this->deriveKey();
        $keyLength = strlen($key);
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $output .= $input[$i] ^ $key[$i % $keyLength];
        }

        return $output;
    }
}
