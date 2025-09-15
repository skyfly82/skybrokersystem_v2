<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Customer;
use App\Entity\CustomerUser;
use App\Entity\Order;
use App\Entity\Transaction;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-sample-data',
    description: 'Creates sample data for testing the dashboard',
)]
class CreateSampleDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Creating sample data for testing...');

        $this->createSampleOrders($io);
        $this->createSampleTransactions($io);
        $this->createSampleNotifications($io);

        $this->entityManager->flush();

        $io->success('Sample data created successfully!');

        return Command::SUCCESS;
    }

    private function createSampleOrders(SymfonyStyle $io): void
    {
        $customers = $this->entityManager->getRepository(Customer::class)->findAll();

        if (empty($customers)) {
            $io->warning('No customers found. Creating sample customers first...');
            $this->createSampleCustomers();
            $customers = $this->entityManager->getRepository(Customer::class)->findAll();
        }

        $orderStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'canceled'];

        for ($i = 1; $i <= 20; $i++) {
            $order = new Order();
            $order->setOrderNumber('ORD-' . sprintf('%06d', $i));
            $order->setCustomer($customers[array_rand($customers)]);
            $order->setStatus($orderStatuses[array_rand($orderStatuses)]);
            $order->setTotalAmount(rand(50, 500) + (rand(0, 99) / 100));
            $order->setCurrency('PLN');
            $order->setCreatedAt(new \DateTime('-' . rand(1, 30) . ' days'));

            $this->entityManager->persist($order);
        }

        $io->note('Created 20 sample orders');
    }

    private function createSampleTransactions(SymfonyStyle $io): void
    {
        $customers = $this->entityManager->getRepository(Customer::class)->findAll();

        $transactionTypes = ['payment', 'refund'];
        $transactionStatuses = ['completed', 'pending', 'failed'];
        $paymentMethods = ['card', 'bank_transfer', 'paynow'];

        for ($i = 1; $i <= 30; $i++) {
            $transaction = new Transaction();
            $transaction->setTransactionId('TXN-' . sprintf('%08d', $i));
            $transaction->setCustomer($customers[array_rand($customers)]);
            $transaction->setType($transactionTypes[array_rand($transactionTypes)]);
            $transaction->setStatus($transactionStatuses[array_rand($transactionStatuses)]);
            $transaction->setPaymentMethod($paymentMethods[array_rand($paymentMethods)]);
            $transaction->setAmount(rand(25, 750) + (rand(0, 99) / 100));
            $transaction->setCurrency('PLN');
            $transaction->setCreatedAt(new \DateTime('-' . rand(1, 45) . ' days'));

            if ($transaction->getStatus() === 'completed') {
                $transaction->setCompletedAt(clone $transaction->getCreatedAt());
                $transaction->getCompletedAt()->modify('+' . rand(1, 60) . ' minutes');
            }

            $this->entityManager->persist($transaction);
        }

        $io->note('Created 30 sample transactions');
    }

    private function createSampleNotifications(SymfonyStyle $io): void
    {
        $customers = $this->entityManager->getRepository(Customer::class)->findAll();
        $customerUsers = $this->entityManager->getRepository(CustomerUser::class)->findAll();

        $notificationTypes = ['email', 'sms', 'push'];
        $notificationStatuses = ['sent', 'pending', 'failed'];
        $priorities = ['low', 'normal', 'high', 'urgent'];

        $subjects = [
            'Order Confirmation',
            'Shipment Update',
            'Payment Received',
            'Invoice Generated',
            'Account Update',
            'Security Alert',
        ];

        for ($i = 1; $i <= 50; $i++) {
            $notification = new Notification();
            $notification->setType($notificationTypes[array_rand($notificationTypes)]);
            $notification->setSubject($subjects[array_rand($subjects)]);
            $notification->setMessage('This is a sample notification message for testing purposes.');
            $notification->setStatus($notificationStatuses[array_rand($notificationStatuses)]);
            $notification->setPriority($priorities[array_rand($priorities)]);
            $notification->setRead(rand(0, 1) === 1);

            if (!empty($customers)) {
                $notification->setCustomer($customers[array_rand($customers)]);
            }

            if (!empty($customerUsers)) {
                $notification->setCustomerUser($customerUsers[array_rand($customerUsers)]);
            }

            $notification->setCreatedAt(new \DateTime('-' . rand(1, 30) . ' days'));

            if ($notification->getStatus() === 'sent') {
                $notification->setSentAt(clone $notification->getCreatedAt());
                $notification->getSentAt()->modify('+' . rand(1, 30) . ' minutes');
            }

            $this->entityManager->persist($notification);
        }

        $io->note('Created 50 sample notifications');
    }

    private function createSampleCustomers(): void
    {
        $customer1 = new Customer();
        $customer1->setType('business');
        $customer1->setCompanyName('Sample Corp Ltd.');
        $customer1->setEmail('sample@example.com');
        $customer1->setPhone('+48123456789');
        $customer1->setStatus('active');
        $customer1->setCity('Warsaw');
        $customer1->setCountry('Poland');
        $customer1->setCreatedAt(new \DateTime('-15 days'));

        $customer2 = new Customer();
        $customer2->setType('individual');
        $customer2->setCompanyName('Jan Kowalski');
        $customer2->setEmail('jan@example.com');
        $customer2->setPhone('+48987654321');
        $customer2->setStatus('active');
        $customer2->setCity('Krakow');
        $customer2->setCountry('Poland');
        $customer2->setCreatedAt(new \DateTime('-20 days'));

        $this->entityManager->persist($customer1);
        $this->entityManager->persist($customer2);
        $this->entityManager->flush();
    }
}