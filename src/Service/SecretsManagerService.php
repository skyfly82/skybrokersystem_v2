<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Secret;
use App\Entity\SecretAuditLog;
use App\Enum\SecretAction;
use App\Enum\SecretCategory;
use App\Repository\SecretAuditLogRepository;
use App\Repository\SecretRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class SecretsManagerService
{
    public function __construct(
        private readonly SecretRepository $secretRepository,
        private readonly SecretAuditLogRepository $auditLogRepository,
        private readonly SecretEncryptionService $encryptionService,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Store a new secret or update existing one
     */
    public function storeSecret(
        string $category,
        string $name,
        string $value,
        ?string $description = null,
        ?DateTimeImmutable $expiresAt = null,
        ?array $metadata = null
    ): Secret {
        $this->validateCategoryAndName($category, $name, $value);

        // Check if secret already exists
        $existingSecret = $this->secretRepository->findActiveByName($category, $name);
        
        if ($existingSecret) {
            return $this->rotateSecret($existingSecret, $value, $description, $expiresAt, $metadata);
        }

        // Create new secret
        $secret = new Secret();
        $secret->setCategory($category)
            ->setName($name)
            ->setEncryptedValue($this->encryptionService->encrypt($value))
            ->setDescription($description)
            ->setExpiresAt($expiresAt)
            ->setMetadata($metadata)
            ->setCreatedBy($this->getCurrentUser())
            ->setUpdatedBy($this->getCurrentUser());

        $this->secretRepository->save($secret, true);

        $this->logAction(
            $secret,
            SecretAction::CREATED,
            sprintf('Secret created in category %s with name %s', $category, $name)
        );

        $this->logger->info('Secret created', [
            'category' => $category,
            'name' => $name,
            'user' => $this->getCurrentUser(),
        ]);

        return $secret;
    }

    /**
     * Retrieve a secret value by category and name
     */
    public function getSecret(string $category, string $name): ?string
    {
        $secret = $this->secretRepository->findActiveByName($category, $name);

        if (!$secret) {
            $this->logAccessDenied($category, $name, 'Secret not found');
            return null;
        }

        if ($secret->isExpired()) {
            $this->logAccessDenied($category, $name, 'Secret expired');
            return null;
        }

        try {
            $value = $this->encryptionService->decrypt($secret->getEncryptedValue());
            
            // Update last accessed timestamp
            $this->secretRepository->updateLastAccessed($secret);
            
            $this->logAction(
                $secret,
                SecretAction::RETRIEVED,
                sprintf('Secret retrieved: %s.%s', $category, $name)
            );

            return $value;
        } catch (RuntimeException $e) {
            $this->logAction(
                $secret,
                SecretAction::ACCESS_DENIED,
                'Failed to decrypt secret: ' . $e->getMessage()
            );
            
            $this->logger->error('Secret decryption failed', [
                'category' => $category,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get secret by full category name (category.name)
     */
    public function getSecretByCategoryName(string $categoryName): ?string
    {
        if (!str_contains($categoryName, '.')) {
            throw new InvalidArgumentException('Category name must be in format "category.name"');
        }

        [$category, $name] = explode('.', $categoryName, 2);
        return $this->getSecret($category, $name);
    }

    /**
     * Rotate a secret with a new value
     */
    public function rotateSecret(
        Secret $secret,
        string $newValue,
        ?string $description = null,
        ?DateTimeImmutable $expiresAt = null,
        ?array $metadata = null
    ): Secret {
        if (empty($newValue)) {
            throw new InvalidArgumentException('New value cannot be empty');
        }

        // Deactivate old version
        $secret->setIsActive(false);
        $this->secretRepository->save($secret);

        // Create new version
        $newVersion = $this->secretRepository->getNextVersion($secret->getCategory(), $secret->getName());
        
        $newSecret = new Secret();
        $newSecret->setCategory($secret->getCategory())
            ->setName($secret->getName())
            ->setVersion($newVersion)
            ->setEncryptedValue($this->encryptionService->encrypt($newValue))
            ->setDescription($description ?? $secret->getDescription())
            ->setExpiresAt($expiresAt)
            ->setMetadata($metadata ?? $secret->getMetadata())
            ->setCreatedBy($this->getCurrentUser())
            ->setUpdatedBy($this->getCurrentUser());

        $this->secretRepository->save($newSecret, true);

        $this->logAction(
            $newSecret,
            SecretAction::ROTATED,
            sprintf('Secret rotated from version %d to %d', $secret->getVersion(), $newVersion)
        );

        $this->logger->info('Secret rotated', [
            'category' => $secret->getCategory(),
            'name' => $secret->getName(),
            'old_version' => $secret->getVersion(),
            'new_version' => $newVersion,
            'user' => $this->getCurrentUser(),
        ]);

        return $newSecret;
    }

    /**
     * Deactivate a secret
     */
    public function deactivateSecret(string $category, string $name): bool
    {
        $secret = $this->secretRepository->findActiveByName($category, $name);
        
        if (!$secret) {
            return false;
        }

        $secret->setIsActive(false)
            ->setUpdatedBy($this->getCurrentUser());
        
        $this->secretRepository->save($secret, true);

        $this->logAction(
            $secret,
            SecretAction::DEACTIVATED,
            sprintf('Secret deactivated: %s.%s', $category, $name)
        );

        return true;
    }

    /**
     * Activate a secret
     */
    public function activateSecret(string $category, string $name): bool
    {
        $secret = $this->secretRepository->createQueryBuilder('s')
            ->andWhere('s.category = :category')
            ->andWhere('s.name = :name')
            ->andWhere('s.isActive = false')
            ->setParameter('category', $category)
            ->setParameter('name', $name)
            ->orderBy('s.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$secret) {
            return false;
        }

        $secret->setIsActive(true)
            ->setUpdatedBy($this->getCurrentUser());
        
        $this->secretRepository->save($secret, true);

        $this->logAction(
            $secret,
            SecretAction::ACTIVATED,
            sprintf('Secret activated: %s.%s', $category, $name)
        );

        return true;
    }

    /**
     * Delete a secret permanently
     */
    public function deleteSecret(string $category, string $name): bool
    {
        $secrets = $this->secretRepository->findVersionsBySecret($category, $name);
        
        if (empty($secrets)) {
            return false;
        }

        foreach ($secrets as $secret) {
            $this->logAction(
                $secret,
                SecretAction::DELETED,
                sprintf('Secret deleted: %s.%s version %d', $category, $name, $secret->getVersion())
            );
            
            $this->secretRepository->remove($secret);
        }

        $this->secretRepository->getEntityManager()->flush();

        $this->logger->warning('Secret deleted', [
            'category' => $category,
            'name' => $name,
            'versions_count' => count($secrets),
            'user' => $this->getCurrentUser(),
        ]);

        return true;
    }

    /**
     * Get all secrets by category
     */
    public function getSecretsByCategory(string $category): array
    {
        return $this->secretRepository->findByCategory($category);
    }

    /**
     * Get all active secrets
     */
    public function getAllActiveSecrets(): array
    {
        return $this->secretRepository->findAllActiveSecrets();
    }

    /**
     * Get secrets that need rotation
     */
    public function getSecretsForRotation(int $daysBeforeExpiry = 30): array
    {
        return $this->secretRepository->findSecretsForRotation($daysBeforeExpiry);
    }

    /**
     * Get expired secrets
     */
    public function getExpiredSecrets(): array
    {
        return $this->secretRepository->findExpiredSecrets();
    }

    /**
     * Generate a new API key for a service
     */
    public function generateApiKey(
        string $category,
        string $name,
        string $prefix = '',
        int $length = 64,
        ?string $description = null,
        ?DateTimeImmutable $expiresAt = null
    ): string {
        $apiKey = $this->encryptionService->generateApiKey($prefix, $length);
        
        $this->storeSecret($category, $name, $apiKey, $description, $expiresAt);
        
        return $apiKey;
    }

    /**
     * Get audit logs for a secret
     */
    public function getSecretAuditLogs(string $category, string $name, int $limit = 50): array
    {
        $secret = $this->secretRepository->findActiveByName($category, $name);
        
        if (!$secret) {
            return [];
        }

        return $this->auditLogRepository->findBySecret($secret, $limit);
    }

    /**
     * Get recent security events
     */
    public function getRecentSecurityEvents(int $days = 7): array
    {
        return $this->auditLogRepository->findSecurityEvents($days);
    }

    /**
     * Get statistics about secrets usage
     */
    public function getSecretsStatistics(): array
    {
        $categoriesStats = $this->secretRepository->getCategoriesStats();
        $actionStats = $this->auditLogRepository->getActionStats();
        $expiredCount = count($this->getExpiredSecrets());
        $rotationCount = count($this->getSecretsForRotation());

        return [
            'categories' => $categoriesStats,
            'actions' => $actionStats,
            'expired_count' => $expiredCount,
            'rotation_needed_count' => $rotationCount,
            'total_active' => array_sum(array_column($categoriesStats, 'active')),
            'total_secrets' => array_sum(array_column($categoriesStats, 'total')),
        ];
    }

    private function validateCategoryAndName(string $category, string $name, string $value): void
    {
        if (empty($category)) {
            throw new InvalidArgumentException('Category cannot be empty');
        }

        if (empty($name)) {
            throw new InvalidArgumentException('Name cannot be empty');
        }

        if (empty($value)) {
            throw new InvalidArgumentException('Value cannot be empty');
        }

        if (strlen($category) > 100) {
            throw new InvalidArgumentException('Category too long (max 100 characters)');
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException('Name too long (max 100 characters)');
        }
    }

    private function logAction(Secret $secret, SecretAction $action, string $details): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        $this->auditLogRepository->logAction(
            $secret,
            $action,
            $this->getCurrentUser(),
            $request?->getClientIp(),
            $request?->headers->get('User-Agent'),
            null,
            $details
        );
    }

    private function logAccessDenied(string $category, string $name, string $reason): void
    {
        $this->logger->warning('Secret access denied', [
            'category' => $category,
            'name' => $name,
            'reason' => $reason,
            'user' => $this->getCurrentUser(),
            'ip' => $this->requestStack->getCurrentRequest()?->getClientIp(),
        ]);
    }

    private function getCurrentUser(): ?string
    {
        $user = $this->security->getUser();
        return $user?->getUserIdentifier();
    }
}