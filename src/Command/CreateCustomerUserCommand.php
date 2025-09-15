<?php

namespace App\Command;

use App\Entity\Customer;
use App\Entity\CustomerUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'create-customer-user',
    description: 'Creates a customer user with test company',
)]
class CreateCustomerUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create test customer company
        $customer = new Customer();
        $customer->setCompanyName('Test Company');
        $customer->setType('business');
        $customer->setEmail('test@test.com');
        $customer->setPhone('+48123456789');
        $customer->setAddress('Test Street 1');
        $customer->setPostalCode('00-001');
        $customer->setCity('Warsaw');
        $customer->setCountry('Poland');
        $customer->setVatNumber('PL1234567890');

        $this->entityManager->persist($customer);

        // Create customer user
        $customerUser = new CustomerUser();
        $customerUser->setEmail('test@test.com');
        $customerUser->setFirstName('Test');
        $customerUser->setLastName('User');
        $customerUser->setCustomerRole('owner');
        $customerUser->setCustomer($customer);
        
        $hashedPassword = $this->passwordHasher->hashPassword($customerUser, 'test1234');
        $customerUser->setPassword($hashedPassword);
        
        $customerUser->setEmailVerifiedAt(new \DateTime());

        $this->entityManager->persist($customerUser);
        $this->entityManager->flush();

        $io->success('Customer user created successfully!');
        $io->table(['Field', 'Value'], [
            ['Email', 'test@test.com'],
            ['Password', 'test1234'],
            ['Role', 'owner'],
            ['Company', 'Test Company'],
            ['Login URL', '/customer/login']
        ]);

        return Command::SUCCESS;
    }
}
