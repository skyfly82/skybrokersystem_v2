<?php

namespace App\Command;

use App\Entity\SystemUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'create-admin-user',
    description: 'Creates an admin user with super admin privileges',
)]
class CreateAdminUserCommand extends Command
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

        // Create admin user
        $admin = new SystemUser();
        $admin->setEmail('admin@test.com');
        $admin->setFirstName('Super');
        $admin->setLastName('Admin');
        $admin->setDepartment('admin');
        $admin->setPosition('Super Administrator');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'test1234');
        $admin->setPassword($hashedPassword);
        
        $admin->setEmailVerifiedAt(new \DateTime());

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success('Admin user created successfully!');
        $io->table(['Field', 'Value'], [
            ['Email', 'admin@test.com'],
            ['Password', 'test1234'],
            ['Department', 'admin'],
            ['Roles', 'ROLE_ADMIN, ROLE_SUPER_ADMIN'],
            ['Login URL', '/admin/login']
        ]);

        return Command::SUCCESS;
    }
}
