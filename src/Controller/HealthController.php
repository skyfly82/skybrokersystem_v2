<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => new \DateTime(),
            'service' => 'SkyBrokerSystem v2',
            'version' => '2.0.0-dev'
        ]);
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to SkyBrokerSystem v2 API',
            'version' => '2.0.0-dev',
            'docs' => '/api/docs',
            'health' => '/health'
        ]);
    }
}