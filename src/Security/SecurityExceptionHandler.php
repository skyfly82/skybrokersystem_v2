<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

/**
 * Security Exception Handler
 * Prevents information leakage through error messages
 * Implements OWASP Error Handling guidelines
 */
class SecurityExceptionHandler implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    private SecurityLogger $securityLogger;
    private string $environment;

    public function __construct(
        LoggerInterface $logger,
        SecurityLogger $securityLogger,
        string $environment = 'prod'
    ) {
        $this->logger = $logger;
        $this->securityLogger = $securityLogger;
        $this->environment = $environment;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 100],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Log the exception for security monitoring
        $this->securityLogger->logSecurityException($exception, $request);

        // Only handle API requests
        if (!$this->isApiRequest($request)) {
            return;
        }

        $response = $this->createSecureResponse($exception, $request);
        $event->setResponse($response);
    }

    private function createSecureResponse(\Throwable $exception, Request $request): JsonResponse
    {
        $statusCode = $this->getStatusCode($exception);
        $errorData = $this->buildErrorResponse($exception, $statusCode, $request);

        $response = new JsonResponse($errorData, $statusCode);

        // Add security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        
        return $response;
    }

    private function buildErrorResponse(\Throwable $exception, int $statusCode, Request $request): array
    {
        $isDevelopment = $this->environment === 'dev';
        
        // Base error structure
        $errorData = [
            'error' => true,
            'message' => $this->getSafeErrorMessage($statusCode),
            'code' => $statusCode,
            'timestamp' => date('c'),
            'path' => $request->getRequestUri(),
        ];

        // Add request ID for tracking (in production, use proper request ID)
        $errorData['request_id'] = uniqid('err_', true);

        // In development, include more details
        if ($isDevelopment) {
            $errorData['debug'] = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->filterStackTrace($exception->getTrace())
            ];
        }

        // Log detailed error for internal monitoring
        $this->logDetailedError($exception, $request, $errorData['request_id']);

        return $errorData;
    }

    private function getSafeErrorMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request - The request could not be processed',
            401 => 'Unauthorized - Authentication required',
            403 => 'Forbidden - Access denied',
            404 => 'Not Found - The requested resource was not found',
            405 => 'Method Not Allowed - HTTP method not supported',
            409 => 'Conflict - The request conflicts with current state',
            422 => 'Unprocessable Entity - Validation failed',
            429 => 'Too Many Requests - Rate limit exceeded',
            500 => 'Internal Server Error - An unexpected error occurred',
            502 => 'Bad Gateway - Server communication error',
            503 => 'Service Unavailable - Service temporarily unavailable',
            default => 'An error occurred while processing your request'
        };
    }

    private function getStatusCode(\Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        // Map specific exception types to HTTP status codes
        $exceptionClass = get_class($exception);
        
        return match (true) {
            str_contains($exceptionClass, 'NotFound') => 404,
            str_contains($exceptionClass, 'Access') && str_contains($exceptionClass, 'Denied') => 403,
            str_contains($exceptionClass, 'Authentication') => 401,
            str_contains($exceptionClass, 'Validation') => 422,
            str_contains($exceptionClass, 'InvalidArgument') => 400,
            str_contains($exceptionClass, 'Database') || str_contains($exceptionClass, 'Connection') => 503,
            default => 500
        };
    }

    private function filterStackTrace(array $trace): array
    {
        $filtered = [];
        
        foreach (array_slice($trace, 0, 10) as $entry) { // Limit to 10 entries
            $filtered[] = [
                'file' => $entry['file'] ?? 'unknown',
                'line' => $entry['line'] ?? 0,
                'function' => $entry['function'] ?? 'unknown',
                'class' => $entry['class'] ?? null,
            ];
        }
        
        return $filtered;
    }

    private function logDetailedError(\Throwable $exception, Request $request, string $requestId): void
    {
        $context = [
            'request_id' => $requestId,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
        ];

        // Add request body for POST/PUT requests (sanitized)
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            $content = $request->getContent();
            if ($content) {
                $context['request_body'] = $this->sanitizeRequestBody($content);
            }
        }

        $this->logger->error('API Exception: ' . $exception->getMessage(), $context);
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
        $sanitized = [];
        
        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), $sensitiveHeaders)) {
                $sanitized[$name] = ['[REDACTED]'];
            } else {
                $sanitized[$name] = $values;
            }
        }
        
        return $sanitized;
    }

    private function sanitizeRequestBody(string $content): string
    {
        if (strlen($content) > 1000) {
            $content = substr($content, 0, 1000) . '... [truncated]';
        }

        // Remove potential sensitive data
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'credential'];
        
        foreach ($sensitiveFields as $field) {
            $content = preg_replace(
                '/("?' . $field . '"?\s*:\s*")[^"]*(")/i',
                '${1}[REDACTED]${2}',
                $content
            );
        }
        
        return $content;
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getRequestUri(), '/api/') || 
               $request->headers->get('Accept') === 'application/json' ||
               $request->headers->get('Content-Type') === 'application/json';
    }
}