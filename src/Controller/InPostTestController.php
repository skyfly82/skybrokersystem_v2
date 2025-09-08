<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InPostApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/inpost', name: 'inpost_')]
class InPostTestController extends AbstractController
{
    public function __construct(
        private readonly InPostApiClient $inpostClient
    ) {
    }

    #[Route('/test', name: 'test', methods: ['GET'])]
    public function test(): JsonResponse
    {
        try {
            $config = $this->inpostClient->getConfig();
            $connectionTest = $this->inpostClient->testConnection();
            
            return $this->json([
                'status' => 'success',
                'config' => $config,
                'connection_test' => $connectionTest,
                'message' => $connectionTest ? 'InPost API connection successful' : 'InPost API connection failed'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'InPost API test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/organization', name: 'organization', methods: ['GET'])]
    public function getOrganization(): JsonResponse
    {
        try {
            $organization = $this->inpostClient->getOrganization();
            
            return $this->json([
                'status' => 'success',
                'data' => $organization
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Failed to fetch organization: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/points', name: 'points', methods: ['GET'])]
    public function getParcelLockers(): JsonResponse
    {
        try {
            $points = $this->inpostClient->getParcelLockers([
                'page' => 1,
                'per_page' => 10,
                'type' => 'parcel_locker'
            ]);
            
            return $this->json([
                'status' => 'success',
                'data' => $points
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Failed to fetch parcel lockers: ' . $e->getMessage()
            ], 500);
        }
    }
}