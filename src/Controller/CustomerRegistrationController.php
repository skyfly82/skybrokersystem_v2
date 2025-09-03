<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/registration')]
class CustomerRegistrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {}

    #[Route('/register', name: 'api_customer_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        $requiredFields = ['email', 'password', 'firstName', 'lastName', 'companyName', 'customerType'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Field '$field' is required"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Check if email already exists
        $existingUser = $this->entityManager->getRepository(CustomerUser::class)
            ->findOneBy(['email' => $data['email']]);
        
        if ($existingUser) {
            return $this->json(['error' => 'Email already registered'], Response::HTTP_CONFLICT);
        }

        try {
            // Create Customer (Company)
            $customer = new Customer();
            $customer->setCompanyName($data['companyName'])
                    ->setType($data['customerType']) // 'individual' or 'business'
                    ->setStatus('active');

            // Set optional company fields if provided
            if (!empty($data['vatNumber'])) {
                $customer->setVatNumber($data['vatNumber']);
            }
            if (!empty($data['regon'])) {
                $customer->setRegon($data['regon']);
            }
            if (!empty($data['address'])) {
                $customer->setAddress($data['address']);
            }
            if (!empty($data['postalCode'])) {
                $customer->setPostalCode($data['postalCode']);
            }
            if (!empty($data['city'])) {
                $customer->setCity($data['city']);
            }
            if (!empty($data['country'])) {
                $customer->setCountry($data['country']);
            }
            if (!empty($data['phone'])) {
                $customer->setPhone($data['phone']);
            }
            if (!empty($data['email'])) {
                $customer->setEmail($data['email']);
            }

            // Create Customer User (First user - Owner)
            $customerUser = new CustomerUser();
            $customerUser->setEmail($data['email'])
                        ->setFirstName($data['firstName'])
                        ->setLastName($data['lastName'])
                        ->setCustomerRole('owner') // First user is always owner
                        ->setStatus('active')
                        ->setCustomer($customer);

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($customerUser, $data['password']);
            $customerUser->setPassword($hashedPassword);

            if (!empty($data['userPhone'])) {
                $customerUser->setPhone($data['userPhone']);
            }

            // Validate entities
            $customerErrors = $this->validator->validate($customer);
            $userErrors = $this->validator->validate($customerUser);

            if (count($customerErrors) > 0 || count($userErrors) > 0) {
                $errors = [];
                foreach ($customerErrors as $error) {
                    $errors[] = $error->getMessage();
                }
                foreach ($userErrors as $error) {
                    $errors[] = $error->getMessage();
                }
                return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
            }

            // Save to database
            $this->entityManager->persist($customer);
            $this->entityManager->persist($customerUser);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Registration successful',
                'customer' => [
                    'id' => $customer->getId(),
                    'companyName' => $customer->getCompanyName(),
                    'type' => $customer->getType()
                ],
                'user' => [
                    'id' => $customerUser->getId(),
                    'email' => $customerUser->getEmail(),
                    'fullName' => $customerUser->getFullName(),
                    'role' => $customerUser->getCustomerRole()
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/check-email', name: 'api_check_email', methods: ['POST'])]
    public function checkEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['email'])) {
            return $this->json(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->entityManager->getRepository(CustomerUser::class)
            ->findOneBy(['email' => $data['email']]);

        return $this->json([
            'available' => $existingUser === null,
            'message' => $existingUser ? 'Email already registered' : 'Email available'
        ]);
    }

    #[Route('/customer-types', name: 'api_customer_types', methods: ['GET'])]
    public function getCustomerTypes(): JsonResponse
    {
        return $this->json([
            'types' => [
                [
                    'value' => 'individual',
                    'label' => 'Individual Customer',
                    'description' => 'Personal account for individual customers'
                ],
                [
                    'value' => 'business',
                    'label' => 'Business Customer', 
                    'description' => 'Company account for business customers'
                ]
            ]
        ]);
    }
}