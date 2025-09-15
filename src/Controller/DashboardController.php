<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SystemUser;
use App\Entity\CustomerUser;
use App\Service\Dashboard\DashboardDataProviderInterface;
use App\Service\Dashboard\SystemDashboardDataProvider;
use App\Service\Dashboard\CustomerDashboardDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Psr\Log\LoggerInterface;

/**
 * Dashboard Controller handling both system and customer dashboards
 * with role-based access control and proper authentication
 */
#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly SystemDashboardDataProvider $systemDashboardProvider,
        private readonly CustomerDashboardDataProvider $customerDashboardProvider,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Main dashboard route with type parameter for role-based routing
     */
    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $type = $request->query->get('type', 'customer');
        $user = $this->getUser();

        // Validate user authentication
        if (!$user) {
            $this->logger->warning('Unauthenticated dashboard access attempt', [
                'type' => $type,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);

            return $this->redirectToRoute('app_login_web');
        }

        try {
            // Route based on dashboard type and user permissions
            return match ($type) {
                'system' => $this->handleSystemDashboard($user, $request),
                'customer' => $this->handleCustomerDashboard($user, $request),
                default => throw new AccessDeniedException('Invalid dashboard type')
            };

        } catch (AccessDeniedException $e) {
            $this->logger->warning('Dashboard access denied', [
                'user_id' => $user->getId(),
                'user_type' => get_class($user),
                'requested_type' => $type,
                'error' => $e->getMessage()
            ]);

            return $this->render('error/403.html.twig', [
                'message' => 'You do not have permission to access this dashboard type.'
            ], new Response('', 403));

        } catch (\Exception $e) {
            $this->logger->error('Dashboard error', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->render('error/500.html.twig', [
                'message' => 'An error occurred while loading the dashboard.'
            ], new Response('', 500));
        }
    }

    /**
     * API endpoint for dashboard data with proper authentication
     */
    #[Route('/api/data', name: 'api_dashboard_data', methods: ['GET'])]
    public function getDashboardData(Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'customer');
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        try {
            $dataProvider = $this->getDashboardDataProvider($type, $user);
            $dashboardData = $dataProvider->getDashboardData();

            $this->logger->info('Dashboard data retrieved', [
                'user_id' => $user->getId(),
                'user_type' => get_class($user),
                'dashboard_type' => $type,
                'data_points' => count($dashboardData)
            ]);

            return $this->json([
                'success' => true,
                'data' => $dashboardData,
                'user_info' => [
                    'id' => $user->getId(),
                    'name' => $user->getFullName(),
                    'email' => $user->getEmail(),
                    'type' => $user instanceof SystemUser ? 'system' : 'customer'
                ],
                'dashboard_type' => $type,
                'timestamp' => time()
            ]);

        } catch (AccessDeniedException $e) {
            return $this->json([
                'success' => false,
                'error' => 'Access denied: ' . $e->getMessage()
            ], 403);

        } catch (\Exception $e) {
            $this->logger->error('Dashboard API error', [
                'user_id' => $user->getId(),
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve dashboard data'
            ], 500);
        }
    }

    /**
     * API endpoint for real-time dashboard updates
     */
    #[Route('/api/realtime', name: 'api_dashboard_realtime', methods: ['GET'])]
    public function getRealtimeUpdates(Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'customer');
        $lastUpdate = (int) $request->query->get('last_update', 0);
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        try {
            $dataProvider = $this->getDashboardDataProvider($type, $user);
            $realtimeData = $dataProvider->getRealtimeUpdates($lastUpdate);

            return $this->json([
                'success' => true,
                'data' => $realtimeData,
                'timestamp' => time(),
                'has_updates' => !empty($realtimeData)
            ]);

        } catch (AccessDeniedException $e) {
            return $this->json([
                'success' => false,
                'error' => 'Access denied: ' . $e->getMessage()
            ], 403);

        } catch (\Exception $e) {
            $this->logger->error('Realtime dashboard API error', [
                'user_id' => $user->getId(),
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve realtime updates'
            ], 500);
        }
    }

    /**
     * Handle system dashboard access with proper role validation
     */
    private function handleSystemDashboard($user, Request $request): Response
    {
        if (!$user instanceof SystemUser) {
            throw new AccessDeniedException('System dashboard requires system user account');
        }

        if (!$this->isGranted('ROLE_SYSTEM_USER')) {
            throw new AccessDeniedException('Insufficient permissions for system dashboard');
        }

        $this->logger->info('System dashboard accessed', [
            'user_id' => $user->getId(),
            'department' => $user->getDepartment(),
            'ip' => $request->getClientIp()
        ]);

        return $this->render('dashboard/system.html.twig', [
            'user' => $user,
            'dashboard_type' => 'system',
            'user_permissions' => $this->getUserPermissions($user)
        ]);
    }

    /**
     * Handle customer dashboard access with proper validation
     */
    private function handleCustomerDashboard($user, Request $request): Response
    {
        if ($user instanceof SystemUser) {
            // Allow system users to access customer dashboard for support purposes
            if (!$user->canManageCustomers()) {
                throw new AccessDeniedException('Insufficient permissions to access customer dashboard');
            }

            $this->logger->info('System user accessing customer dashboard', [
                'user_id' => $user->getId(),
                'department' => $user->getDepartment(),
                'ip' => $request->getClientIp()
            ]);

            return $this->render('dashboard/customer.html.twig', [
                'user' => $user,
                'dashboard_type' => 'customer',
                'is_system_user' => true,
                'user_permissions' => $this->getUserPermissions($user)
            ]);
        }

        if (!$user instanceof CustomerUser) {
            throw new AccessDeniedException('Invalid user type for customer dashboard');
        }

        if (!$this->isGranted('ROLE_CUSTOMER_USER')) {
            throw new AccessDeniedException('Insufficient permissions for customer dashboard');
        }

        $this->logger->info('Customer dashboard accessed', [
            'user_id' => $user->getId(),
            'customer_id' => $user->getCustomer()?->getId(),
            'customer_role' => $user->getCustomerRole(),
            'ip' => $request->getClientIp()
        ]);

        return $this->render('dashboard/customer.html.twig', [
            'user' => $user,
            'customer' => $user->getCustomer(),
            'dashboard_type' => 'customer',
            'is_system_user' => false,
            'user_permissions' => $this->getUserPermissions($user)
        ]);
    }

    /**
     * Get appropriate dashboard data provider based on type and user
     */
    private function getDashboardDataProvider(string $type, $user): DashboardDataProviderInterface
    {
        return match ($type) {
            'system' => $this->validateSystemDashboardAccess($user, $this->systemDashboardProvider),
            'customer' => $this->validateCustomerDashboardAccess($user, $this->customerDashboardProvider),
            default => throw new AccessDeniedException('Invalid dashboard type')
        };
    }

    /**
     * Validate system dashboard access and return provider
     */
    private function validateSystemDashboardAccess($user, SystemDashboardDataProvider $provider): DashboardDataProviderInterface
    {
        if (!$user instanceof SystemUser || !$this->isGranted('ROLE_SYSTEM_USER')) {
            throw new AccessDeniedException('System dashboard access denied');
        }

        return $provider;
    }

    /**
     * Validate customer dashboard access and return provider
     */
    private function validateCustomerDashboardAccess($user, CustomerDashboardDataProvider $provider): DashboardDataProviderInterface
    {
        if ($user instanceof SystemUser) {
            if (!$user->canManageCustomers()) {
                throw new AccessDeniedException('Insufficient system permissions for customer dashboard');
            }
        } elseif (!$user instanceof CustomerUser || !$this->isGranted('ROLE_CUSTOMER_USER')) {
            throw new AccessDeniedException('Customer dashboard access denied');
        }

        return $provider;
    }

    /**
     * Get user permissions array for frontend
     */
    private function getUserPermissions($user): array
    {
        $permissions = [
            'can_view_reports' => false,
            'can_manage_customers' => false,
            'can_manage_users' => false,
            'can_access_admin' => false,
            'can_view_billing' => false,
            'can_manage_orders' => false
        ];

        if ($user instanceof SystemUser) {
            $permissions['can_view_reports'] = $user->canViewReports();
            $permissions['can_manage_customers'] = $user->canManageCustomers();
            $permissions['can_manage_users'] = $user->isAdmin();
            $permissions['can_access_admin'] = $user->isAdmin();
            $permissions['can_view_billing'] = true;
            $permissions['can_manage_orders'] = $user->canManageCustomers();
        } elseif ($user instanceof CustomerUser) {
            $permissions['can_manage_users'] = $user->canManageUsers();
            $permissions['can_view_billing'] = true;
            $permissions['can_manage_orders'] = $user->isManager();
        }

        return $permissions;
    }
}