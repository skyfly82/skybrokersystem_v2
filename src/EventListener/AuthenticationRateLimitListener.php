<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Psr\Log\LoggerInterface;

// TEMPORARILY DISABLED FOR TESTING
// #[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
class AuthenticationRateLimitListener
{
    public function __construct(
        private RateLimiterFactoryInterface $authenticationLimiter,
        private RateLimiterFactoryInterface $loginFailuresLimiter,
        private LoggerInterface $logger
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Only apply to authentication endpoints
        $path = $request->getPathInfo();
        if (!preg_match('#^/api/v1/(customer|system)/login$#', $path)) {
            return;
        }

        // Skip non-POST requests
        if ($request->getMethod() !== 'POST') {
            return;
        }

        $clientIp = $request->getClientIp();
        
        // Create rate limiter key based on IP address
        $limiterKey = 'auth_' . $clientIp;
        
        // Check general authentication rate limit
        $limiter = $this->authenticationLimiter->create($limiterKey);
        if (!$limiter->consume()->isAccepted()) {
            $this->logger->warning('Authentication rate limit exceeded', [
                'ip' => $clientIp,
                'path' => $path,
                'user_agent' => $request->headers->get('User-Agent')
            ]);
            
            $response = new JsonResponse([
                'error' => 'Too many authentication attempts. Please try again later.',
                'retry_after' => $limiter->reserve()->getRetryAfter()->getTimestamp()
            ], Response::HTTP_TOO_MANY_REQUESTS);
            
            $response->headers->set('Retry-After', (string) $limiter->reserve()->getRetryAfter()->getTimestamp());
            $event->setResponse($response);
            return;
        }
    }
}
