<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Service;

use App\Domain\Courier\Meest\Entity\MeestShipment;
use App\Domain\Courier\Meest\Exception\MeestIntegrationException;
use App\Domain\Courier\Meest\Repository\MeestShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for handling MEEST shipping labels
 */
class MeestLabelService
{
    private const LABELS_DIRECTORY = 'var/labels/meest';
    private const MAX_DOWNLOAD_ATTEMPTS = 3;
    private const DOWNLOAD_TIMEOUT = 30;

    public function __construct(
        private readonly MeestApiClient $apiClient,
        private readonly MeestShipmentRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir
    ) {}

    /**
     * Generate and store label for shipment
     */
    public function generateAndStoreLabel(MeestShipment $shipment): string
    {
        $this->logger->info('Generating MEEST label', [
            'tracking_number' => $shipment->getTrackingNumber(),
            'shipment_id' => $shipment->getShipmentId()
        ]);

        try {
            // Step 1: Generate label via API
            $labelUrl = $this->apiClient->generateLabel($shipment->getTrackingNumber());

            // Step 2: Download and store label locally
            $localPath = $this->downloadAndStoreLabel($shipment, $labelUrl);

            // Step 3: Update shipment with label path
            $shipment->setLabelUrl($localPath);
            $this->repository->save($shipment);

            $this->logger->info('MEEST label generated and stored successfully', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'label_path' => $localPath
            ]);

            return $localPath;

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate MEEST label', [
                'tracking_number' => $shipment->getTrackingNumber(),
                'error' => $e->getMessage()
            ]);

            if ($e instanceof MeestIntegrationException) {
                throw $e;
            }

            throw new MeestIntegrationException('Failed to generate label: ' . $e->getMessage());
        }
    }

    /**
     * Regenerate label for existing shipment
     */
    public function regenerateLabel(string $trackingNumber): string
    {
        $shipment = $this->repository->findByTrackingNumber($trackingNumber);
        if (!$shipment) {
            throw new MeestIntegrationException("Shipment not found: {$trackingNumber}");
        }

        // Remove old label file if exists
        if ($shipment->getLabelUrl()) {
            $this->removeOldLabel($shipment->getLabelUrl());
        }

        return $this->generateAndStoreLabel($shipment);
    }

    /**
     * Download label from URL and store locally
     */
    private function downloadAndStoreLabel(MeestShipment $shipment, string $labelUrl): string
    {
        $attempts = 0;

        while ($attempts < self::MAX_DOWNLOAD_ATTEMPTS) {
            try {
                $this->logger->info('Downloading MEEST label', [
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'label_url' => $labelUrl,
                    'attempt' => $attempts + 1
                ]);

                // Download label content
                $response = $this->httpClient->request('GET', $labelUrl, [
                    'timeout' => self::DOWNLOAD_TIMEOUT,
                    'headers' => [
                        'User-Agent' => 'SkyBroker/2.0 MEEST Label Downloader'
                    ]
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException("HTTP {$response->getStatusCode()} received");
                }

                $content = $response->getContent();

                // Validate PDF content
                if (!$this->isPdfContent($content)) {
                    throw new \RuntimeException('Downloaded content is not a valid PDF');
                }

                // Generate local file path
                $fileName = $this->generateLabelFileName($shipment);
                $localPath = $this->getLabelsDirectory() . '/' . $fileName;

                // Ensure directory exists
                $this->ensureDirectoryExists(dirname($localPath));

                // Save file
                file_put_contents($localPath, $content);

                // Verify file was saved correctly
                if (!file_exists($localPath) || filesize($localPath) === 0) {
                    throw new \RuntimeException('Failed to save label file');
                }

                $this->logger->info('Label downloaded and saved successfully', [
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'local_path' => $localPath,
                    'file_size' => filesize($localPath)
                ]);

                return $localPath;

            } catch (\Exception $e) {
                $attempts++;

                $this->logger->warning('Failed to download label', [
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'attempt' => $attempts,
                    'error' => $e->getMessage()
                ]);

                if ($attempts >= self::MAX_DOWNLOAD_ATTEMPTS) {
                    throw new MeestIntegrationException(
                        "Failed to download label after {$attempts} attempts: {$e->getMessage()}"
                    );
                }

                // Wait before retry
                sleep(1);
            }
        }

        throw new MeestIntegrationException('Unexpected error in label download');
    }

    /**
     * Generate label file name
     */
    private function generateLabelFileName(MeestShipment $shipment): string
    {
        $date = $shipment->getCreatedAt()->format('Y-m-d');
        $trackingNumber = $this->slugger->slug($shipment->getTrackingNumber())->lower();

        return "{$date}_{$trackingNumber}_label.pdf";
    }

    /**
     * Get labels directory path
     */
    private function getLabelsDirectory(): string
    {
        return $this->projectDir . '/' . self::LABELS_DIRECTORY;
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }
        }
    }

    /**
     * Check if content is valid PDF
     */
    private function isPdfContent(string $content): bool
    {
        return str_starts_with($content, '%PDF-') && strlen($content) > 100;
    }

    /**
     * Remove old label file
     */
    private function removeOldLabel(string $labelPath): void
    {
        $fullPath = $this->projectDir . '/' . ltrim($labelPath, '/');

        if (file_exists($fullPath)) {
            try {
                unlink($fullPath);
                $this->logger->info('Old label file removed', ['path' => $fullPath]);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to remove old label file', [
                    'path' => $fullPath,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Get label file path for shipment
     */
    public function getLabelPath(string $trackingNumber): ?string
    {
        $shipment = $this->repository->findByTrackingNumber($trackingNumber);

        if (!$shipment || !$shipment->hasLabel()) {
            return null;
        }

        $fullPath = $this->projectDir . '/' . ltrim($shipment->getLabelUrl(), '/');

        return file_exists($fullPath) ? $fullPath : null;
    }

    /**
     * Check if label exists and is valid
     */
    public function hasValidLabel(string $trackingNumber): bool
    {
        $labelPath = $this->getLabelPath($trackingNumber);

        return $labelPath !== null && filesize($labelPath) > 0;
    }
}