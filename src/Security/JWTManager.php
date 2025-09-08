<?php

namespace App\Security;

use App\Entity\CustomerUser;
use App\Entity\SystemUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class JWTManager
{
    private JWTTokenManagerInterface $jwtManager;
    private EntityManagerInterface $entityManager;
    private SecurityLogger $securityLogger;
    private LoggerInterface $logger;
    
    // Token blacklist for logout/security invalidation
    private array $blacklistedTokens = [];

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        EntityManagerInterface $entityManager,
        SecurityLogger $securityLogger,
        LoggerInterface $logger
    ) {
        $this->jwtManager = $jwtManager;
        $this->entityManager = $entityManager;
        $this->securityLogger = $securityLogger;
        $this->logger = $logger;
    }

    public function createToken(UserInterface $user): string
    {
        $payload = $this->buildTokenPayload($user);
        return $this->jwtManager->createFromPayload($user, $payload);
    }

    public function parseToken(string $token): ?array
    {
        try {
            // Check if token is blacklisted
            if ($this->isTokenBlacklisted($token)) {
                $this->logger->warning('Attempted use of blacklisted JWT token');
                return null;
            }

            $payload = $this->jwtManager->parse($token);
            
            // Additional security validations
            if (!$this->validateTokenStructure($payload)) {
                $this->securityLogger->logSuspiciousActivity(
                    'Invalid JWT token structure detected',
                    ['token_payload' => array_keys($payload)]
                );
                return null;
            }

            // Validate token hasn't expired with grace period
            $currentTime = time();
            if (isset($payload['exp']) && $payload['exp'] < $currentTime - 60) { // 60 second grace period
                $this->logger->info('Expired JWT token rejected');
                return null;
            }

            // Validate user still exists and is active
            if (!$this->validateUserStatus($payload)) {
                $this->securityLogger->logSuspiciousActivity(
                    'JWT token for inactive/deleted user used',
                    ['email' => $payload['email'] ?? 'unknown']
                );
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            $this->logger->error('JWT parsing error: ' . $e->getMessage());
            return null;
        }
    }

    private function buildTokenPayload(UserInterface $user): array
    {
        $currentTime = time();
        $payload = [
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'iat' => $currentTime,
            'exp' => $currentTime + 3600, // 1 hour
            'jti' => bin2hex(random_bytes(16)), // JWT ID for tracking
            'iss' => 'skybrokersystem', // Issuer
            'aud' => 'skybrokersystem-api', // Audience
        ];

        if ($user instanceof CustomerUser) {
            $payload['user_type'] = 'customer';
            $payload['customer_id'] = $user->getCustomer()?->getId();
            $payload['company_role'] = $user->getCustomerRole();
            $payload['status'] = $user->getStatus();
            $payload['user_id'] = $user->getId();
        } elseif ($user instanceof SystemUser) {
            $payload['user_type'] = 'system';
            $payload['department'] = $user->getDepartment();
            $payload['position'] = $user->getPosition();
            $payload['status'] = $user->getStatus();
            $payload['user_id'] = $user->getId();
        }

        return $payload;
    }

    public function refreshToken(string $token): ?string
    {
        $payload = $this->parseToken($token);
        if (!$payload) {
            return null;
        }

        // Check if refresh is needed (within 15 minutes of expiry)
        $currentTime = time();
        $expiryTime = $payload['exp'] ?? 0;
        
        if ($expiryTime - $currentTime < 900) {
            // Blacklist old token
            $this->blacklistToken($token);
            
            // Create new token with updated timestamps and new JTI
            $payload['iat'] = $currentTime;
            $payload['exp'] = $currentTime + 3600;
            $payload['jti'] = bin2hex(random_bytes(16));
            
            try {
                // Get fresh user data to ensure it's still valid
                $userType = $payload['user_type'];
                $email = $payload['email'];
                
                if ($userType === 'customer') {
                    $user = $this->entityManager->getRepository(CustomerUser::class)
                        ->findOneBy(['email' => $email]);
                } else {
                    $user = $this->entityManager->getRepository(SystemUser::class)
                        ->findOneBy(['email' => $email]);
                }

                if (!$user || $user->getStatus() !== 'active') {
                    return null;
                }

                return $this->jwtManager->createFromPayload($user, $payload);
            } catch (\Exception $e) {
                $this->logger->error('JWT refresh error: ' . $e->getMessage());
                return null;
            }
        }

        return $token; // Token still valid for more than 15 minutes
    }

    public function validateTokenStructure(array $payload): bool
    {
        $requiredFields = ['email', 'roles', 'user_type', 'iat', 'exp', 'jti', 'iss', 'aud'];
        
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                return false;
            }
        }

        // Validate issuer and audience
        if ($payload['iss'] !== 'skybrokersystem' || $payload['aud'] !== 'skybrokersystem-api') {
            return false;
        }

        // Validate user type specific fields
        if ($payload['user_type'] === 'customer') {
            return isset($payload['company_role'], $payload['status'], $payload['user_id']);
        } elseif ($payload['user_type'] === 'system') {
            return isset($payload['department'], $payload['status'], $payload['user_id']);
        }

        return false;
    }

    private function validateUserStatus(array $payload): bool
    {
        try {
            $userType = $payload['user_type'];
            $email = $payload['email'];
            
            if ($userType === 'customer') {
                $user = $this->entityManager->getRepository(CustomerUser::class)
                    ->findOneBy(['email' => $email]);
            } else {
                $user = $this->entityManager->getRepository(SystemUser::class)
                    ->findOneBy(['email' => $email]);
            }

            return $user && $user->getStatus() === 'active';
        } catch (\Exception $e) {
            $this->logger->error('User validation error: ' . $e->getMessage());
            return false;
        }
    }

    public function blacklistToken(string $token): void
    {
        try {
            $payload = $this->jwtManager->parse($token);
            $jti = $payload['jti'] ?? null;
            
            if ($jti) {
                $this->blacklistedTokens[$jti] = time();
                // In production, store this in Redis or database
                // For now, we'll use in-memory storage (limited scope)
            }
        } catch (\Exception $e) {
            $this->logger->error('Token blacklist error: ' . $e->getMessage());
        }
    }

    private function isTokenBlacklisted(string $token): bool
    {
        try {
            $payload = $this->jwtManager->parse($token);
            $jti = $payload['jti'] ?? null;
            
            if ($jti && isset($this->blacklistedTokens[$jti])) {
                return true;
            }
        } catch (\Exception $e) {
            // If we can't parse, assume not blacklisted
            return false;
        }
        
        return false;
    }

    public function invalidateAllUserTokens(UserInterface $user): void
    {
        // In production, this would query a token storage to invalidate all tokens for user
        // For now, log the event
        $this->securityLogger->logSuspiciousActivity(
            'All user tokens invalidated',
            ['user_email' => $user->getUserIdentifier()]
        );
    }
}