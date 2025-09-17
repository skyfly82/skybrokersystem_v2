<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\DTO;

/**
 * DTO for MEEST authentication response
 */
readonly class MeestAuthResponseDTO
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public \DateTimeImmutable $expiresAt,
        public ?string $refreshToken = null,
        public ?array $metadata = null
    ) {}

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() >= $this->expiresAt;
    }

    public function isExpiringSoon(int $bufferSeconds = 300): bool
    {
        $expiresWithBuffer = $this->expiresAt->modify("-{$bufferSeconds} seconds");
        return new \DateTimeImmutable() >= $expiresWithBuffer;
    }

    public function getAuthorizationHeader(): string
    {
        return "{$this->tokenType} {$this->accessToken}";
    }

    public static function fromApiResponse(array $response): self
    {
        $expiresIn = (int) ($response['expires_in'] ?? 3600);
        $expiresAt = (new \DateTimeImmutable())->modify("+{$expiresIn} seconds");

        return new self(
            accessToken: $response['access_token'] ?? '',
            tokenType: $response['token_type'] ?? 'Bearer',
            expiresIn: $expiresIn,
            expiresAt: $expiresAt,
            refreshToken: $response['refresh_token'] ?? null,
            metadata: $response['metadata'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'refresh_token' => $this->refreshToken,
            'metadata' => $this->metadata
        ];
    }
}