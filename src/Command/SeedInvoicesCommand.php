<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed-test-invoices', description: 'Insert test invoices with random NIPs and amounts')]
class SeedInvoicesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('count', InputArgument::OPTIONAL, 'How many invoices to create', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = (int) $input->getArgument('count');

        $sellerName = 'BL LOGISTICS SP. Z O.O.';
        $sellerAddress = 'ul. Czerska 8/10, 00-732 Warszawa';
        $sellerNip = 'PL 5213971463';
        $sellerBank = 'mBank S.A.';
        $sellerIban = '39114011400000390287001001';

        $now = new \DateTimeImmutable();

        for ($i = 1; $i <= $count; $i++) {
            $inv = new Invoice();
            $inv
                ->setNumber(sprintf('FS/%03d/%02d/%04d', $i, (int)$now->format('m'), (int)$now->format('Y')))
                ->setIssueDate($now)
                ->setSellDate($now)
                ->setPaymentDueDate($now->modify('+7 days'))
                ->setPaymentMethod('przelew')
                ->setSellerName($sellerName)
                ->setSellerAddress($sellerAddress)
                ->setSellerNip($sellerNip)
                ->setSellerBank($sellerBank)
                ->setSellerIban($sellerIban)
                ->setBuyerName('Klient Testowy #' . $i)
                ->setBuyerAddress('ul. Testowa ' . $i . ', 00-0' . $i . ' Miasto')
                ->setBuyerNip($this->randomNip())
                ->setPaidAmount(0.0)
            ;

            $itemsCount = random_int(1, 5);
            for ($lp = 1; $lp <= $itemsCount; $lp++) {
                $qty = random_int(1, 5);
                $unit = random_int(100, 5000) / 100; // 1.00 - 50.00
                $item = (new InvoiceItem())
                    ->setLp($lp)
                    ->setName('Usługa transportowa #' . $lp)
                    ->setCode($lp % 2 === 0 ? '49.41.13.0' : '-')
                    ->setQty((float)$qty)
                    ->setJm('szt')
                    ->setVat(23)
                    ->setUnitBrutto((float)$unit)
                    ->setTotalBrutto($unit * $qty);
                $inv->addItem($item);
            }

            $this->em->persist($inv);
            if ($i % 20 === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();

        $output->writeln(sprintf('Seeded %d invoices.', $count));
        return Command::SUCCESS;
    }

    private function randomNip(): string
    {
        // generate a simple pseudo NIP-like number (not validated checksum)
        $digits = [];
        for ($i = 0; $i < 10; $i++) { $digits[] = (string) random_int(0, 9); }
        return implode('', $digits);
    }
}

