<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Repository\CustomerUserRepository;
use App\Repository\OrderRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/customers')]
#[IsGranted('ROLE_SYSTEM_USER')]
class CustomersController extends AbstractController
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly CustomerUserRepository $customerUserRepository,
        private readonly OrderRepository $orderRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_customers', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $type = $request->query->get('type', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        $customers = $this->customerRepository->findWithFilters([
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'page' => $page,
            'limit' => $limit,
        ]);

        $totalCustomers = $this->customerRepository->countWithFilters([
            'search' => $search,
            'status' => $status,
            'type' => $type,
        ]);

        return $this->render('admin/customers/index.html.twig', [
            'customers' => $customers,
            'total_customers' => $totalCustomers,
            'current_page' => $page,
            'total_pages' => ceil($totalCustomers / $limit),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
            ],
            'statistics' => $this->getCustomerStatistics(),
        ]);
    }

    #[Route('/api', name: 'admin_customers_api', methods: ['GET'])]
    public function getCustomersApi(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $type = $request->query->get('type', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 25);

        $customers = $this->customerRepository->findWithFilters([
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'page' => $page,
            'limit' => $limit,
        ]);

        $totalCustomers = $this->customerRepository->countWithFilters([
            'search' => $search,
            'status' => $status,
            'type' => $type,
        ]);

        $customersData = [];
        foreach ($customers as $customer) {
            $customersData[] = [
                'id' => $customer->getId(),
                'company_name' => $customer->getCompanyName(),
                'type' => $customer->getType(),
                'status' => $customer->getStatus(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'city' => $customer->getCity(),
                'country' => $customer->getCountry(),
                'created_at' => $customer->getCreatedAt()?->format('Y-m-d H:i:s'),
                'users_count' => $customer->getCustomerUsers()->count(),
            ];
        }

        return $this->json([
            'customers' => $customersData,
            'total' => $totalCustomers,
            'page' => $page,
            'total_pages' => ceil($totalCustomers / $limit),
        ]);
    }

    #[Route('/{id}', name: 'admin_customer_show', methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        $customerUsers = $this->customerUserRepository->findBy(['customer' => $customer]);
        $recentOrders = $this->orderRepository->findBy(['customer' => $customer], ['createdAt' => 'DESC'], 10);
        $recentTransactions = $this->transactionRepository->findByCustomer($customer, 10);

        $statistics = [
            'total_orders' => $this->orderRepository->count(['customer' => $customer]),
            'total_spent' => $this->transactionRepository->getTotalRevenueForPeriod(
                $customer->getCreatedAt(),
                new \DateTime()
            ),
            'active_users' => $this->customerUserRepository->count([
                'customer' => $customer,
                'status' => 'active'
            ]),
        ];

        return $this->render('admin/customers/show.html.twig', [
            'customer' => $customer,
            'customer_users' => $customerUsers,
            'recent_orders' => $recentOrders,
            'recent_transactions' => $recentTransactions,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Customer $customer, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateCustomer($customer, $request);
        }

        return $this->render('admin/customers/edit.html.twig', [
            'customer' => $customer,
        ]);
    }

    #[Route('/{id}/status', name: 'admin_customer_update_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStatus(Customer $customer, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!in_array($newStatus, ['active', 'inactive', 'suspended'])) {
            return $this->json(['error' => 'Invalid status'], 400);
        }

        $customer->setStatus($newStatus);
        $customer->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Customer status updated successfully',
            'status' => $customer->getStatus(),
        ]);
    }

    #[Route('/bulk/action', name: 'admin_customers_bulk_action', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function bulkAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;
        $customerIds = $data['customer_ids'] ?? [];

        if (!$action || empty($customerIds)) {
            return $this->json(['error' => 'Invalid action or no customers selected'], 400);
        }

        $customers = $this->customerRepository->findBy(['id' => $customerIds]);

        $count = 0;
        foreach ($customers as $customer) {
            switch ($action) {
                case 'activate':
                    $customer->setStatus('active');
                    $count++;
                    break;
                case 'deactivate':
                    $customer->setStatus('inactive');
                    $count++;
                    break;
                case 'suspend':
                    $customer->setStatus('suspended');
                    $count++;
                    break;
            }
            $customer->setUpdatedAt(new \DateTime());
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('%d customers updated successfully', $count),
            'updated_count' => $count,
        ]);
    }

    #[Route('/export', name: 'admin_customers_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $type = $request->query->get('type', '');

        $customers = $this->customerRepository->findWithFilters([
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'limit' => 10000, // High limit for export
        ]);

        if ($format === 'csv') {
            return $this->exportToCsv($customers);
        }

        return $this->json(['error' => 'Unsupported export format'], 400);
    }

    private function updateCustomer(Customer $customer, Request $request): Response
    {
        $data = $request->request->all();

        $customer->setCompanyName($data['company_name'] ?? $customer->getCompanyName());
        $customer->setVatNumber($data['vat_number'] ?? $customer->getVatNumber());
        $customer->setRegon($data['regon'] ?? $customer->getRegon());
        $customer->setAddress($data['address'] ?? $customer->getAddress());
        $customer->setPostalCode($data['postal_code'] ?? $customer->getPostalCode());
        $customer->setCity($data['city'] ?? $customer->getCity());
        $customer->setCountry($data['country'] ?? $customer->getCountry());
        $customer->setPhone($data['phone'] ?? $customer->getPhone());
        $customer->setEmail($data['email'] ?? $customer->getEmail());
        $customer->setType($data['type'] ?? $customer->getType());
        $customer->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->addFlash('success', 'Customer updated successfully');

        return $this->redirectToRoute('admin_customer_show', ['id' => $customer->getId()]);
    }

    private function getCustomerStatistics(): array
    {
        $total = $this->customerRepository->count([]);
        $active = $this->customerRepository->count(['status' => 'active']);
        $inactive = $this->customerRepository->count(['status' => 'inactive']);
        $suspended = $this->customerRepository->count(['status' => 'suspended']);
        $business = $this->customerRepository->count(['type' => 'business']);
        $individual = $this->customerRepository->count(['type' => 'individual']);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'suspended' => $suspended,
            'business' => $business,
            'individual' => $individual,
        ];
    }

    private function exportToCsv(array $customers): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="customers_export.csv"');

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, [
            'ID', 'Company Name', 'Type', 'Status', 'Email', 'Phone',
            'Address', 'City', 'Country', 'VAT Number', 'Created At'
        ]);

        foreach ($customers as $customer) {
            fputcsv($output, [
                $customer->getId(),
                $customer->getCompanyName(),
                $customer->getType(),
                $customer->getStatus(),
                $customer->getEmail(),
                $customer->getPhone(),
                $customer->getAddress(),
                $customer->getCity(),
                $customer->getCountry(),
                $customer->getVatNumber(),
                $customer->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);
        }

        fclose($output);

        return $response;
    }
}