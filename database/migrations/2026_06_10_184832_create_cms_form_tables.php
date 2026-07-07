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
        Schema::create('cms_forms', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('locale', 12)->default(config('app.locale', 'en'));
            $table->string('translation_key', 32)->index();
            $table->foreignId('translated_from_form_id')->nullable()->constrained('cms_forms')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('notification_email')->nullable();
            $table->string('submit_button_label')->nullable();
            $table->text('success_message')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['translation_key', 'locale']);
        });

        Schema::create('cms_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_form_id')->constrained('cms_forms')->cascadeOnDelete();
            $table->string('type', 48)->default('text');
            $table->string('translation_key', 32)->index();
            $table->foreignId('translated_from_form_field_id')->nullable()->constrained('cms_form_fields')->nullOnDelete();
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->string('width', 24)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['cms_form_id', 'sort_order']);
        });

        Schema::create('cms_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_form_id')->constrained('cms_forms')->cascadeOnDelete();
            $table->foreignId('cms_page_id')->nullable()->constrained('cms_pages')->nullOnDelete();
            $table->string('locale', 12)->index();
            $table->string('form_translation_key', 32)->index();
            $table->string('status', 32)->default('new')->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_form_submission_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_form_submission_id')->constrained('cms_form_submissions')->cascadeOnDelete();
            $table->foreignId('cms_form_field_id')->nullable()->constrained('cms_form_fields')->nullOnDelete();
            $table->string('field_translation_key', 32)->index();
            $table->string('field_label_snapshot')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_form_submission_values');
        Schema::dropIfExists('cms_form_submissions');
        Schema::dropIfExists('cms_form_fields');
        Schema::dropIfExists('cms_forms');
    }
};
