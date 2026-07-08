<?php

namespace App\Actions\Platform;

use App\Models\Platform\HostingConnection;
use App\Models\Platform\HostingEnvironment;
use App\Support\Hosting\HostingProviderManager;
use Illuminate\Support\Facades\DB;

class SyncHostingEnvironmentsAction
{
    public function __construct(private readonly HostingProviderManager $providers) {}

    /**
     * @return array{applications: int, environments: int}
     */
    public function handle(HostingConnection $connection): array
    {
        $provider = $this->providers->providerFor($connection);
        $applications = $provider->listApplications($connection);
        $environmentRows = [];

        foreach ($applications as $application) {
            $applicationId = (string) ($application['id'] ?? '');

            if ($applicationId === '') {
                continue;
            }

            foreach ($provider->listEnvironments($connection, $applicationId) as $environment) {
                $environmentId = (string) ($environment['id'] ?? '');

                if ($environmentId === '') {
                    continue;
                }

                $environmentRows[] = [
                    'application_id' => $applicationId,
                    'environment_id' => $environmentId,
                    'attributes' => is_array($environment['attributes'] ?? null) ? $environment['attributes'] : [],
                    'application_attributes' => is_array($application['attributes'] ?? null) ? $application['attributes'] : [],
                ];
            }
        }

        DB::connection('central')->transaction(function () use ($connection, $applications, $environmentRows): void {
            foreach ($environmentRows as $environmentRow) {
                $attributes = $environmentRow['attributes'];
                $applicationAttributes = $environmentRow['application_attributes'];

                HostingEnvironment::query()->updateOrCreate(
                    [
                        'hosting_connection_id' => $connection->id,
                        'provider_environment_id' => $environmentRow['environment_id'],
                    ],
                    [
                        'name' => $this->environmentName($attributes, $environmentRow['environment_id']),
                        'provider_application_id' => $environmentRow['application_id'],
                        'provider_region' => (string) ($applicationAttributes['region'] ?? ''),
                        'status' => 'synced',
                        'last_synced_at' => now(),
                        'metadata' => [
                            'application' => [
                                'id' => $environmentRow['application_id'],
                                'name' => $applicationAttributes['name'] ?? null,
                                'slug' => $applicationAttributes['slug'] ?? null,
                                'region' => $applicationAttributes['region'] ?? null,
                            ],
                            'environment' => [
                                'id' => $environmentRow['environment_id'],
                                'name' => $attributes['name'] ?? null,
                                'slug' => $attributes['slug'] ?? null,
                                'status' => $attributes['status'] ?? null,
                                'vanity_domain' => $attributes['vanity_domain'] ?? null,
                                'php_major_version' => $attributes['php_major_version'] ?? null,
                                'node_version' => $attributes['node_version'] ?? null,
                            ],
                        ],
                    ]
                );
            }

            $connection->forceFill([
                'status' => 'ready',
                'last_checked_at' => now(),
                'last_error' => null,
                'metadata' => array_merge($connection->metadata ?? [], [
                    'last_applications_count' => count($applications),
                    'last_environments_count' => count($environmentRows),
                ]),
            ])->save();
        });

        return [
            'applications' => count($applications),
            'environments' => count($environmentRows),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function environmentName(array $attributes, string $fallback): string
    {
        $name = trim((string) ($attributes['name'] ?? ''));

        return $name !== '' ? $name : $fallback;
    }
}
