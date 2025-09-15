<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CourierService;
use App\Repository\CourierServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
class SystemSettingsController extends AbstractController
{
    public function __construct(
        private readonly CourierServiceRepository $courierServiceRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_settings', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/settings/index.html.twig', [
            'courier_services' => $this->courierServiceRepository->findAll(),
            'system_info' => $this->getSystemInfo(),
        ]);
    }

    #[Route('/general', name: 'admin_settings_general', methods: ['GET', 'POST'])]
    public function general(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateGeneralSettings($request);
        }

        return $this->render('admin/settings/general.html.twig', [
            'settings' => $this->getGeneralSettings(),
        ]);
    }

    #[Route('/couriers', name: 'admin_settings_couriers', methods: ['GET'])]
    public function couriers(): Response
    {
        $courierServices = $this->courierServiceRepository->findAll();

        return $this->render('admin/settings/couriers.html.twig', [
            'courier_services' => $courierServices,
        ]);
    }

    #[Route('/couriers/create', name: 'admin_settings_courier_create', methods: ['GET', 'POST'])]
    public function createCourier(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->createCourierService($request);
        }

        return $this->render('admin/settings/courier_create.html.twig');
    }

    #[Route('/couriers/{id}/edit', name: 'admin_settings_courier_edit', methods: ['GET', 'POST'])]
    public function editCourier(CourierService $courier, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateCourierService($courier, $request);
        }

        return $this->render('admin/settings/courier_edit.html.twig', [
            'courier' => $courier,
        ]);
    }

    #[Route('/couriers/{id}/toggle', name: 'admin_settings_courier_toggle', methods: ['POST'])]
    public function toggleCourier(CourierService $courier): JsonResponse
    {
        $courier->setActive(!$courier->isActive());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'active' => $courier->isActive(),
            'message' => sprintf('Courier service %s', $courier->isActive() ? 'activated' : 'deactivated'),
        ]);
    }

    #[Route('/api/keys', name: 'admin_settings_api_keys', methods: ['GET', 'POST'])]
    public function apiKeys(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateApiKeys($request);
        }

        return $this->render('admin/settings/api_keys.html.twig', [
            'api_keys' => $this->getApiKeys(),
        ]);
    }

    #[Route('/email', name: 'admin_settings_email', methods: ['GET', 'POST'])]
    public function email(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateEmailSettings($request);
        }

        return $this->render('admin/settings/email.html.twig', [
            'settings' => $this->getEmailSettings(),
        ]);
    }

    #[Route('/notifications', name: 'admin_settings_notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateNotificationSettings($request);
        }

        return $this->render('admin/settings/notifications.html.twig', [
            'settings' => $this->getNotificationSettings(),
        ]);
    }

    #[Route('/backup', name: 'admin_settings_backup', methods: ['GET', 'POST'])]
    public function backup(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->createBackup($request);
        }

        return $this->render('admin/settings/backup.html.twig', [
            'backups' => $this->getBackupHistory(),
        ]);
    }

    #[Route('/logs', name: 'admin_settings_logs', methods: ['GET'])]
    public function logs(Request $request): Response
    {
        $logLevel = $request->query->get('level', 'all');
        $limit = $request->query->getInt('limit', 100);

        return $this->render('admin/settings/logs.html.twig', [
            'logs' => $this->getSystemLogs($logLevel, $limit),
            'current_level' => $logLevel,
        ]);
    }

    #[Route('/cache/clear', name: 'admin_settings_cache_clear', methods: ['POST'])]
    public function clearCache(): JsonResponse
    {
        try {
            // Clear Symfony cache
            $cacheDir = $this->getParameter('kernel.cache_dir');
            $filesystem = new \Symfony\Component\Filesystem\Filesystem();

            if (is_dir($cacheDir)) {
                $filesystem->remove($cacheDir);
            }

            return $this->json([
                'success' => true,
                'message' => 'Cache cleared successfully',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function createCourierService(Request $request): Response
    {
        $data = $request->request->all();

        $courier = new CourierService();
        $courier->setName($data['name'] ?? '');
        $courier->setCode($data['code'] ?? '');
        $courier->setDescription($data['description'] ?? null);
        $courier->setActive($data['active'] ?? true);
        $courier->setDomestic($data['domestic'] ?? true);
        $courier->setInternational($data['international'] ?? false);
        $courier->setSupportedServices($data['supported_services'] ?? []);

        // Handle API credentials (should be encrypted in production)
        if (!empty($data['api_credentials'])) {
            $courier->setApiCredentials($data['api_credentials']);
        }

        $this->entityManager->persist($courier);
        $this->entityManager->flush();

        $this->addFlash('success', 'Courier service created successfully');

        return $this->redirectToRoute('admin_settings_couriers');
    }

    private function updateCourierService(CourierService $courier, Request $request): Response
    {
        $data = $request->request->all();

        $courier->setName($data['name'] ?? $courier->getName());
        $courier->setCode($data['code'] ?? $courier->getCode());
        $courier->setDescription($data['description'] ?? $courier->getDescription());
        $courier->setActive($data['active'] ?? $courier->isActive());
        $courier->setDomestic($data['domestic'] ?? $courier->isDomestic());
        $courier->setInternational($data['international'] ?? $courier->isInternational());
        $courier->setSupportedServices($data['supported_services'] ?? $courier->getSupportedServices());
        $courier->setUpdatedAt(new \DateTime());

        if (!empty($data['api_credentials'])) {
            $courier->setApiCredentials($data['api_credentials']);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Courier service updated successfully');

        return $this->redirectToRoute('admin_settings_couriers');
    }

    private function updateGeneralSettings(Request $request): Response
    {
        $data = $request->request->all();

        // In production, these would be stored in database or environment variables
        $settings = [
            'site_name' => $data['site_name'] ?? 'Sky Broker System',
            'site_description' => $data['site_description'] ?? '',
            'admin_email' => $data['admin_email'] ?? '',
            'timezone' => $data['timezone'] ?? 'Europe/Warsaw',
            'maintenance_mode' => $data['maintenance_mode'] ?? false,
        ];

        // Save settings (implementation depends on your preference)
        $this->saveSettings('general', $settings);

        $this->addFlash('success', 'General settings updated successfully');

        return $this->redirectToRoute('admin_settings_general');
    }

    private function updateApiKeys(Request $request): Response
    {
        $data = $request->request->all();

        $apiKeys = [
            'inpost_api_key' => $data['inpost_api_key'] ?? '',
            'dhl_api_key' => $data['dhl_api_key'] ?? '',
            'paynow_api_key' => $data['paynow_api_key'] ?? '',
            'stripe_public_key' => $data['stripe_public_key'] ?? '',
            'stripe_secret_key' => $data['stripe_secret_key'] ?? '',
        ];

        // In production, encrypt sensitive keys before storing
        $this->saveSettings('api_keys', $apiKeys);

        $this->addFlash('success', 'API keys updated successfully');

        return $this->redirectToRoute('admin_settings_api_keys');
    }

    private function updateEmailSettings(Request $request): Response
    {
        $data = $request->request->all();

        $emailSettings = [
            'smtp_host' => $data['smtp_host'] ?? '',
            'smtp_port' => $data['smtp_port'] ?? '587',
            'smtp_username' => $data['smtp_username'] ?? '',
            'smtp_password' => $data['smtp_password'] ?? '',
            'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
            'from_email' => $data['from_email'] ?? '',
            'from_name' => $data['from_name'] ?? '',
        ];

        $this->saveSettings('email', $emailSettings);

        $this->addFlash('success', 'Email settings updated successfully');

        return $this->redirectToRoute('admin_settings_email');
    }

    private function updateNotificationSettings(Request $request): Response
    {
        $data = $request->request->all();

        $notificationSettings = [
            'email_notifications' => $data['email_notifications'] ?? true,
            'sms_notifications' => $data['sms_notifications'] ?? false,
            'push_notifications' => $data['push_notifications'] ?? true,
            'order_notifications' => $data['order_notifications'] ?? true,
            'shipment_notifications' => $data['shipment_notifications'] ?? true,
            'payment_notifications' => $data['payment_notifications'] ?? true,
        ];

        $this->saveSettings('notifications', $notificationSettings);

        $this->addFlash('success', 'Notification settings updated successfully');

        return $this->redirectToRoute('admin_settings_notifications');
    }

    private function createBackup(Request $request): JsonResponse
    {
        try {
            // This is a simplified backup implementation
            $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = $this->getParameter('kernel.project_dir') . '/var/backups/';

            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            // In production, you would use proper database backup commands
            // This is just a placeholder
            file_put_contents($backupPath . $backupName, '-- Database backup created at ' . date('Y-m-d H:i:s'));

            return $this->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'filename' => $backupName,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'symfony_version' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
        ];
    }

    private function getGeneralSettings(): array
    {
        return $this->loadSettings('general') ?? [
            'site_name' => 'Sky Broker System',
            'site_description' => '',
            'admin_email' => '',
            'timezone' => 'Europe/Warsaw',
            'maintenance_mode' => false,
        ];
    }

    private function getApiKeys(): array
    {
        $keys = $this->loadSettings('api_keys') ?? [];

        // Mask sensitive keys for display
        foreach ($keys as $key => $value) {
            if (!empty($value) && strlen($value) > 8) {
                $keys[$key] = substr($value, 0, 4) . '****' . substr($value, -4);
            }
        }

        return $keys;
    }

    private function getEmailSettings(): array
    {
        return $this->loadSettings('email') ?? [
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'from_email' => '',
            'from_name' => '',
        ];
    }

    private function getNotificationSettings(): array
    {
        return $this->loadSettings('notifications') ?? [
            'email_notifications' => true,
            'sms_notifications' => false,
            'push_notifications' => true,
            'order_notifications' => true,
            'shipment_notifications' => true,
            'payment_notifications' => true,
        ];
    }

    private function getBackupHistory(): array
    {
        $backupPath = $this->getParameter('kernel.project_dir') . '/var/backups/';

        if (!is_dir($backupPath)) {
            return [];
        }

        $files = glob($backupPath . '*.sql');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        return array_reverse($backups);
    }

    private function getSystemLogs(string $level, int $limit): array
    {
        // This is a simplified log reader
        $logFile = $this->getParameter('kernel.logs_dir') . '/prod.log';

        if (!file_exists($logFile)) {
            return [];
        }

        $logs = [];
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_slice($lines, -$limit);

        foreach ($lines as $line) {
            if ($level !== 'all' && strpos($line, strtoupper($level)) === false) {
                continue;
            }

            $logs[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => 'INFO', // Simplified
                'message' => $line,
            ];
        }

        return array_reverse($logs);
    }

    private function saveSettings(string $category, array $settings): void
    {
        // In production, implement proper settings storage
        // This could be database, environment variables, or configuration files
        $settingsFile = $this->getParameter('kernel.cache_dir') . "/settings_{$category}.json";
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    }

    private function loadSettings(string $category): ?array
    {
        $settingsFile = $this->getParameter('kernel.cache_dir') . "/settings_{$category}.json";

        if (!file_exists($settingsFile)) {
            return null;
        }

        return json_decode(file_get_contents($settingsFile), true);
    }
}