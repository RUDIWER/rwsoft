<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cms_forms', function (Blueprint $table): void {
            if (! Schema::hasColumn('cms_forms', 'submission_email_enabled')) {
                $table->boolean('submission_email_enabled')->default(false)->after('notification_email');
            }

            if (! Schema::hasColumn('cms_forms', 'submission_cms_email_id')) {
                $table->foreignId('submission_cms_email_id')
                    ->nullable()
                    ->after('submission_email_enabled')
                    ->constrained('cms_emails')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('cms_forms', 'submission_to_cms_form_field_id')) {
                $table->foreignId('submission_to_cms_form_field_id')
                    ->nullable()
                    ->after('submission_cms_email_id')
                    ->constrained('cms_form_fields')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('cms_forms', 'submission_cc_recipients')) {
                $table->json('submission_cc_recipients')->nullable()->after('submission_to_cms_form_field_id');
            }

            if (! Schema::hasColumn('cms_forms', 'submission_bcc_recipients')) {
                $table->json('submission_bcc_recipients')->nullable()->after('submission_cc_recipients');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_forms', function (Blueprint $table): void {
            foreach (['submission_cms_email_id', 'submission_to_cms_form_field_id'] as $column) {
                if (Schema::hasColumn('cms_forms', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['submission_bcc_recipients', 'submission_cc_recipients', 'submission_email_enabled'] as $column) {
                if (Schema::hasColumn('cms_forms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
