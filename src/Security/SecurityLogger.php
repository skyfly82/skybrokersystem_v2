<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security Event Logger
 * Logs security-related events for monitoring and incident response
 */
class SecurityLogger
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function logAuthenticationAttempt(string $email, string $userAgent, string $clientIp, bool $success = false, ?string $reason = null): void
    {
        $context = [
            'email' => $email,
            'ip_address' => $clientIp,
            'user_agent' => $userAgent,
            'success' => $success,
            'timestamp' => new \DateTime(),
            'event_type' => 'authentication_attempt'
        ];

        if (!$success && $reason) {
            $context['failure_reason'] = $reason;
        }

        if ($success) {
            $this->logger->info('Successful authentication attempt', $context);
        } else {
            $this->logger->warning('Failed authentication attempt', $context);
        }
    }

    public function logSuspiciousActivity(string $activity, array $context = []): void
    {
        $baseContext = [
            'event_type' => 'suspicious_activity',
            'activity' => $activity,
            'timestamp' => new \DateTime(),
        ];

        $this->logger->warning('Suspicious activity detected', array_merge($baseContext, $context));
    }

    public function logRateLimitExceeded(string $identifier, string $clientIp, string $endpoint): void
    {
        $this->logger->warning('Rate limit exceeded', [
            'event_type' => 'rate_limit_exceeded',
            'identifier' => $identifier,
            'ip_address' => $clientIp,
            'endpoint' => $endpoint,
            'timestamp' => new \DateTime()
        ]);
    }

    public function logPasswordChange(UserInterface $user, string $clientIp): void
    {
        $this->logger->info('Password changed', [
            'event_type' => 'password_change',
            'user_id' => $user->getUserIdentifier(),
            'ip_address' => $clientIp,
            'timestamp' => new \DateTime()
        ]);
    }

    public function logPrivilegeEscalation(UserInterface $user, string $targetResource, string $clientIp): void
    {
        $this->logger->critical('Privilege escalation attempt', [
            'event_type' => 'privilege_escalation',
            'user_id' => $user->getUserIdentifier(),
            'target_resource' => $targetResource,
            'ip_address' => $clientIp,
            'timestamp' => new \DateTime()
        ]);
    }

    public function logDataAccess(UserInterface $user, string $resource, array $data = []): void
    {
        $this->logger->info('Sensitive data access', [
            'event_type' => 'data_access',
            'user_id' => $user->getUserIdentifier(),
            'resource' => $resource,
            'data_count' => count($data),
            'timestamp' => new \DateTime()
        ]);
    }

    public function logSecurityException(\Throwable $exception, Request $request): void
    {
        $this->logger->error('Security exception occurred', [
            'event_type' => 'security_exception',
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request_uri' => $request->getRequestUri(),
            'method' => $request->getMethod(),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'timestamp' => new \DateTime()
        ]);
    }
}