<?php

namespace App\Support\Hosting;

use App\Models\Platform\HostingConnection;

interface HostingProviderInterface
{
    /**
     * @return array{ok: bool, message: string, error?: string, applications_count?: int}
     */
    public function testConnection(HostingConnection $connection): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listApplications(HostingConnection $connection): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEnvironments(HostingConnection $connection, string $applicationId): array;

    /**
     * @param  array<int, array{key: string, value: string|null, action?: string}>  $variables
     * @return array{updated: int, keys: array<int, string>}
     */
    public function syncEnvironmentVariables(HostingConnection $connection, string $applicationId, string $environmentId, array $variables): array;

    /**
     * @return array{domain: string, action: string, domain_id: string|null, verification: string}
     */
    public function ensureDomain(HostingConnection $connection, string $applicationId, string $environmentId, string $domain): array;

    /**
     * @return array{deployment_id: string|null, status: string|null}
     */
    public function startDeployment(HostingConnection $connection, string $applicationId, string $environmentId): array;

    /**
     * @return array{command_id: string|null, status: string|null}
     */
    public function runCommand(HostingConnection $connection, string $environmentId, string $command): array;

    /**
     * @return array{command_id: string|null, status: string|null, exit_code: int|null, output: string|null}
     */
    public function getCommand(HostingConnection $connection, string $commandId): array;

    /**
     * @return array{database_id: string|null, name: string|null, type: string|null, status: string|null}
     */
    public function getEnvironmentDatabase(HostingConnection $connection, string $applicationId, string $environmentId): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array{database_id: string|null, schema_id: string|null, name: string|null, type: string|null, status: string|null, region: string|null}
     */
    public function createDatabaseCluster(HostingConnection $connection, string $name, string $region, string $type, array $config): array;

    /**
     * @return array<int, array{id: string|null, name: string|null, status: string|null}>
     */
    public function listDatabaseSchemas(HostingConnection $connection, string $databaseId): array;

    /**
     * @return array{database_id: string|null, schema_id: string|null, name: string|null, type: string|null, status: string|null}
     */
    public function attachDatabaseToEnvironment(HostingConnection $connection, string $environmentId, string $databaseSchemaId): array;
}
