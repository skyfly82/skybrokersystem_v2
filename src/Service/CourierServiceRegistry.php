<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Registry for all courier service clients
 */
class CourierServiceRegistry
{
    private array $clients = [];

    public function __construct(iterable $clients)
    {
        foreach ($clients as $client) {
            $this->addClient($client);
        }
    }

    public function addClient(object $client): void
    {
        $className = get_class($client);
        $serviceName = $this->extractServiceName($className);
        $this->clients[$serviceName] = $client;
    }

    public function getClient(string $serviceName): ?object
    {
        return $this->clients[$serviceName] ?? null;
    }

    public function getInPostClient(): ?InPostApiClient
    {
        return $this->getClient('inpost');
    }

    public function getAllClients(): array
    {
        return $this->clients;
    }

    public function getAvailableServices(): array
    {
        return array_keys($this->clients);
    }

    private function extractServiceName(string $className): string
    {
        $parts = explode('\\', $className);
        $serviceName = end($parts);
        
        // Convert InPostApiClient -> inpost, DhlApiClient -> dhl, etc.
        $serviceName = strtolower(str_replace(['ApiClient', 'Client'], '', $serviceName));
        
        return $serviceName;
    }
}