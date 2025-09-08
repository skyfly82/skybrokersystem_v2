<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\SecretCategory;
use App\Service\SecretsManagerService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/secrets', name: 'api_secrets_')]
#[IsGranted('ROLE_ADMIN')]
class SecretsManagementController extends AbstractController
{
    public function __construct(
        private readonly SecretsManagerService $secretsManager
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $category = $request->query->get('category');
        $includeValues = $request->query->getBoolean('include_values', false);

        try {
            if ($category) {
                $secrets = $this->secretsManager->getSecretsByCategory($category);
            } else {
                $secrets = $this->secretsManager->getAllActiveSecrets();
            }

            $data = [];
            foreach ($secrets as $secret) {
                $item = [
                    'id' => $secret->getId()->toString(),
                    'category' => $secret->getCategory(),
                    'name' => $secret->getName(),
                    'version' => $secret->getVersion(),
                    'is_active' => $secret->isActive(),
                    'is_expired' => $secret->isExpired(),
                    'created_at' => $secret->getCreatedAt()->format('c'),
                    'updated_at' => $secret->getUpdatedAt()->format('c'),
                    'expires_at' => $secret->getExpiresAt()?->format('c'),
                    'last_accessed_at' => $secret->getLastAccessedAt()?->format('c'),
                    'description' => $secret->getDescription(),
                    'metadata' => $secret->getMetadata(),
                    'created_by' => $secret->getCreatedBy(),
                    'updated_by' => $secret->getUpdatedBy(),
                ];

                if ($includeValues && $this->isGranted('ROLE_SUPER_ADMIN')) {
                    $item['value'] = $this->secretsManager->getSecret($secret->getCategory(), $secret->getName());
                }

                $data[] = $item;
            }

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{category}/{name}', name: 'get', methods: ['GET'])]
    public function get(string $category, string $name, Request $request): JsonResponse
    {
        $includeValue = $request->query->getBoolean('include_value', false);

        try {
            $secrets = $this->secretsManager->getSecretsByCategory($category);
            $secret = null;
            
            foreach ($secrets as $s) {
                if ($s->getName() === $name && $s->isActive()) {
                    $secret = $s;
                    break;
                }
            }

            if (!$secret) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Secret not found',
                ], 404);
            }

            $data = [
                'id' => $secret->getId()->toString(),
                'category' => $secret->getCategory(),
                'name' => $secret->getName(),
                'version' => $secret->getVersion(),
                'is_active' => $secret->isActive(),
                'is_expired' => $secret->isExpired(),
                'created_at' => $secret->getCreatedAt()->format('c'),
                'updated_at' => $secret->getUpdatedAt()->format('c'),
                'expires_at' => $secret->getExpiresAt()?->format('c'),
                'last_accessed_at' => $secret->getLastAccessedAt()?->format('c'),
                'description' => $secret->getDescription(),
                'metadata' => $secret->getMetadata(),
                'created_by' => $secret->getCreatedBy(),
                'updated_by' => $secret->getUpdatedBy(),
            ];

            if ($includeValue && $this->isGranted('ROLE_SUPER_ADMIN')) {
                $data['value'] = $this->secretsManager->getSecret($category, $name);
            }

            return new JsonResponse([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('', name: 'store', methods: ['POST'])]
    public function store(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $category = $data['category'] ?? null;
            $name = $data['name'] ?? null;
            $value = $data['value'] ?? null;
            $description = $data['description'] ?? null;
            $expiresAt = isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null;
            $metadata = $data['metadata'] ?? null;

            if (!$category || !$name || !$value) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Category, name, and value are required',
                ], 400);
            }

            $secret = $this->secretsManager->storeSecret(
                $category,
                $name,
                $value,
                $description,
                $expiresAt,
                $metadata
            );

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $secret->getId()->toString(),
                    'category' => $secret->getCategory(),
                    'name' => $secret->getName(),
                    'version' => $secret->getVersion(),
                    'created_at' => $secret->getCreatedAt()->format('c'),
                ],
                'message' => 'Secret stored successfully',
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    #[Route('/{category}/{name}/rotate', name: 'rotate', methods: ['POST'])]
    public function rotate(string $category, string $name, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $newValue = $data['value'] ?? null;
            $description = $data['description'] ?? null;
            $expiresAt = isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null;
            $metadata = $data['metadata'] ?? null;

            if (!$newValue) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'New value is required',
                ], 400);
            }

            // Find current secret
            $secrets = $this->secretsManager->getSecretsByCategory($category);
            $currentSecret = null;
            
            foreach ($secrets as $s) {
                if ($s->getName() === $name && $s->isActive()) {
                    $currentSecret = $s;
                    break;
                }
            }

            if (!$currentSecret) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Secret not found',
                ], 404);
            }

            $newSecret = $this->secretsManager->rotateSecret(
                $currentSecret,
                $newValue,
                $description,
                $expiresAt,
                $metadata
            );

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $newSecret->getId()->toString(),
                    'category' => $newSecret->getCategory(),
                    'name' => $newSecret->getName(),
                    'version' => $newSecret->getVersion(),
                    'created_at' => $newSecret->getCreatedAt()->format('c'),
                ],
                'message' => 'Secret rotated successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    #[Route('/{category}/{name}/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(string $category, string $name): JsonResponse
    {
        try {
            $result = $this->secretsManager->deactivateSecret($category, $name);

            if (!$result) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Secret not found',
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Secret deactivated successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{category}/{name}/activate', name: 'activate', methods: ['POST'])]
    public function activate(string $category, string $name): JsonResponse
    {
        try {
            $result = $this->secretsManager->activateSecret($category, $name);

            if (!$result) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Secret not found',
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Secret activated successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{category}/{name}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(string $category, string $name): JsonResponse
    {
        try {
            $result = $this->secretsManager->deleteSecret($category, $name);

            if (!$result) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Secret not found',
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Secret deleted successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/categories', name: 'categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => SecretCategory::getAllWithDetails(),
        ]);
    }

    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->secretsManager->getSecretsStatistics();

            return new JsonResponse([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{category}/{name}/audit', name: 'audit_logs', methods: ['GET'])]
    public function auditLogs(string $category, string $name, Request $request): JsonResponse
    {
        try {
            $limit = $request->query->getInt('limit', 50);
            $logs = $this->secretsManager->getSecretAuditLogs($category, $name, $limit);

            $data = [];
            foreach ($logs as $log) {
                $data[] = [
                    'id' => $log->getId()->toString(),
                    'action' => $log->getAction(),
                    'user_identifier' => $log->getUserIdentifier(),
                    'ip_address' => $log->getIpAddress(),
                    'user_agent' => $log->getUserAgent(),
                    'performed_at' => $log->getPerformedAt()->format('c'),
                    'details' => $log->getDetails(),
                    'metadata' => $log->getMetadata(),
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/security-events', name: 'security_events', methods: ['GET'])]
    public function securityEvents(Request $request): JsonResponse
    {
        try {
            $days = $request->query->getInt('days', 7);
            $events = $this->secretsManager->getRecentSecurityEvents($days);

            $data = [];
            foreach ($events as $event) {
                $secret = $event->getSecret();
                $data[] = [
                    'id' => $event->getId()->toString(),
                    'secret' => [
                        'category' => $secret->getCategory(),
                        'name' => $secret->getName(),
                        'version' => $secret->getVersion(),
                    ],
                    'action' => $event->getAction(),
                    'user_identifier' => $event->getUserIdentifier(),
                    'ip_address' => $event->getIpAddress(),
                    'performed_at' => $event->getPerformedAt()->format('c'),
                    'details' => $event->getDetails(),
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}