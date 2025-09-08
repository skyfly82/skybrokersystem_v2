<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Psr\Log\LoggerInterface;

class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        // Log failed authentication attempt with security details
        $this->logger->warning('Authentication failure', [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'attempted_email' => $this->extractEmailFromRequest($request),
            'exception' => get_class($exception),
            'timestamp' => new \DateTime(),
        ]);

        // Always return the same generic error message to prevent username enumeration
        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => 'Invalid credentials'
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function extractEmailFromRequest(Request $request): ?string
    {
        $data = json_decode($request->getContent(), true);
        return $data['email'] ?? $data['username'] ?? null;
    }
}