<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function success(
        string $action,
        string $module,
        string $subjectType,
        ?string $subjectKey = null,
        string $message = '',
        array $meta = [],
        ?Request $request = null,
    ): void {
        $this->log(
            action: $action,
            module: $module,
            subjectType: $subjectType,
            subjectKey: $subjectKey,
            success: true,
            severity: 'info',
            message: $message,
            meta: $meta,
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function failure(
        string $action,
        string $module,
        string $subjectType,
        ?string $subjectKey = null,
        string $message = '',
        array $meta = [],
        ?Request $request = null,
    ): void {
        $this->log(
            action: $action,
            module: $module,
            subjectType: $subjectType,
            subjectKey: $subjectKey,
            success: false,
            severity: 'error',
            message: $message,
            meta: $meta,
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function denied(
        string $action,
        string $module,
        string $subjectType,
        ?string $subjectKey = null,
        string $message = '',
        array $meta = [],
        ?Request $request = null,
    ): void {
        $this->log(
            action: $action,
            module: $module,
            subjectType: $subjectType,
            subjectKey: $subjectKey,
            success: false,
            severity: 'warning',
            message: $message,
            meta: $meta,
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(
        string $action,
        string $module,
        string $subjectType,
        ?string $subjectKey,
        bool $success,
        string $severity,
        string $message = '',
        array $meta = [],
        ?Request $request = null,
    ): void {
        if (! (bool) config('audit.enabled', true)) {
            return;
        }

        $request ??= request();
        $actor = $request?->user();
        $actorUser = $actor instanceof User ? $actor : null;

        $requestId = (string) ($request?->attributes->get('audit.request_id') ?? '');
        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }

        $executionMode = $request?->attributes->get('audit.execution_mode');
        if (! is_string($executionMode) || trim($executionMode) === '') {
            $executionMode = null;
        }

        $entry = [
            'occurred_at' => now(),
            'request_id' => $requestId,
            'actor_user_id' => $actorUser?->id,
            'actor_name' => $actorUser?->name,
            'actor_email' => $actorUser?->email,
            'application_slug' => null,
            'application_name' => null,
            'execution_mode' => $executionMode,
            'module' => trim($module),
            'action' => trim($action),
            'subject_type' => trim($subjectType),
            'subject_key' => $subjectKey,
            'success' => $success,
            'severity' => $severity,
            'message' => trim($message) !== '' ? trim($message) : null,
            'meta' => $this->sanitizeMeta($meta),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'route_name' => $request?->route()?->getName(),
            'http_method' => $request?->method(),
            'url' => $request?->fullUrl(),
        ];

        if ((bool) config('audit.db_enabled', true)
            && Schema::hasTable('audit_logs')) {
            try {
                AuditLog::query()->create($entry);
            } catch (Throwable) {
            }
        }

        if ((bool) config('audit.file_enabled', true)) {
            try {
                $channel = (string) config('audit.channel', 'audit');
                Log::channel($channel)->info(
                    $entry['message'] ?? $entry['action'],
                    Arr::except($entry, ['message'])
                );
            } catch (Throwable) {
            }
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function sanitizeMeta(array $meta): array
    {
        $redactedKeys = collect(config('audit.redacted_keys', []))
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        return $this->sanitizeValue($meta, $redactedKeys);
    }

    /**
     * @param  array<int, string>  $redactedKeys
     */
    private function sanitizeValue(mixed $value, array $redactedKeys): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $normalizedKey = strtolower((string) $key);
                if (in_array($normalizedKey, $redactedKeys, true)) {
                    $sanitized[$key] = '[REDACTED]';

                    continue;
                }

                $sanitized[$key] = $this->sanitizeValue($item, $redactedKeys);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            $maxLength = (int) config('audit.max_meta_string_length', 4000);

            return mb_strlen($value) > $maxLength
                ? mb_substr($value, 0, $maxLength).'...'
                : $value;
        }

        return $value;
    }
}
