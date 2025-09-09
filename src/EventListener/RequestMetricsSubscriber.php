<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AnalyticsEvent;
use App\Repository\AnalyticsEventRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

class RequestMetricsSubscriber implements EventSubscriberInterface
{
    private const REQ_START_ATTR = '_req_start_time';

    public function __construct(
        private readonly AnalyticsEventRepository $repo,
        private readonly Security $security,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 1024],
            KernelEvents::RESPONSE => ['onResponse', -1024],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        // Skip profiler and static
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/_profiler') || str_starts_with($path, '/_wdt') || str_starts_with($path, '/_assets')) {
            return;
        }
        $request->attributes->set(self::REQ_START_ATTR, microtime(true));
        // ensure session id if any
        if (!$request->hasSession() || !$request->getSession()->isStarted()) {
            return;
        }
        if (!$request->getSession()->has('sid')) {
            $request->getSession()->set('sid', Uuid::v4()->toRfc4122());
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $start = $request->attributes->get(self::REQ_START_ATTR);
        if (!$start) {
            return;
        }
        $duration = (int) (1000 * (microtime(true) - (float) $start));
        $response = $event->getResponse();

        $tokenUser = $this->security->getUser();
        $userId = null;
        $userType = null;
        if ($tokenUser) {
            // Try to detect if it's system user vs customer by class name
            $class = (new \ReflectionClass($tokenUser))->getShortName();
            $userType = str_contains(strtolower($class), 'system') ? 'system' : 'customer';
            if (method_exists($tokenUser, 'getId')) {
                $userId = $tokenUser->getId();
            }
        }

        $sessionId = $request->hasSession() && $request->getSession()->has('sid') ? (string) $request->getSession()->get('sid') : null;
        $event = (new AnalyticsEvent())
            ->setType('request')
            ->setName(null)
            ->setEndpoint($request->getPathInfo())
            ->setMethod($request->getMethod())
            ->setStatusCode($response->getStatusCode())
            ->setDurationMs($duration)
            ->setIp($this->getClientIp($request))
            ->setUserAgent($request->headers->get('User-Agent'))
            ->setSessionId($sessionId)
            ->setUserId($userId)
            ->setUserType($userType);
        $this->repo->save($event, true);
    }

    private function getClientIp(Request $request): ?string
    {
        $ip = $request->headers->get('X-Forwarded-For') ?? $request->getClientIp();
        if (!$ip) {
            return null;
        }
        // If multiple are provided, take first
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return $ip;
    }
}
