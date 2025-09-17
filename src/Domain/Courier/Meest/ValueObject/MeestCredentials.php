<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\ValueObject;

use App\Domain\Courier\Meest\Exception\MeestValidationException;

/**
 * Value Object representing MEEST API credentials
 */
readonly class MeestCredentials
{
    public function __construct(
        public string $username,
        public string $password,
        public string $baseUrl
    ) {
        $this->validateCredentials();
    }

    private function validateCredentials(): void
    {
        if (empty($this->username)) {
            throw new MeestValidationException('Username cannot be empty');
        }

        if (empty($this->password)) {
            throw new MeestValidationException('Password cannot be empty');
        }

        if (empty($this->baseUrl) || !filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new MeestValidationException('Base URL must be a valid URL');
        }
    }

    public function getAuthPayload(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password
        ];
    }

    public function isTestEnvironment(): bool
    {
        return str_contains($this->baseUrl, 'stage') || str_contains($this->baseUrl, 'test');
    }
}