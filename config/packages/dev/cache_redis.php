<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $redisUrl = $_ENV['REDIS_URL'] ?? $_SERVER['REDIS_URL'] ?? null;
    if (!$redisUrl) {
        return; // do nothing if REDIS_URL not set
    }

    $container->extension('framework', [
        'cache' => [
            'app' => 'cache.adapter.redis',
            'default_redis_provider' => '%env(REDIS_URL)%',
        ],
    ]);
};

