<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Repository\CustomerRepository;
use App\Repository\SystemUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notifications')]
#[IsGranted('ROLE_SYSTEM_USER')]
class NotificationsController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly SystemUserRepository $systemUserRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_notifications', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');
        $status = $request->query->get('status', '');
        $priority = $request->query->get('priority', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        $filters = [
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'priority' => $priority,
            'page' => $page,
            'limit' => $limit,
        ];

        $notifications = $this->notificationRepository->findWithFilters($filters);
        $totalNotifications = $this->notificationRepository->countWithFilters($filters);

        return $this->render('admin/notifications/index.html.twig', [
            'notifications' => $notifications,
            'total_notifications' => $totalNotifications,
            'current_page' => $page,
            'total_pages' => ceil($totalNotifications / $limit),
            'filters' => $filters,
            'statistics' => $this->getNotificationStatistics(),
        ]);
    }

    #[Route('/api', name: 'admin_notifications_api', methods: ['GET'])]
    public function getNotificationsApi(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');
        $status = $request->query->get('status', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 25);

        $filters = [
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'page' => $page,
            'limit' => $limit,
        ];

        $notifications = $this->notificationRepository->findWithFilters($filters);
        $totalNotifications = $this->notificationRepository->countWithFilters($filters);

        $notificationsData = [];
        foreach ($notifications as $notification) {
            $notificationsData[] = [
                'id' => $notification->getId(),
                'type' => $notification->getType(),
                'subject' => $notification->getSubject(),
                'message' => substr($notification->getMessage(), 0, 100) . '...',
                'status' => $notification->getStatus(),
                'priority' => $notification->getPriority(),
                'recipient' => $this->getRecipientName($notification),
                'created_at' => $notification->getCreatedAt()?->format('Y-m-d H:i:s'),
                'sent_at' => $notification->getSentAt()?->format('Y-m-d H:i:s'),
                'scheduled_at' => $notification->getScheduledAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'notifications' => $notificationsData,
            'total' => $totalNotifications,
            'page' => $page,
            'total_pages' => ceil($totalNotifications / $limit),
        ]);
    }

    #[Route('/create', name: 'admin_notification_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->createNotification($request);
        }

        return $this->render('admin/notifications/create.html.twig', [
            'customers' => $this->customerRepository->findAll(),
            'system_users' => $this->systemUserRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'admin_notification_show', methods: ['GET'])]
    public function show(Notification $notification): Response
    {
        return $this->render('admin/notifications/show.html.twig', [
            'notification' => $notification,
        ]);
    }

    #[Route('/{id}/resend', name: 'admin_notification_resend', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function resend(Notification $notification): JsonResponse
    {
        if ($notification->getStatus() !== 'failed') {
            return $this->json(['error' => 'Only failed notifications can be resent'], 400);
        }

        // Reset notification status
        $notification->setStatus('pending');
        $notification->setSentAt(null);
        $notification->setErrorMessage(null);

        $this->entityManager->flush();

        // Here you would trigger the notification sending process
        // For demo purposes, we'll mark it as sent
        $notification->markAsSent();
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Notification resent successfully',
            'status' => $notification->getStatus(),
        ]);
    }

    #[Route('/{id}/cancel', name: 'admin_notification_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(Notification $notification): JsonResponse
    {
        if (!$notification->isPending()) {
            return $this->json(['error' => 'Only pending notifications can be cancelled'], 400);
        }

        $notification->setStatus('cancelled');
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Notification cancelled successfully',
            'status' => $notification->getStatus(),
        ]);
    }

    #[Route('/bulk/action', name: 'admin_notifications_bulk_action', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function bulkAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;
        $notificationIds = $data['notification_ids'] ?? [];

        if (!$action || empty($notificationIds)) {
            return $this->json(['error' => 'Invalid action or no notifications selected'], 400);
        }

        $notifications = $this->notificationRepository->findBy(['id' => $notificationIds]);

        $count = 0;
        foreach ($notifications as $notification) {
            switch ($action) {
                case 'resend':
                    if ($notification->isFailed()) {
                        $notification->setStatus('pending');
                        $notification->setSentAt(null);
                        $notification->setErrorMessage(null);
                        $count++;
                    }
                    break;
                case 'cancel':
                    if ($notification->isPending()) {
                        $notification->setStatus('cancelled');
                        $count++;
                    }
                    break;
                case 'delete':
                    $this->entityManager->remove($notification);
                    $count++;
                    break;
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('%d notifications processed successfully', $count),
            'processed_count' => $count,
        ]);
    }

    #[Route('/send/broadcast', name: 'admin_notification_broadcast', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function broadcast(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $type = $data['type'] ?? 'email';
        $subject = $data['subject'] ?? '';
        $message = $data['message'] ?? '';
        $recipients = $data['recipients'] ?? 'all_customers'; // 'all_customers', 'all_system_users', 'specific'
        $priority = $data['priority'] ?? 'normal';
        $scheduleAt = $data['schedule_at'] ?? null;

        if (!$subject || !$message) {
            return $this->json(['error' => 'Subject and message are required'], 400);
        }

        $createdCount = 0;

        // Determine recipients
        if ($recipients === 'all_customers') {
            $customers = $this->customerRepository->findBy(['status' => 'active']);
            foreach ($customers as $customer) {
                $this->createBroadcastNotification($type, $subject, $message, $priority, $scheduleAt, $customer);
                $createdCount++;
            }
        } elseif ($recipients === 'all_system_users') {
            $systemUsers = $this->systemUserRepository->findBy(['status' => 'active']);
            foreach ($systemUsers as $systemUser) {
                $this->createBroadcastNotification($type, $subject, $message, $priority, $scheduleAt, null, $systemUser);
                $createdCount++;
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('Broadcast notification created for %d recipients', $createdCount),
            'created_count' => $createdCount,
        ]);
    }

    #[Route('/templates', name: 'admin_notification_templates', methods: ['GET'])]
    public function templates(): Response
    {
        return $this->render('admin/notifications/templates.html.twig', [
            'templates' => $this->getNotificationTemplates(),
        ]);
    }

    #[Route('/analytics', name: 'admin_notifications_analytics', methods: ['GET'])]
    public function analytics(Request $request): JsonResponse
    {
        $period = $request->query->get('period', '30days');
        $chartData = $this->getNotificationAnalytics($period);

        return $this->json($chartData);
    }

    private function createNotification(Request $request): Response
    {
        $data = $request->request->all();

        $notification = new Notification();
        $notification->setType($data['type'] ?? 'email');
        $notification->setSubject($data['subject'] ?? '');
        $notification->setMessage($data['message'] ?? '');
        $notification->setPriority($data['priority'] ?? 'normal');

        if (!empty($data['customer_id'])) {
            $customer = $this->customerRepository->find($data['customer_id']);
            $notification->setCustomer($customer);
        }

        if (!empty($data['system_user_id'])) {
            $systemUser = $this->systemUserRepository->find($data['system_user_id']);
            $notification->setSystemUser($systemUser);
        }

        if (!empty($data['scheduled_at'])) {
            $notification->setScheduledAt(new \DateTime($data['scheduled_at']));
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->addFlash('success', 'Notification created successfully');

        return $this->redirectToRoute('admin_notification_show', ['id' => $notification->getId()]);
    }

    private function createBroadcastNotification(
        string $type,
        string $subject,
        string $message,
        string $priority,
        ?string $scheduleAt,
        $customer = null,
        $systemUser = null
    ): void {
        $notification = new Notification();
        $notification->setType($type);
        $notification->setSubject($subject);
        $notification->setMessage($message);
        $notification->setPriority($priority);

        if ($customer) {
            $notification->setCustomer($customer);
        }

        if ($systemUser) {
            $notification->setSystemUser($systemUser);
        }

        if ($scheduleAt) {
            $notification->setScheduledAt(new \DateTime($scheduleAt));
        }

        $this->entityManager->persist($notification);
    }

    private function getRecipientName(Notification $notification): string
    {
        if ($notification->getCustomer()) {
            return $notification->getCustomer()->getCompanyName();
        }

        if ($notification->getCustomerUser()) {
            return $notification->getCustomerUser()->getFullName();
        }

        if ($notification->getSystemUser()) {
            return $notification->getSystemUser()->getFullName();
        }

        return 'Unknown';
    }

    private function getNotificationStatistics(): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        $startOfMonth = new \DateTime('first day of this month');
        $now = new \DateTime();

        $stats = $this->notificationRepository->getStatistics($startOfMonth, $now);

        return [
            'total_this_month' => (int) $stats['total_notifications'],
            'sent_this_month' => (int) $stats['sent_notifications'],
            'pending' => (int) $stats['pending_notifications'],
            'failed' => (int) $stats['failed_notifications'],
            'email_notifications' => (int) $stats['email_notifications'],
            'sms_notifications' => (int) $stats['sms_notifications'],
        ];
    }

    private function getNotificationTemplates(): array
    {
        return [
            'order_confirmation' => [
                'name' => 'Order Confirmation',
                'subject' => 'Your order has been confirmed',
                'message' => 'Dear {{customer_name}}, your order #{{order_number}} has been confirmed.',
                'variables' => ['customer_name', 'order_number', 'order_date'],
            ],
            'shipment_dispatch' => [
                'name' => 'Shipment Dispatched',
                'subject' => 'Your package is on its way',
                'message' => 'Dear {{customer_name}}, your package with tracking number {{tracking_number}} has been dispatched.',
                'variables' => ['customer_name', 'tracking_number', 'courier_service'],
            ],
            'payment_received' => [
                'name' => 'Payment Received',
                'subject' => 'Payment confirmation',
                'message' => 'Dear {{customer_name}}, we have received your payment of {{amount}}.',
                'variables' => ['customer_name', 'amount', 'transaction_id'],
            ],
        ];
    }

    private function getNotificationAnalytics(string $period): array
    {
        $days = match ($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30,
        };

        $from = new \DateTime("-{$days} days");
        $to = new \DateTime();

        $stats = $this->notificationRepository->getStatistics($from, $to);

        $data = [];
        $labels = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} days");
            $nextDate = clone $date;
            $nextDate->add(new \DateInterval('P1D'));

            $dayStats = $this->notificationRepository->getStatistics($date, $nextDate);

            $labels[] = $date->format('M j');
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'sent' => (int) $dayStats['sent_notifications'],
                'failed' => (int) $dayStats['failed_notifications'],
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Sent',
                    'data' => array_column($data, 'sent'),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                ],
                [
                    'label' => 'Failed',
                    'data' => array_column($data, 'failed'),
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
            ],
            'summary' => $stats,
        ];
    }
}