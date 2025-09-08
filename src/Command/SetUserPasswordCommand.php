<?php

namespace App\Command;

use App\Entity\CustomerUser;
use App\Entity\SystemUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:set-user-password',
    description: 'Set password for a user (customer or system) by email',
)]
class SetUserPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('password', InputArgument::REQUIRED, 'New password')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'user type: customer|system', 'customer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string)$input->getArgument('email');
        $password = (string)$input->getArgument('password');
        $type = (string)$input->getOption('type');

        $repoClass = $type === 'system' ? SystemUser::class : CustomerUser::class;
        $user = $this->em->getRepository($repoClass)->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error('User not found');
            return Command::FAILURE;
        }

        $hash = $this->hasher->hashPassword($user, $password);
        $user->setPassword($hash);
        $this->em->flush();
        $io->success('Password updated for '.$email);
        return Command::SUCCESS;
    }
}

