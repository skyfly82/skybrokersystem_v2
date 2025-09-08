<?php

namespace App\Service;

use App\Entity\EmailVerificationCode;
use App\Entity\PreliminaryRegistration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment as Twig;

class RegistrationVerificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly Twig $twig,
        private readonly \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator,
    ) {}

    public function sendCode(PreliminaryRegistration $pre, int $ttlMinutes = 15, int $cooldownSeconds = 60, int $dailyLimit = 5): EmailVerificationCode
    {
        $repo = $this->em->getRepository(EmailVerificationCode::class);

        // Cooldown: deny if last code was generated too recently
        $last = $repo->findOneBy(['preToken' => $pre->getToken(), 'purpose' => 'registration'], ['id' => 'DESC']);
        if ($last) {
            $since = (new \DateTime())->getTimestamp() - $last->getCreatedAt()->getTimestamp();
            if ($since < $cooldownSeconds) {
                $retry = $cooldownSeconds - $since;
                throw new \RuntimeException('cooldown:'.$retry);
            }
        }

        // Daily limit per token
        $todayStart = (new \DateTime('today'));
        $conn = $this->em->getConnection();
        $count = (int)$conn->fetchOne(
            'SELECT COUNT(*) FROM v2_email_verification_codes WHERE pre_token = :t AND created_at >= :d',
            ['t' => $pre->getToken(), 'd' => $todayStart->format('Y-m-d 00:00:00')]
        );
        if ($count >= $dailyLimit) {
            throw new \RuntimeException('daily_limit');
        }

        $code = new EmailVerificationCode();
        $code->setEmail($pre->getEmail())
            ->setPreToken($pre->getToken())
            ->setPurpose('registration')
            ->setCode($this->generateCode())
            ->setExpiresAt((new \DateTime())->modify("+{$ttlMinutes} minutes"));

        $this->em->persist($code);
        $this->em->flush();

        $confirmUrl = $this->urlGenerator->generate('api_customer_register_confirm_link', [
            'token' => $pre->getToken(),
            'code' => $code->getCode(),
        ], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $html = $this->twig->render('emails/registration_confirmation.html.twig', [
            'name' => null,
            'code' => $code->getCode(),
            'expires_at' => $code->getExpiresAt(),
            'confirm_url' => $confirmUrl,
        ]);

        $email = (new Email())
            ->from('noreply@skybrokersystem.com')
            ->to($pre->getEmail())
            ->subject('Sky — Potwierdzenie rejestracji')
            ->html($html)
            ->text("Kod potwierdzający: {$code->getCode()} (ważny {$ttlMinutes} min)");

        $this->mailer->send($email);

        return $code;
    }

    public function verify(string $preToken, string $codeInput, int $maxAttempts = 5): bool
    {
        /** @var EmailVerificationCode|null $code */
        $code = $this->em->getRepository(EmailVerificationCode::class)
            ->findOneBy(['preToken' => $preToken, 'purpose' => 'registration'], ['id' => 'DESC']);
        if (!$code) {
            return false;
        }

        if ($code->getConsumedAt() !== null) {
            return false;
        }
        if ($code->getExpiresAt() < new \DateTime()) {
            return false;
        }
        if ($code->getAttempts() >= $maxAttempts) {
            return false;
        }

        // Increment attempts
        $code->incrementAttempts();

        if (hash_equals($code->getCode(), trim($codeInput))) {
            $code->setConsumedAt(new \DateTime());
            // mark preliminary as verified
            $pre = $this->em->getRepository(PreliminaryRegistration::class)->findOneBy(['token' => $preToken]);
            if ($pre) {
                $pre->setStatus('email_verified');
                $ref = new \ReflectionClass($pre);
                if ($ref->hasMethod('setEmailVerified')) {
                    $pre->setEmailVerified(true);
                }
                if ($ref->hasMethod('setEmailVerifiedAt')) {
                    $pre->setEmailVerifiedAt(new \DateTime());
                }
                $pre->setUpdatedAt(new \DateTime());
            }
            $this->em->flush();
            return true;
        }

        $this->em->flush();
        return false;
    }

    private function generateCode(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
