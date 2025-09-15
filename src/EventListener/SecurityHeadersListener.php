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
            // Strict CSP for API responses (no scripts/styles needed)
            $headers->set('Content-Security-Policy', "default-src 'none'; script-src 'none'; object-src 'none'; style-src 'none'; img-src 'none'; media-src 'none'; frame-src 'none'; font-src 'none'; connect-src 'self'");
        } else {
            // Web pages: align with ImportMap and Google Fonts usage
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://ga.jspm.io https://cdn.tailwindcss.com https://unpkg.com",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "img-src 'self' data: https:",
                "font-src 'self' data: https: https://fonts.gstatic.com",
                "connect-src 'self' https:",
                "object-src 'none'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'"
            ]));
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
