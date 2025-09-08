<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
class SecurityHeadersListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        // Security headers to prevent various attacks
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-XSS-Protection', '1; mode=block');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Content Security Policy - strict for API endpoints
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            $headers->set('Content-Security-Policy', "default-src 'none'; script-src 'none'; object-src 'none'; style-src 'none'; img-src 'none'; media-src 'none'; frame-src 'none'; font-src 'none'; connect-src 'self'");
        } else {
            // More permissive CSP for web pages
            $headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'");
        }
        
        // HSTS - only in production
        if ($_ENV['APP_ENV'] === 'prod') {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        // Remove server information
        $headers->remove('Server');
        $headers->remove('X-Powered-By');
        
        // Set secure cookie settings
        if ($request->isSecure()) {
            $headers->set('Set-Cookie', 'secure; samesite=strict');
        }
    }
}