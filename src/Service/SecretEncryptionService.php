<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;
use RuntimeException;
use SodiumException;

class SecretEncryptionService
{
    private readonly string $encryptionKey;

    public function __construct(
        string $appSecret,
        private readonly string $encryptionSalt = 'skybroker_secrets_v2'
    ) {
        if (empty($appSecret)) {
            throw new InvalidArgumentException('App secret cannot be empty');
        }

        // Derive encryption key from app secret using PBKDF2
        $this->encryptionKey = hash_pbkdf2(
            'sha256',
            $appSecret,
            $this->encryptionSalt,
            10000,
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            true
        );
    }

    /**
     * Encrypt a secret value
     */
    public function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            throw new InvalidArgumentException('Plaintext cannot be empty');
        }

        try {
            // Generate a random nonce
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            
            // Encrypt the data
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->encryptionKey);
            
            // Combine nonce and ciphertext
            $encrypted = $nonce . $ciphertext;
            
            // Base64 encode for storage
            return base64_encode($encrypted);
        } catch (SodiumException $e) {
            throw new RuntimeException('Encryption failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decrypt a secret value
     */
    public function decrypt(string $encryptedData): string
    {
        if (empty($encryptedData)) {
            throw new InvalidArgumentException('Encrypted data cannot be empty');
        }

        try {
            // Base64 decode
            $data = base64_decode($encryptedData, true);
            if ($data === false) {
                throw new RuntimeException('Invalid encrypted data format');
            }

            // Ensure we have enough data
            if (strlen($data) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                throw new RuntimeException('Encrypted data too short');
            }

            // Extract nonce and ciphertext
            $nonce = substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            // Decrypt
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encryptionKey);
            
            if ($plaintext === false) {
                throw new RuntimeException('Decryption failed - invalid key or corrupted data');
            }

            return $plaintext;
        } catch (SodiumException $e) {
            throw new RuntimeException('Decryption failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify if data can be decrypted (useful for testing)
     */
    public function canDecrypt(string $encryptedData): bool
    {
        try {
            $this->decrypt($encryptedData);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Generate a secure random secret (useful for tokens)
     */
    public function generateSecret(int $length = 32): string
    {
        if ($length < 8) {
            throw new InvalidArgumentException('Secret length must be at least 8 characters');
        }

        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate a cryptographically secure API key
     */
    public function generateApiKey(string $prefix = '', int $length = 64): string
    {
        $randomPart = $this->generateSecret($length - strlen($prefix));
        return $prefix . $randomPart;
    }

    /**
     * Hash a secret for comparison (one-way)
     */
    public function hashSecret(string $secret): string
    {
        return password_hash($secret, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }

    /**
     * Verify a hashed secret
     */
    public function verifyHashedSecret(string $secret, string $hash): bool
    {
        return password_verify($secret, $hash);
    }

    /**
     * Clean up sensitive data from memory (best effort)
     */
    public function clearSensitiveData(string &$data): void
    {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($data);
        } else {
            // Fallback - overwrite with random data
            $length = strlen($data);
            $data = str_repeat('0', $length);
            unset($data);
        }
    }

    public function __destruct()
    {
        // Clear the encryption key from memory
        if (function_exists('sodium_memzero')) {
            $key = $this->encryptionKey;
            sodium_memzero($key);
        }
    }
}