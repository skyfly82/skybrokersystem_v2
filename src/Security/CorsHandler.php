<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * CORS Handler for secure cross-origin requests
 * Implements OWASP CORS security guidelines
 */
class CorsHandler implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    
    // Define allowed origins (configure per environment)
    private array $allowedOrigins = [
        'https://yourdomain.com',
        'https://www.yourdomain.com',
        'https://app.yourdomain.com',
        'http://185.213.25.106',
    ];
    
    // Allowed headers
    private array $allowedHeaders = [
        'Accept',
        'Accept-Language',
        'Content-Language',
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Origin',
    ];
    
    // Allowed methods
    private array $allowedMethods = [
        'GET',
        'POST', 
        'PUT',
        'DELETE',
        'PATCH',
        'OPTIONS'
    ];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        // Merge additional allowed origins from env (comma-separated)
        $env = $_ENV['CORS_ALLOWED_ORIGINS'] ?? $_SERVER['CORS_ALLOWED_ORIGINS'] ?? '';
        if ($env) {
            $extra = array_filter(array_map('trim', explode(',', $env)));
            $this->allowedOrigins = array_values(array_unique(array_merge($this->allowedOrigins, $extra)));
        }

        // In development, also allow common local hosts by default
        if (($_ENV['APP_ENV'] ?? '') === 'dev') {
            foreach (['http://localhost:3000', 'http://localhost:5173'] as $devOrigin) {
                if (!in_array($devOrigin, $this->allowedOrigins, true)) {
                    $this->allowedOrigins[] = $devOrigin;
                }
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', -9999],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Only handle CORS for API requests
        if (!$this->isApiRequest($request)) {
            return;
        }

        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->createPreflightResponse($request);
            $event->setResponse($response);
            return;
        }

        // Validate origin for actual requests
        $this->validateOrigin($request);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only add CORS headers to API responses
        if (!$this->isApiRequest($request)) {
            return;
        }

        $this->addCorsHeaders($response, $request);
    }

    private function createPreflightResponse(Request $request): Response
    {
        $response = new Response();
        $response->setStatusCode(200);
        
        $origin = $request->headers->get('Origin');
        
        if ($this->isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
            $response->headers->set('Access-Control-Max-Age', '3600');
            $response->headers->set('Vary', 'Origin');
        } else {
            // Log suspicious origin for monitoring
            $this->logger->warning('CORS preflight request from disallowed origin', [
                'origin' => $origin,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);
        }

        return $response;
    }

    private function addCorsHeaders(Response $response, Request $request): void
    {
        $origin = $request->headers->get('Origin');
        
        if (!$origin) {
            return; // Same-origin request
        }

        if ($this->isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'X-Total-Count, X-RateLimit-Remaining, X-RateLimit-Limit');
            $response->headers->set('Vary', 'Origin');
        } else {
            $this->logger->warning('CORS request from disallowed origin', [
                'origin' => $origin,
                'ip' => $request->getClientIp(),
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri()
            ]);
        }
    }

    private function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }

        // In development, allow localhost
        if ($_ENV['APP_ENV'] === 'dev') {
            if (preg_match('/^https?:\/\/localhost(:\d+)?$/', $origin)) {
                return true;
            }
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    private function validateOrigin(Request $request): void
    {
        $origin = $request->headers->get('Origin');
        
        if ($origin && !$this->isOriginAllowed($origin)) {
            $this->logger->warning('Request from disallowed origin blocked', [
                'origin' => $origin,
                'ip' => $request->getClientIp(),
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);
        }
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/');
    }
}
