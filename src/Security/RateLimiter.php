<?php

namespace App\Security;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

/**
 * Rate Limiter Implementation
 * Implements sliding window rate limiting for API endpoints
 */
class RateLimiter
{
    private AdapterInterface $cache;
    private LoggerInterface $logger;
    private SecurityLogger $securityLogger;
    
    private const DEFAULT_WINDOW = 3600; // 1 hour in seconds
    private const LOGIN_LIMIT = 5; // Max login attempts per window
    private const REGISTRATION_LIMIT = 3; // Max registration attempts per window
    private const API_LIMIT = 1000; // Max API calls per window

    public function __construct(AdapterInterface $cache, LoggerInterface $logger, SecurityLogger $securityLogger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->securityLogger = $securityLogger;
    }

    /**
     * Check if request is rate limited
     */
    public function isRateLimited(Request $request, string $type = 'api'): bool
    {
        $identifier = $this->getIdentifier($request);
        $limit = $this->getLimit($type);
        $window = self::DEFAULT_WINDOW;

        $key = sprintf('rate_limit_%s_%s_%s', $type, $identifier, floor(time() / $window));
        
        try {
            $item = $this->cache->getItem($key);
            $attempts = $item->get() ?? 0;

            if ($attempts >= $limit) {
                $this->securityLogger->logRateLimitExceeded(
                    $identifier,
                    $request->getClientIp() ?? 'unknown',
                    $request->getRequestUri()
                );
                return true;
            }

            // Increment counter
            $item->set($attempts + 1);
            $item->expiresAfter($window);
            $this->cache->save($item);

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Rate limiter error: ' . $e->getMessage());
            // Fail open in case of cache issues
            return false;
        }
    }

    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts(Request $request, string $type = 'api'): int
    {
        $identifier = $this->getIdentifier($request);
        $limit = $this->getLimit($type);
        $window = self::DEFAULT_WINDOW;

        $key = sprintf('rate_limit_%s_%s_%s', $type, $identifier, floor(time() / $window));
        
        try {
            $item = $this->cache->getItem($key);
            $attempts = $item->get() ?? 0;
            return max(0, $limit - $attempts);
        } catch (\Exception $e) {
            $this->logger->error('Rate limiter error: ' . $e->getMessage());
            return $limit; // Return full limit on error
        }
    }

    /**
     * Reset rate limit for identifier
     */
    public function reset(Request $request, string $type = 'api'): void
    {
        $identifier = $this->getIdentifier($request);
        $window = self::DEFAULT_WINDOW;
        $key = sprintf('rate_limit_%s_%s_%s', $type, $identifier, floor(time() / $window));
        
        try {
            $this->cache->deleteItem($key);
        } catch (\Exception $e) {
            $this->logger->error('Rate limiter reset error: ' . $e->getMessage());
        }
    }

    /**
     * Apply progressive delay based on attempts
     */
    public function getDelay(Request $request, string $type = 'login'): int
    {
        $identifier = $this->getIdentifier($request);
        $window = self::DEFAULT_WINDOW;
        $key = sprintf('rate_limit_%s_%s_%s', $type, $identifier, floor(time() / $window));
        
        try {
            $item = $this->cache->getItem($key);
            $attempts = $item->get() ?? 0;
            
            // Progressive delay: 0s, 1s, 2s, 4s, 8s, 16s, 32s...
            return min(32, pow(2, max(0, $attempts - 1)));
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get unique identifier for rate limiting
     */
    private function getIdentifier(Request $request): string
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $userAgent = $request->headers->get('User-Agent', 'unknown');
        
        // Create composite identifier to handle NAT/proxy scenarios
        return hash('sha256', $ip . '|' . $userAgent);
    }

    /**
     * Get rate limit based on type
     */
    private function getLimit(string $type): int
    {
        return match ($type) {
            'login' => self::LOGIN_LIMIT,
            'registration' => self::REGISTRATION_LIMIT,
            'api' => self::API_LIMIT,
            default => self::API_LIMIT
        };
    }
}