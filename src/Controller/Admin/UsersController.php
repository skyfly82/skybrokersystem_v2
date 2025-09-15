<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SystemUser;
use App\Repository\SystemUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UsersController extends AbstractController
{
    public function __construct(
        private readonly SystemUserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator
    ) {}

    #[Route('', name: 'admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $department = $request->query->get('department', '');
        $status = $request->query->get('status', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        $filters = [
            'search' => $search,
            'department' => $department,
            'status' => $status,
            'page' => $page,
            'limit' => $limit,
        ];

        $users = $this->userRepository->findWithFilters($filters);
        $totalUsers = $this->userRepository->countWithFilters($filters);

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'total_users' => $totalUsers,
            'current_page' => $page,
            'total_pages' => ceil($totalUsers / $limit),
            'filters' => $filters,
            'statistics' => $this->getUserStatistics(),
        ]);
    }

    #[Route('/api', name: 'admin_users_api', methods: ['GET'])]
    public function getUsersApi(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $department = $request->query->get('department', '');
        $status = $request->query->get('status', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 25);

        $filters = [
            'search' => $search,
            'department' => $department,
            'status' => $status,
            'page' => $page,
            'limit' => $limit,
        ];

        $users = $this->userRepository->findWithFilters($filters);
        $totalUsers = $this->userRepository->countWithFilters($filters);

        $usersData = [];
        foreach ($users as $user) {
            $usersData[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'full_name' => $user->getFullName(),
                'department' => $user->getDepartment(),
                'position' => $user->getPosition(),
                'status' => $user->getStatus(),
                'roles' => $user->getRoles(),
                'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'email_verified' => $user->isEmailVerified(),
            ];
        }

        return $this->json([
            'users' => $usersData,
            'total' => $totalUsers,
            'page' => $page,
            'total_pages' => ceil($totalUsers / $limit),
        ]);
    }

    #[Route('/create', name: 'admin_user_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->createUser($request);
        }

        return $this->render('admin/users/create.html.twig');
    }

    #[Route('/{id}', name: 'admin_user_show', methods: ['GET'])]
    public function show(SystemUser $user): Response
    {
        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(SystemUser $user, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateUser($user, $request);
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/status', name: 'admin_user_update_status', methods: ['POST'])]
    public function updateStatus(SystemUser $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!in_array($newStatus, ['active', 'inactive', 'suspended'])) {
            return $this->json(['error' => 'Invalid status'], 400);
        }

        $user->setStatus($newStatus);
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'status' => $user->getStatus(),
        ]);
    }

    #[Route('/{id}/reset-password', name: 'admin_user_reset_password', methods: ['POST'])]
    public function resetPassword(SystemUser $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newPassword = $data['password'] ?? null;
        $sendEmail = $data['send_email'] ?? true;

        if (!$newPassword || strlen($newPassword) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters long'], 400);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Here you would send email notification to the user
        if ($sendEmail) {
            // TODO: Implement email notification
        }

        return $this->json([
            'success' => true,
            'message' => 'Password reset successfully',
        ]);
    }

    #[Route('/{id}/roles', name: 'admin_user_update_roles', methods: ['POST'])]
    public function updateRoles(SystemUser $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $roles = $data['roles'] ?? [];

        $validRoles = [
            'ROLE_SYSTEM_USER', 'ROLE_ADMIN', 'ROLE_SUPPORT',
            'ROLE_MARKETING', 'ROLE_SALES', 'ROLE_OPERATIONS'
        ];

        foreach ($roles as $role) {
            if (!in_array($role, $validRoles)) {
                return $this->json(['error' => 'Invalid role: ' . $role], 400);
            }
        }

        $user->setRoles($roles);
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'User roles updated successfully',
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/bulk/action', name: 'admin_users_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;
        $userIds = $data['user_ids'] ?? [];

        if (!$action || empty($userIds)) {
            return $this->json(['error' => 'Invalid action or no users selected'], 400);
        }

        $users = $this->userRepository->findBy(['id' => $userIds]);

        $count = 0;
        foreach ($users as $user) {
            switch ($action) {
                case 'activate':
                    $user->setStatus('active');
                    $count++;
                    break;
                case 'deactivate':
                    $user->setStatus('inactive');
                    $count++;
                    break;
                case 'suspend':
                    $user->setStatus('suspended');
                    $count++;
                    break;
            }
            $user->setUpdatedAt(new \DateTime());
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('%d users updated successfully', $count),
            'updated_count' => $count,
        ]);
    }

    private function createUser(Request $request): Response
    {
        $data = $request->request->all();

        $user = new SystemUser();
        $user->setEmail($data['email'] ?? '');
        $user->setFirstName($data['first_name'] ?? '');
        $user->setLastName($data['last_name'] ?? '');
        $user->setPhone($data['phone'] ?? null);
        $user->setDepartment($data['department'] ?? 'support');
        $user->setPosition($data['position'] ?? null);
        $user->setStatus('active');

        if (!empty($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->render('admin/users/create.html.twig', ['user' => $user]);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'User created successfully');

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    private function updateUser(SystemUser $user, Request $request): Response
    {
        $data = $request->request->all();

        $user->setEmail($data['email'] ?? $user->getEmail());
        $user->setFirstName($data['first_name'] ?? $user->getFirstName());
        $user->setLastName($data['last_name'] ?? $user->getLastName());
        $user->setPhone($data['phone'] ?? $user->getPhone());
        $user->setDepartment($data['department'] ?? $user->getDepartment());
        $user->setPosition($data['position'] ?? $user->getPosition());
        $user->setUpdatedAt(new \DateTime());

        if (!empty($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->render('admin/users/edit.html.twig', ['user' => $user]);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'User updated successfully');

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    private function getUserStatistics(): array
    {
        return [
            'total' => $this->userRepository->count([]),
            'active' => $this->userRepository->count(['status' => 'active']),
            'inactive' => $this->userRepository->count(['status' => 'inactive']),
            'suspended' => $this->userRepository->count(['status' => 'suspended']),
            'admin' => $this->userRepository->count(['department' => 'admin']),
            'support' => $this->userRepository->count(['department' => 'support']),
            'sales' => $this->userRepository->count(['department' => 'sales']),
            'marketing' => $this->userRepository->count(['department' => 'marketing']),
            'operations' => $this->userRepository->count(['department' => 'operations']),
        ];
    }
}