<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

/**
 * Security Middleware for HTTP headers and request validation
 * Implements OWASP security headers and request filtering
 */
class SecurityMiddleware implements EventSubscriberInterface
{
    private SecurityLogger $securityLogger;
    private RateLimiter $rateLimiter;
    private InputValidator $inputValidator;
    private LoggerInterface $logger;

    public function __construct(
        SecurityLogger $securityLogger,
        RateLimiter $rateLimiter,
        InputValidator $inputValidator,
        LoggerInterface $logger
    ) {
        $this->securityLogger = $securityLogger;
        $this->rateLimiter = $rateLimiter;
        $this->inputValidator = $inputValidator;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
            KernelEvents::RESPONSE => ['onKernelResponse', -1000],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip security checks for dev environment assets
        if ($this->isDevAsset($request)) {
            return;
        }

        // Validate request method
        if (!$this->isAllowedMethod($request)) {
            $this->securityLogger->logSuspiciousActivity(
                'Invalid HTTP method',
                ['method' => $request->getMethod(), 'uri' => $request->getRequestUri()]
            );
            $event->setResponse(new Response('Method Not Allowed', 405));
            return;
        }

        // Check for suspicious headers
        $this->validateHeaders($request);

        // Rate limiting for sensitive endpoints
        if ($this->isSensitiveEndpoint($request)) {
            $limitType = $this->getLimitType($request);
            
            if ($this->rateLimiter->isRateLimited($request, $limitType)) {
                $delay = $this->rateLimiter->getDelay($request, $limitType);
                
                $response = new Response('Too Many Requests', 429);
                $response->headers->set('Retry-After', (string)$delay);
                $response->headers->set('X-RateLimit-Limit', (string)$this->getRateLimitForType($limitType));
                $response->headers->set('X-RateLimit-Remaining', '0');
                
                $event->setResponse($response);
                return;
            }
        }

        // Validate request content for potential attacks
        $this->validateRequestContent($request);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // Add security headers
        $this->addSecurityHeaders($response, $request);

        // Add rate limit headers for API endpoints
        if ($this->isApiEndpoint($request)) {
            $limitType = $this->getLimitType($request);
            $remaining = $this->rateLimiter->getRemainingAttempts($request, $limitType);
            
            $response->headers->set('X-RateLimit-Limit', (string)$this->getRateLimitForType($limitType));
            $response->headers->set('X-RateLimit-Remaining', (string)$remaining);
        }
    }

    private function addSecurityHeaders(Response $response, Request $request): void
    {
        // Content Security Policy
        $csp = implode('; ', [
            "default-src 'self'",
            // Allow importmap polyfill (for browsers bez wsparcia importmap)
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://ga.jspm.io",
            // Allow Google Fonts CSS
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            // Allow Google Fonts files from gstatic; keep generic https fallback
            "font-src 'self' https: https://fonts.gstatic.com",
            "connect-src 'self' https:",
            "media-src 'self'",
            "object-src 'none'",
            "child-src 'none'",
            "frame-src 'none'",
            "worker-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "manifest-src 'self'"
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Strict Transport Security (HSTS)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // X-Frame-Options
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (Feature Policy)
        $permissionsPolicy = implode(', ', [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'accelerometer=()',
            'gyroscope=()'
        ]);
        $response->headers->set('Permissions-Policy', $permissionsPolicy);

        // CORS headers are handled by CorsHandler. Avoid duplicating here.

        // Remove server information
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
    }

    private function validateHeaders(Request $request): void
    {
        $suspiciousHeaders = [
            'X-Forwarded-For' => '/[;<>"\']/',
            'User-Agent' => '/(<script|javascript:|data:|vbscript:)/i',
            'Referer' => '/(<script|javascript:|data:|vbscript:)/i',
        ];

        foreach ($suspiciousHeaders as $header => $pattern) {
            $value = $request->headers->get($header);
            if ($value && preg_match($pattern, $value)) {
                $this->securityLogger->logSuspiciousActivity(
                    'Suspicious header detected',
                    ['header' => $header, 'value' => $value, 'ip' => $request->getClientIp()]
                );
            }
        }
    }

    private function validateRequestContent(Request $request): void
    {
        $content = $request->getContent();
        
        if (empty($content)) {
            return;
        }

        // Check for potential SQL injection
        if ($this->inputValidator->detectSqlInjection($content)) {
            $this->securityLogger->logSuspiciousActivity(
                'Potential SQL injection detected',
                ['uri' => $request->getRequestUri(), 'ip' => $request->getClientIp()]
            );
        }

        // Check for XSS patterns
        $xssPatterns = [
            '/<script[\s\S]*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<embed/i',
            '/<object/i'
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->securityLogger->logSuspiciousActivity(
                    'Potential XSS attack detected',
                    ['uri' => $request->getRequestUri(), 'ip' => $request->getClientIp()]
                );
                break;
            }
        }
    }

    private function isDevAsset(Request $request): bool
    {
        $path = $request->getPathInfo();
        return str_starts_with($path, '/_') || 
               str_ends_with($path, '.css') || 
               str_ends_with($path, '.js') || 
               str_ends_with($path, '.png') || 
               str_ends_with($path, '.jpg') || 
               str_ends_with($path, '.gif');
    }

    private function isAllowedMethod(Request $request): bool
    {
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        return in_array($request->getMethod(), $allowedMethods, true);
    }

    private function isSensitiveEndpoint(Request $request): bool
    {
        $path = $request->getPathInfo();
        return str_contains($path, '/login') || 
               str_contains($path, '/register') || 
               str_contains($path, '/registration');
    }

    private function isApiEndpoint(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/');
    }

    private function getLimitType(Request $request): string
    {
        $path = $request->getPathInfo();
        
        if (str_contains($path, '/login')) {
            return 'login';
        }
        
        if (str_contains($path, '/register') || str_contains($path, '/registration')) {
            return 'registration';
        }
        
        return 'api';
    }

    private function getRateLimitForType(string $type): int
    {
        return match ($type) {
            'login' => 5,
            'registration' => 3,
            'api' => 1000,
            default => 1000
        };
    }
}
