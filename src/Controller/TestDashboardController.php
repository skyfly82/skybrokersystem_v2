<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Temporary controller for testing dashboard without authentication
 */
class TestDashboardController extends AbstractController
{
    #[Route('/test-dashboard', name: 'test_dashboard', methods: ['GET'])]
    public function testDashboard(Request $request): Response
    {
        $type = $request->query->get('type', 'customer');
        
        // Mock user data for testing
        $mockUser = (object) [
            'id' => 1,
            'fullName' => 'Test User',
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'test@example.com'
        ];
        
        // Render appropriate dashboard template
        if ($type === 'system') {
            return $this->render('dashboard/system.html.twig', [
                'user' => $mockUser,
                'dashboard_type' => 'system',
                'user_permissions' => [
                    'can_view_reports' => true,
                    'can_manage_customers' => true,
                    'can_manage_users' => true,
                    'can_access_admin' => true,
                    'can_view_billing' => true,
                    'can_manage_orders' => true
                ]
            ]);
        } else {
            return $this->render('dashboard/customer.html.twig', [
                'user' => $mockUser,
                'customer' => (object) ['id' => 1, 'name' => 'Test Company'],
                'dashboard_type' => 'customer',
                'is_system_user' => false,
                'user_permissions' => [
                    'can_manage_users' => false,
                    'can_view_billing' => true,
                    'can_manage_orders' => true
                ]
            ]);
        }
    }
}