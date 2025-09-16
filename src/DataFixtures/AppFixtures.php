<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\CustomerUser;
use App\Entity\SystemUser;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Shipment;
use App\Entity\Transaction;
use App\Entity\Notification;
use App\Entity\CourierService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private array $customers = [];
    private array $customerUsers = [];
    private array $courierServices = [];
    private array $orders = [];

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin system users first
        $this->createSystemUsers($manager);

        // Create courier services
        $this->createCourierServices($manager);

        // Create customers and customer users
        $this->createCustomers($manager);
        $this->createCustomerUsers($manager);

        // Create orders with items
        $this->createOrders($manager);

        // Create shipments
        $this->createShipments($manager);

        // Create transactions
        $this->createTransactions($manager);

        // Create notifications
        $this->createNotifications($manager);

        $manager->flush();
    }

    private function createSystemUsers(ObjectManager $manager): void
    {
        // Create main admin user for testing
        $admin = new SystemUser();
        $admin->setEmail('admin@skybroker.com')
            ->setFirstName('Admin')
            ->setLastName('User')
            ->setDepartment('admin')
            ->setPosition('System Administrator')
            ->setPhone('+48123456789')
            ->setStatus('active')
            ->setEmailVerifiedAt(new \DateTime())
            ->setRoles(['ROLE_ADMIN', 'ROLE_SYSTEM_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        // Create support users
        $departments = [
            ['support', 'Support Manager', 'Jan', 'Kowalski'],
            ['sales', 'Sales Representative', 'Anna', 'Nowak'],
            ['operations', 'Operations Coordinator', 'Piotr', 'Wiśniewski'],
            ['marketing', 'Marketing Specialist', 'Katarzyna', 'Dąbrowska']
        ];

        foreach ($departments as $i => $dept) {
            $user = new SystemUser();
            $user->setEmail(strtolower($dept[2]) . '.' . strtolower($dept[3]) . '@skybroker.com')
                ->setFirstName($dept[2])
                ->setLastName($dept[3])
                ->setDepartment($dept[0])
                ->setPosition($dept[1])
                ->setPhone('+4812345678' . ($i + 1))
                ->setStatus('active')
                ->setEmailVerifiedAt(new \DateTime())
                ->setLastLoginAt((new \DateTime())->modify('-' . rand(1, 30) . ' days'));

            $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            $manager->persist($user);
        }
    }

    private function createCourierServices(ObjectManager $manager): void
    {
        $services = [
            [
                'name' => 'InPost',
                'code' => 'inpost',
                'description' => 'Polish courier service with parcel lockers',
                'domestic' => true,
                'international' => false,
                'services' => ['standard', 'express', 'locker']
            ],
            [
                'name' => 'DHL Express',
                'code' => 'dhl',
                'description' => 'International express delivery service',
                'domestic' => true,
                'international' => true,
                'services' => ['standard', 'express', 'economy']
            ],
            [
                'name' => 'UPS',
                'code' => 'ups',
                'description' => 'United Parcel Service international delivery',
                'domestic' => false,
                'international' => true,
                'services' => ['standard', 'express', 'saver']
            ]
        ];

        foreach ($services as $serviceData) {
            $service = new CourierService();
            $service->setName($serviceData['name'])
                ->setCode($serviceData['code'])
                ->setDescription($serviceData['description'])
                ->setActive(true)
                ->setDomestic($serviceData['domestic'])
                ->setInternational($serviceData['international'])
                ->setSupportedServices($serviceData['services']);

            $this->courierServices[] = $service;
            $manager->persist($service);
        }
    }

    private function createCustomers(ObjectManager $manager): void
    {
        $companies = [
            ['TechCorp Sp. z o.o.', 'PL1234567890', '123456789', 'Warszawa', '00-001'],
            ['E-Commerce Solutions', 'PL2345678901', '234567890', 'Kraków', '30-001'],
            ['Logistics Pro', 'PL3456789012', '345678901', 'Gdańsk', '80-001'],
            ['Fashion Store', 'PL4567890123', '456789012', 'Wrocław', '50-001'],
            ['Auto Parts Plus', 'PL5678901234', '567890123', 'Poznań', '60-001'],
            ['Books & More', 'PL6789012345', '678901234', 'Łódź', '90-001'],
            ['Electronics Hub', 'PL7890123456', '789012345', 'Katowice', '40-001'],
            ['Sports Equipment', 'PL8901234567', '890123456', 'Lublin', '20-001'],
            ['Home & Garden', 'PL9012345678', '901234567', 'Białystok', '15-001'],
            ['Medical Supplies', 'PL0123456789', '012345678', 'Rzeszów', '35-001']
        ];

        $streets = [
            'ul. Marszałkowska 123',
            'ul. Krakowska 45',
            'ul. Gdańska 67',
            'ul. Wrocławska 89',
            'ul. Poznańska 12',
            'ul. Łódzka 34',
            'ul. Katowicka 56',
            'ul. Lubelska 78',
            'ul. Białostocka 90',
            'ul. Rzeszowska 11'
        ];

        foreach ($companies as $i => $companyData) {
            $customer = new Customer();
            $customer->setCompanyName($companyData[0])
                ->setVatNumber($companyData[1])
                ->setRegon($companyData[2])
                ->setAddress($streets[$i])
                ->setPostalCode($companyData[4])
                ->setCity($companyData[3])
                ->setCountry('Poland')
                ->setPhone('+48' . rand(100000000, 999999999))
                ->setEmail(strtolower(str_replace([' ', '.', '&'], ['', '', ''], $companyData[0])) . '@company.com')
                ->setType('business')
                ->setStatus(rand(0, 10) > 1 ? 'active' : 'inactive') // 90% active
                ->setCreatedAt((new \DateTime())->modify('-' . rand(1, 365) . ' days'));

            $this->customers[] = $customer;
            $manager->persist($customer);
        }

        // Add some individual customers
        $individuals = [
            ['Jan Kowalski', 'jan.kowalski@example.com', 'Warszawa'],
            ['Anna Nowak', 'anna.nowak@example.com', 'Kraków'],
            ['Piotr Wiśniewski', 'piotr.wisniewski@example.com', 'Gdańsk']
        ];

        foreach ($individuals as $i => $individual) {
            $customer = new Customer();
            $customer->setCompanyName($individual[0])
                ->setAddress('ul. Domowa ' . rand(1, 100))
                ->setPostalCode(rand(10, 99) . '-' . rand(100, 999))
                ->setCity($individual[2])
                ->setCountry('Poland')
                ->setPhone('+48' . rand(100000000, 999999999))
                ->setEmail($individual[1])
                ->setType('individual')
                ->setStatus('active')
                ->setCreatedAt((new \DateTime())->modify('-' . rand(1, 180) . ' days'));

            $this->customers[] = $customer;
            $manager->persist($customer);
        }
    }

    private function createCustomerUsers(ObjectManager $manager): void
    {
        $roles = ['owner', 'manager', 'employee'];
        $firstNames = ['Adam', 'Ewa', 'Tomasz', 'Magdalena', 'Paweł', 'Agnieszka', 'Michał', 'Katarzyna'];
        $lastNames = ['Kowalski', 'Nowak', 'Wiśniewski', 'Dąbrowska', 'Lewandowski', 'Wójcik', 'Kamiński', 'Kowalczyk'];

        foreach ($this->customers as $customer) {
            // Skip individual customers for user creation
            if ($customer->getType() === 'individual') {
                continue;
            }

            // Create 1-3 users per business customer
            $userCount = rand(1, 3);
            for ($i = 0; $i < $userCount; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];

                $customerUser = new CustomerUser();
                $customerUser->setEmail(strtolower($firstName . '.' . $lastName . '@' . str_replace(' ', '', $customer->getCompanyName()) . '.com'))
                    ->setFirstName($firstName)
                    ->setLastName($lastName)
                    ->setPhone('+48' . rand(100000000, 999999999))
                    ->setCustomerRole($i === 0 ? 'owner' : $roles[array_rand($roles)])
                    ->setStatus(rand(0, 10) > 1 ? 'active' : 'pending')
                    ->setCustomer($customer)
                    ->setEmailVerifiedAt(new \DateTime())
                    ->setCreatedAt((new \DateTime())->modify('-' . rand(1, 90) . ' days'))
                    ->setLastLoginAt((new \DateTime())->modify('-' . rand(1, 7) . ' days'));

                $hashedPassword = $this->passwordHasher->hashPassword($customerUser, 'customer123');
                $customerUser->setPassword($hashedPassword);

                $this->customerUsers[] = $customerUser;
                $manager->persist($customerUser);
            }
        }
    }

    private function createOrders(ObjectManager $manager): void
    {
        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'canceled'];
        $statusWeights = [10, 15, 20, 25, 25, 5]; // Weight distribution
        $products = [
            ['Laptop Dell Inspiron', 'Electronics', 2500.00, 2.5],
            ['Smartphone Samsung', 'Electronics', 1200.00, 0.3],
            ['Office Chair', 'Furniture', 450.00, 8.0],
            ['Desk Lamp', 'Furniture', 120.00, 1.2],
            ['Wireless Mouse', 'Electronics', 60.00, 0.2],
            ['Bluetooth Speaker', 'Electronics', 180.00, 0.8],
            ['Coffee Mug Set', 'Kitchen', 45.00, 1.5],
            ['Notebook A4', 'Office', 15.00, 0.5],
            ['External HDD 1TB', 'Electronics', 300.00, 0.6],
            ['Gaming Keyboard', 'Electronics', 220.00, 1.1]
        ];

        // Create 80 orders with varying dates
        for ($i = 0; $i < 80; $i++) {
            $customer = $this->customers[array_rand($this->customers)];
            $customerUser = null;

            // Find a customer user for this customer if it's a business
            if ($customer->getType() === 'business') {
                foreach ($this->customerUsers as $cu) {
                    if ($cu->getCustomer() === $customer) {
                        $customerUser = $cu;
                        break;
                    }
                }
            }

            $order = new Order();
            $order->setCustomer($customer)
                ->setCreatedBy($customerUser);

            // Set status based on weights
            $status = $this->getWeightedRandomValue($statuses, $statusWeights);
            $order->setStatus($status);

            // Add 1-5 items to each order
            $itemCount = rand(1, 5);
            $totalAmount = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products[array_rand($products)];
                $quantity = rand(1, 3);
                $unitPrice = $product[2] * (0.8 + (rand(0, 40) / 100)); // ±20% price variation

                $orderItem = new OrderItem();
                $orderItem->setOrder($order)
                    ->setName($product[0])
                    ->setDescription('High quality ' . $product[1] . ' item')
                    ->setSku('SKU-' . rand(100000, 999999))
                    ->setQuantity($quantity)
                    ->setUnitPrice((string) $unitPrice)
                    ->setWeight((string) $product[3])
                    ->setWidth((string) rand(10, 50))
                    ->setHeight((string) rand(5, 30))
                    ->setLength((string) rand(15, 60));

                $totalAmount += $unitPrice * $quantity;
                $order->addItem($orderItem);
                $manager->persist($orderItem);
            }

            $order->setTotalAmount((string) $totalAmount);
            $this->orders[] = $order;
            $manager->persist($order);
        }
    }

    private function createShipments(ObjectManager $manager): void
    {
        $statuses = ['created', 'dispatched', 'in_transit', 'delivered', 'canceled'];
        $serviceTypes = ['standard', 'express', 'economy'];
        $cities = ['Warszawa', 'Kraków', 'Gdańsk', 'Wrocław', 'Poznań', 'Łódź', 'Katowice', 'Lublin'];

        // Create shipments for shipped and delivered orders
        foreach ($this->orders as $order) {
            if (in_array($order->getStatus(), ['shipped', 'delivered'])) {
                $shipment = new Shipment();
                $courierService = $this->courierServices[array_rand($this->courierServices)];

                $shipment->setOrder($order)
                    ->setTrackingNumber($this->generateTrackingNumber($courierService->getCode()))
                    ->setCourierService($courierService->getCode())
                    ->setStatus($order->getStatus() === 'delivered' ? 'delivered' : $statuses[array_rand($statuses)])
                    ->setSenderName('SkyBroker Warehouse')
                    ->setSenderEmail('warehouse@skybroker.com')
                    ->setSenderAddress('ul. Magazynowa 123')
                    ->setSenderPostalCode('00-001')
                    ->setSenderCity('Warszawa')
                    ->setSenderCountry('Poland')
                    ->setSenderPhone('+48123456789')
                    ->setRecipientName($order->getCustomer()->getCompanyName())
                    ->setRecipientEmail($order->getCustomer()->getEmail())
                    ->setRecipientAddress($order->getCustomer()->getAddress())
                    ->setRecipientPostalCode($order->getCustomer()->getPostalCode())
                    ->setRecipientCity($order->getCustomer()->getCity())
                    ->setRecipientCountry($order->getCustomer()->getCountry())
                    ->setRecipientPhone($order->getCustomer()->getPhone())
                    ->setTotalWeight((string) (rand(100, 5000) / 100)) // 1-50 kg
                    ->setTotalValue($order->getTotalAmount())
                    ->setServiceType($serviceTypes[array_rand($serviceTypes)])
                    ->setShippingCost((string) rand(15, 150));

                if (rand(0, 4) === 0) { // 20% chance of COD
                    $shipment->setCodAmount($order->getTotalAmount());
                }

                if (rand(0, 9) === 0) { // 10% chance of insurance
                    $shipment->setInsuranceAmount((string) (floatval($order->getTotalAmount()) * 0.1));
                }

                $manager->persist($shipment);
            }
        }

        // Additional shipments will be created only for orders that are shipped/delivered
    }

    private function createTransactions(ObjectManager $manager): void
    {
        $types = ['payment', 'refund', 'credit_top_up'];
        $statuses = ['pending', 'completed', 'failed'];
        $statusWeights = [10, 80, 10];
        $paymentMethods = ['paynow', 'stripe', 'credit', 'wallet'];

        // Create transactions for orders
        foreach ($this->orders as $order) {
            if (in_array($order->getStatus(), ['confirmed', 'processing', 'shipped', 'delivered'])) {
                $transaction = new Transaction();
                $transaction->setCustomer($order->getCustomer())
                    ->setOrder($order)
                    ->setAmount($order->getTotalAmount())
                    ->setCurrency('PLN')
                    ->setType('payment')
                    ->setStatus($this->getWeightedRandomValue($statuses, $statusWeights))
                    ->setPaymentMethod($paymentMethods[array_rand($paymentMethods)])
                    ->setDescription('Payment for order ' . $order->getOrderNumber())
                    ->setCreatedAt($order->getCreatedAt());

                if ($transaction->getStatus() === 'completed') {
                    $transaction->setCompletedAt($order->getCreatedAt());
                }

                $manager->persist($transaction);
            }
        }

        // Create some additional transactions
        for ($i = 0; $i < 30; $i++) {
            $customer = $this->customers[array_rand($this->customers)];
            $type = $types[array_rand($types)];
            $amount = match($type) {
                'credit_top_up' => rand(100, 1000),
                'refund' => rand(50, 500),
                default => rand(100, 2000)
            };

            $transaction = new Transaction();
            $transaction->setCustomer($customer)
                ->setAmount((string) $amount)
                ->setCurrency('PLN')
                ->setType($type)
                ->setStatus($this->getWeightedRandomValue($statuses, $statusWeights))
                ->setPaymentMethod($paymentMethods[array_rand($paymentMethods)])
                ->setDescription(ucfirst(str_replace('_', ' ', $type)) . ' transaction')
                ->setCreatedAt((new \DateTime())->modify('-' . rand(1, 90) . ' days'));

            if ($transaction->getStatus() === 'completed') {
                $transaction->setCompletedAt($transaction->getCreatedAt());
            }

            $manager->persist($transaction);
        }
    }

    private function createNotifications(ObjectManager $manager): void
    {
        $types = ['email', 'sms', 'system'];
        $statuses = ['pending', 'sent', 'failed', 'delivered'];
        $priorities = ['low', 'normal', 'high', 'urgent'];

        $templates = [
            'Order Confirmation' => 'Your order has been confirmed and is being processed.',
            'Shipment Dispatched' => 'Your shipment has been dispatched and is on the way.',
            'Delivery Notification' => 'Your package has been delivered successfully.',
            'Payment Received' => 'We have received your payment. Thank you!',
            'Account Update' => 'Your account information has been updated.',
            'System Maintenance' => 'Scheduled system maintenance notification.',
            'New Feature' => 'New features have been added to your account.',
            'Invoice Ready' => 'Your invoice is ready for download.'
        ];

        // Create notifications for customers
        for ($i = 0; $i < 150; $i++) {
            $customer = $this->customers[array_rand($this->customers)];
            $customerUser = null;

            // Find a customer user for this customer if it's a business
            if ($customer->getType() === 'business') {
                foreach ($this->customerUsers as $cu) {
                    if ($cu->getCustomer() === $customer) {
                        $customerUser = $cu;
                        break;
                    }
                }
            }

            $templateKeys = array_keys($templates);
            $subject = $templateKeys[array_rand($templateKeys)];
            $message = $templates[$subject];

            $notification = new Notification();
            $notification->setType($types[array_rand($types)])
                ->setCustomer($customer)
                ->setCustomerUser($customerUser)
                ->setSubject($subject)
                ->setMessage($message)
                ->setStatus($statuses[array_rand($statuses)])
                ->setPriority($priorities[array_rand($priorities)])
                ->setRead(rand(0, 1) === 1)
                ->setCreatedAt((new \DateTime())->modify('-' . rand(1, 30) . ' days'));

            if ($notification->getStatus() === 'sent') {
                $notification->setSentAt($notification->getCreatedAt());
            }

            if ($notification->isRead()) {
                $notification->setReadAt((new \DateTime())->modify('-' . rand(1, 7) . ' days'));
            }

            $manager->persist($notification);
        }
    }

    private function getWeightedRandomValue(array $values, array $weights): string
    {
        $totalWeight = array_sum($weights);
        $random = rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($values as $i => $value) {
            $currentWeight += $weights[$i];
            if ($random <= $currentWeight) {
                return $value;
            }
        }

        return $values[0];
    }

    private function generateTrackingNumber(string $courierCode): string
    {
        $prefix = match($courierCode) {
            'inpost' => 'IP',
            'dhl' => 'DHL',
            'ups' => 'UPS',
            default => 'TRK'
        };

        return $prefix . rand(100000000000, 999999999999);
    }
}