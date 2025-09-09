<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/test', name: 'api_test_')]
final class TestController extends AbstractController
{
    #[Route('/raise-exception', name: 'raise_exception', methods: ['GET'])]
    public function raiseException(): JsonResponse
    {
        throw new \RuntimeException('Sentry test exception from /api/test/raise-exception');
    }
}

