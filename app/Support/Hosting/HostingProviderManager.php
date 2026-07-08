<?php

namespace App\Support\Hosting;

use App\Models\Platform\HostingConnection;

class HostingProviderManager
{
    public function __construct(private readonly LaravelCloudHostingProvider $laravelCloudProvider) {}

    public function providerFor(HostingConnection $connection): HostingProviderInterface
    {
        return match ((string) $connection->provider) {
            'laravel_cloud' => $this->laravelCloudProvider,
            default => throw new HostingProviderException('Unsupported hosting provider.'),
        };
    }
}
