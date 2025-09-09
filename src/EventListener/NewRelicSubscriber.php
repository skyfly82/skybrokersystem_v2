<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class NewRelicSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 2048],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!\extension_loaded('newrelic')) {
            return;
        }
        if (!filter_var((string)($_ENV['NEWRELIC_ENABLED'] ?? $_SERVER['NEWRELIC_ENABLED'] ?? 'false'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route') ?: $request->getPathInfo();
        if (function_exists('newrelic_name_transaction')) {
            \newrelic_name_transaction($route);
        }
        if (function_exists('newrelic_add_custom_parameter')) {
            \newrelic_add_custom_parameter('method', $request->getMethod());
            \newrelic_add_custom_parameter('endpoint', $request->getPathInfo());
        }
    }
}

