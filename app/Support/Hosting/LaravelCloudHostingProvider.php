<?php

namespace App\Support\Hosting;

use App\Models\Platform\HostingConnection;
use Throwable;

class LaravelCloudHostingProvider implements HostingProviderInterface
{
    public function __construct(private readonly LaravelCloudApiClient $client) {}

    public function testConnection(HostingConnection $connection): array
    {
        try {
            $applications = $this->listApplications($connection);

            return [
                'ok' => true,
                'message' => __('admin_common_ui.platform.hosting.connection_test.success'),
                'applications_count' => count($applications),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => __('admin_common_ui.platform.hosting.connection_test.failed'),
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function listApplications(HostingConnection $connection): array
    {
        $payload = $this->client->get($connection, '/applications', [
            'include' => 'defaultEnvironment',
        ]);

        $data = $payload['data'] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    public function listEnvironments(HostingConnection $connection, string $applicationId): array
    {
        $payload = $this->client->get($connection, '/applications/'.rawurlencode($applicationId).'/environments');

        $data = $payload['data'] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    public function syncEnvironmentVariables(HostingConnection $connection, string $applicationId, string $environmentId, array $variables): array
    {
        $path = '/environments/'.rawurlencode($environmentId);
        $this->client->get($connection, $path);
        $appliedVariables = $this->settableEnvironmentVariables($variables);

        $this->client->post($connection, $path.'/variables', [
            'method' => 'append',
            'variables' => array_map(
                fn (array $variable): array => [
                    'key' => (string) $variable['key'],
                    'value' => (string) $variable['value'],
                ],
                $appliedVariables,
            ),
        ]);

        return [
            'updated' => count($appliedVariables),
            'keys' => collect($appliedVariables)->pluck('key')->map(fn (mixed $key): string => (string) $key)->values()->all(),
        ];
    }

    public function ensureDomain(HostingConnection $connection, string $applicationId, string $environmentId, string $domain): array
    {
        $basePath = '/environments/'.rawurlencode($environmentId);
        $environment = $this->client->get($connection, $basePath);
        $existingDomain = $this->findDomain($environment, $domain);
        $action = 'existing';

        if ($existingDomain === null) {
            $created = $this->client->post($connection, $basePath.'/domains', [
                'domain' => $domain,
            ]);
            $existingDomain = is_array($created['data'] ?? null) ? $created['data'] : null;
            $action = 'created';
        }

        $domainId = is_array($existingDomain) ? (string) ($existingDomain['id'] ?? '') : '';
        $verification = 'pending_verification';

        if ($domainId !== '') {
            $this->client->post($connection, $basePath.'/domains/'.rawurlencode($domainId).'/verify');
            $verification = 'requested';
        }

        return [
            'domain' => $domain,
            'action' => $action,
            'domain_id' => $domainId !== '' ? $domainId : null,
            'verification' => $verification,
        ];
    }

    public function startDeployment(HostingConnection $connection, string $applicationId, string $environmentId): array
    {
        $payload = $this->client->post($connection, '/environments/'.rawurlencode($environmentId).'/deployments');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        return [
            'deployment_id' => isset($data['id']) ? (string) $data['id'] : null,
            'status' => isset($attributes['status']) ? (string) $attributes['status'] : null,
        ];
    }

    public function runCommand(HostingConnection $connection, string $environmentId, string $command): array
    {
        $payload = $this->client->post($connection, '/environments/'.rawurlencode($environmentId).'/commands', [
            'command' => $command,
        ]);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        return [
            'command_id' => isset($data['id']) ? (string) $data['id'] : null,
            'status' => isset($attributes['status']) ? (string) $attributes['status'] : null,
        ];
    }

    public function getCommand(HostingConnection $connection, string $commandId): array
    {
        $payload = $this->client->get($connection, '/commands/'.rawurlencode($commandId));
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        return [
            'command_id' => isset($data['id']) ? (string) $data['id'] : null,
            'status' => isset($attributes['status']) ? (string) $attributes['status'] : null,
            'exit_code' => isset($attributes['exit_code']) ? (int) $attributes['exit_code'] : null,
            'output' => isset($attributes['output']) ? (string) $attributes['output'] : null,
        ];
    }

    public function getEnvironmentDatabase(HostingConnection $connection, string $applicationId, string $environmentId): array
    {
        $payload = $this->client->get($connection, '/environments/'.rawurlencode($environmentId), [
            'include' => 'database',
        ]);

        return $this->environmentDatabaseFromPayload($payload);
    }

    public function createDatabaseCluster(HostingConnection $connection, string $name, string $region, string $type, array $config): array
    {
        $payload = $this->client->post($connection, '/databases/clusters', [
            'type' => $type,
            'name' => $name,
            'region' => $region,
            'config' => $config,
        ]);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
        $schemaIdentifier = collect(data_get($payload, 'data.relationships.databases.data', []))
            ->first(fn (mixed $resource): bool => is_array($resource) && isset($resource['id']));
        $schema = collect(is_array($payload['included'] ?? null) ? $payload['included'] : [])
            ->first(fn (mixed $resource): bool => is_array($resource) && (string) ($resource['type'] ?? '') === 'databaseSchemas');

        return [
            'database_id' => isset($data['id']) ? (string) $data['id'] : null,
            'schema_id' => is_array($schemaIdentifier) && isset($schemaIdentifier['id'])
                ? (string) $schemaIdentifier['id']
                : (is_array($schema) && isset($schema['id']) ? (string) $schema['id'] : null),
            'name' => isset($attributes['name']) ? (string) $attributes['name'] : null,
            'type' => isset($attributes['type']) ? (string) $attributes['type'] : null,
            'status' => isset($attributes['status']) ? (string) $attributes['status'] : null,
            'region' => isset($attributes['region']) ? (string) $attributes['region'] : null,
        ];
    }

    public function listDatabaseSchemas(HostingConnection $connection, string $databaseId): array
    {
        $payload = $this->client->get($connection, '/databases/clusters/'.rawurlencode($databaseId).'/databases');

        return collect(is_array($payload['data'] ?? null) ? $payload['data'] : [])
            ->filter(fn (mixed $resource): bool => is_array($resource))
            ->map(fn (array $resource): array => [
                'id' => isset($resource['id']) ? (string) $resource['id'] : null,
                'name' => is_string(data_get($resource, 'attributes.name')) ? data_get($resource, 'attributes.name') : null,
                'status' => is_string(data_get($resource, 'attributes.status')) ? data_get($resource, 'attributes.status') : null,
            ])
            ->values()
            ->all();
    }

    public function attachDatabaseToEnvironment(HostingConnection $connection, string $environmentId, string $databaseSchemaId): array
    {
        $payload = $this->client->patch($connection, '/environments/'.rawurlencode($environmentId), [
            'database_schema_id' => $databaseSchemaId,
        ]);

        return $this->environmentDatabaseFromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{database_id: string|null, schema_id: string|null, name: string|null, type: string|null, status: string|null}
     */
    private function environmentDatabaseFromPayload(array $payload): array
    {
        $schemaIdentifier = data_get($payload, 'data.relationships.database.data');

        if (! is_array($schemaIdentifier) || ! isset($schemaIdentifier['id'])) {
            return [
                'database_id' => null,
                'schema_id' => null,
                'name' => null,
                'type' => null,
                'status' => null,
            ];
        }

        $schemaId = (string) $schemaIdentifier['id'];
        $included = collect(is_array($payload['included'] ?? null) ? $payload['included'] : []);
        $schema = $included->first(fn (mixed $resource): bool => is_array($resource) && (string) ($resource['id'] ?? '') === $schemaId);
        $schemaAttributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $clusterIdentifier = is_array($schema) ? data_get($schema, 'relationships.database.data') : null;
        $clusterId = is_array($clusterIdentifier) && isset($clusterIdentifier['id']) ? (string) $clusterIdentifier['id'] : $schemaId;
        $cluster = $included->first(fn (mixed $resource): bool => is_array($resource) && (string) ($resource['id'] ?? '') === $clusterId);
        $clusterAttributes = is_array($cluster['attributes'] ?? null) ? $cluster['attributes'] : [];

        return [
            'database_id' => $clusterId,
            'schema_id' => $schemaId,
            'name' => isset($schemaAttributes['name']) ? (string) $schemaAttributes['name'] : (isset($clusterAttributes['name']) ? (string) $clusterAttributes['name'] : null),
            'type' => isset($clusterAttributes['type']) ? (string) $clusterAttributes['type'] : null,
            'status' => isset($schemaAttributes['status']) ? (string) $schemaAttributes['status'] : (isset($clusterAttributes['status']) ? (string) $clusterAttributes['status'] : null),
        ];
    }

    /**
     * @param  array<int, array{key: string, value: string|null, action?: string}>  $plannedVariables
     * @return array<int, array{key: string, value: string|null, action?: string}>
     */
    private function settableEnvironmentVariables(array $plannedVariables): array
    {
        return collect($plannedVariables)
            ->filter(fn (array $variable): bool => (string) ($variable['key'] ?? '') !== '')
            ->filter(fn (array $variable): bool => ($variable['action'] ?? 'set') === 'set')
            ->filter(fn (array $variable): bool => array_key_exists('value', $variable) && $variable['value'] !== null)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function findDomain(array $payload, string $domain): ?array
    {
        $candidates = [];

        if (is_array($payload['included'] ?? null)) {
            $candidates = array_merge($candidates, $payload['included']);
        }

        if (is_array($payload['data']['relationships']['domains']['data'] ?? null)) {
            $candidates = array_merge($candidates, $payload['data']['relationships']['domains']['data']);
        }

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidateDomain = (string) ($candidate['attributes']['domain'] ?? $candidate['attributes']['name'] ?? $candidate['domain'] ?? '');

            if (strcasecmp($candidateDomain, $domain) === 0) {
                return $candidate;
            }
        }

        return null;
    }
}
