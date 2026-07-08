<?php

namespace App\Support\Hosting;

use App\Models\Platform\HostingConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class LaravelCloudApiClient
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(HostingConnection $connection, string $path, array $query = []): array
    {
        $response = $this->request($connection)->get($this->normalizePath($path), $query);

        return $this->payload($response->status(), $response->json(), $response->json('message'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patch(HostingConnection $connection, string $path, array $data = []): array
    {
        $response = $this->request($connection)->patch($this->normalizePath($path), $data);

        return $this->payload($response->status(), $response->json(), $response->json('message'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(HostingConnection $connection, string $path, array $data = []): array
    {
        $response = $this->request($connection)->post($this->normalizePath($path), $data);

        return $this->payload($response->status(), $response->json(), $response->json('message'));
    }

    private function request(HostingConnection $connection): PendingRequest
    {
        $token = (string) $connection->api_token;

        if ($token === '') {
            throw new HostingProviderException('Missing Laravel Cloud API token.');
        }

        return Http::baseUrl($this->baseUrl($connection))
            ->acceptJson()
            ->withToken($token)
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 250, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException;
            }, throw: false);
    }

    private function baseUrl(HostingConnection $connection): string
    {
        $baseUrl = trim((string) ($connection->api_base_url ?: 'https://cloud.laravel.com/api'));

        return rtrim($baseUrl, '/');
    }

    private function normalizePath(string $path): string
    {
        return '/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $status, mixed $payload, mixed $message): array
    {
        if ($status >= 400) {
            throw new HostingProviderException($this->failureMessage($status, $message));
        }

        if (! is_array($payload)) {
            throw new HostingProviderException('Laravel Cloud API returned a non-JSON response.');
        }

        return $payload;
    }

    private function failureMessage(int $status, mixed $message): string
    {
        $message = is_string($message) && trim($message) !== '' ? trim($message) : 'Request failed.';

        return "Laravel Cloud API error {$status}: {$message}";
    }
}
