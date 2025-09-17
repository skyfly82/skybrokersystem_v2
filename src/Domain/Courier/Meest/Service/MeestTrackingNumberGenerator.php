<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use Psr\Log\LoggerInterface;

/**
 * Service for generating unique MEEST tracking numbers
 */
class MeestTrackingNumberGenerator
{
    private const PREFIX = 'ME';
    private const LENGTH = 12; // Total length including prefix

    public function __construct(
        private readonly MeestShipmentRepository $repository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Generate unique tracking number
     */
    public function generate(): string
    {
        $maxAttempts = 10;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $trackingNumber = $this->generateTrackingNumber();

            // Check if tracking number is unique
            if (!$this->repository->findByTrackingNumber($trackingNumber)) {
                $this->logger->info('Generated unique MEEST tracking number', [
                    'tracking_number' => $trackingNumber,
                    'attempts' => $attempts + 1
                ]);

                return $trackingNumber;
            }

            $attempts++;
            $this->logger->warning('Tracking number collision, regenerating', [
                'tracking_number' => $trackingNumber,
                'attempt' => $attempts
            ]);
        }

        throw new \RuntimeException("Failed to generate unique tracking number after {$maxAttempts} attempts");
    }

    /**
     * Generate tracking number with format: ME + 10 digits
     */
    private function generateTrackingNumber(): string
    {
        // Generate timestamp-based number for better uniqueness
        $timestamp = time();
        $microseconds = (int)(microtime(true) * 1000000) % 1000000;
        $random = random_int(100, 999);

        // Combine and take last 10 digits
        $number = substr((string)($timestamp . $microseconds . $random), -10);

        return self::PREFIX . $number;
    }

    /**
     * Validate tracking number format
     */
    public function isValidFormat(string $trackingNumber): bool
    {
        return preg_match('/^' . self::PREFIX . '\d{10}$/', $trackingNumber) === 1;
    }

    /**
     * Extract sequence from tracking number
     */
    public function extractSequence(string $trackingNumber): ?string
    {
        if (!$this->isValidFormat($trackingNumber)) {
            return null;
        }

        return substr($trackingNumber, strlen(self::PREFIX));
    }
}