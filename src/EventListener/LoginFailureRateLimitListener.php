<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Psr\Log\LoggerInterface;

// Disabled - requires symfony/rate-limiter component
// #[AsEventListener(event: LoginFailureEvent::class)]
class LoginFailureRateLimitListener
{
    public function __construct(
        private RateLimiterFactoryInterface $loginFailuresLimiter,
        private LoggerInterface $logger
    ) {}

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $clientIp = $request->getClientIp();
        
        // Create rate limiter key based on IP address
        $limiterKey = 'login_failures_' . $clientIp;
        
        // Consume from the login failures rate limiter
        $limiter = $this->loginFailuresLimiter->create($limiterKey);
        $limiter->consume();
        
        // Check if we've exceeded the limit
        if (!$limiter->consume()->isAccepted()) {
            $this->logger->warning('Login failure rate limit exceeded', [
                'ip' => $clientIp,
                'path' => $request->getPathInfo(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);
            
            // Block further authentication attempts from this IP
            $response = new JsonResponse([
                'error' => 'Too many failed login attempts. Account temporarily locked.',
                'retry_after' => $limiter->reserve()->getRetryAfter()->getTimestamp()
            ], Response::HTTP_TOO_MANY_REQUESTS);
            
            $response->headers->set('Retry-After', (string) $limiter->reserve()->getRetryAfter()->getTimestamp());
            $event->setResponse($response);
        }
    }
}
