<?php

namespace Tests\Feature\Audit;

use App\Support\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_stores_null_execution_mode_when_not_available(): void
    {
        $logger = app(AuditLogger::class);
        $request = Request::create('/audit-test', 'POST');

        $logger->success(
            action: 'audit.test.null_mode',
            module: 'audit',
            subjectType: 'test',
            subjectKey: 'no-mode',
            message: 'Test without execution mode',
            request: $request,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'audit.test.null_mode',
            'subject_key' => 'no-mode',
            'execution_mode' => null,
            'success' => 1,
        ]);
    }

    public function test_audit_logger_stores_execution_mode_when_context_is_present(): void
    {
        $logger = app(AuditLogger::class);
        $request = Request::create('/audit-test', 'POST');
        $request->attributes->set('audit.execution_mode', 'run');

        $logger->success(
            action: 'audit.test.run_mode',
            module: 'audit',
            subjectType: 'test',
            subjectKey: 'run-mode',
            message: 'Test with execution mode',
            request: $request,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'audit.test.run_mode',
            'subject_key' => 'run-mode',
            'execution_mode' => 'run',
            'success' => 1,
        ]);
    }
}
