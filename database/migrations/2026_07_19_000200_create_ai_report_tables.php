<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_session_id')->constrained()->cascadeOnDelete();
            $table->string('task_type', 40)->index();
            $table->string('provider', 32)->default('openai');
            $table->string('endpoint');
            $table->string('model');
            $table->string('prompt_version', 32);
            $table->char('input_hash', 64);
            $table->string('provider_response_id')->nullable();
            $table->string('status', 24)->index();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedInteger('latency_milliseconds')->nullable();
            $table->unsignedInteger('input_token_count')->nullable();
            $table->unsignedInteger('output_token_count')->nullable();
            $table->unsignedInteger('cached_token_count')->nullable();
            $table->unsignedInteger('reasoning_token_count')->nullable();
            $table->decimal('estimated_provider_cost', 14, 6)->nullable();
            $table->char('cost_currency', 3)->nullable();
            $table->json('raw_usage_json')->nullable();
            $table->json('response_metadata_json')->nullable();
            $table->string('safe_error_category', 64)->nullable();
            $table->text('safe_error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['diagnostic_session_id', 'task_type', 'input_hash'], 'ai_runs_input_idx');
        });

        Schema::create('media_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_media_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ai_run_id')->constrained()->cascadeOnDelete();
            $table->string('observation_type', 64);
            $table->string('canonical_code', 100)->nullable();
            $table->decimal('confidence', 5, 4);
            $table->decimal('reliability', 5, 4);
            $table->text('text_en');
            $table->text('text_ar');
            $table->json('bounding_box_or_time_range')->nullable();
            $table->timestamps();
        });

        Schema::create('diagnostic_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->restrictOnDelete();
            $table->decimal('overall_confidence', 5, 4);
            $table->string('severity', 16);
            $table->string('driving_recommendation', 32);
            $table->string('evidence_quality', 16);
            $table->boolean('professional_inspection_required');
            $table->string('prompt_version', 32);
            $table->string('schema_version', 32);
            $table->timestamp('generated_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('disclaimer_version', 32);
            $table->json('limitations')->nullable();
            $table->json('missing_evidence')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('diagnostic_report_translations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_report_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('title');
            $table->text('summary');
            $table->text('driving_advice');
            $table->text('disclaimer');
            $table->timestamps();
            $table->unique(['diagnostic_report_id', 'locale'], 'report_translation_locale_unique');
        });

        Schema::create('suspected_faults', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_report_id')->constrained()->cascadeOnDelete();
            $table->string('canonical_fault_code', 120);
            $table->string('obd_code', 7)->nullable();
            $table->decimal('confidence', 5, 4);
            $table->string('severity', 16);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('suspected_fault_translations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('suspected_fault_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('title');
            $table->text('description');
            $table->timestamps();
            $table->unique(['suspected_fault_id', 'locale'], 'fault_translation_locale_unique');
        });

        Schema::create('fault_causes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('suspected_fault_id')->constrained()->cascadeOnDelete();
            $table->string('canonical_code', 120);
            $table->decimal('probability', 5, 4)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fault_cause_translations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('fault_cause_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->text('text');
            $table->timestamps();
            $table->unique(['fault_cause_id', 'locale']);
        });

        Schema::create('report_actions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_report_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('suspected_fault_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action_type', 32);
            $table->string('canonical_code', 120)->nullable();
            $table->unsignedTinyInteger('priority')->default(3);
            $table->boolean('professional_required')->default(false);
            $table->string('stop_condition_code', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('report_action_translations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('report_action_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->text('text');
            $table->text('stop_condition_text')->nullable();
            $table->timestamps();
            $table->unique(['report_action_id', 'locale']);
        });

        Schema::create('report_evidence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_report_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('suspected_fault_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->ulid('source_record_id')->nullable();
            $table->decimal('reliability', 5, 4);
            $table->text('observation_en');
            $table->text('observation_ar');
            $table->timestamps();
        });

        Schema::create('report_feedback', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_report_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->text('correction_or_comment')->nullable();
            $table->text('confirmed_mechanic_diagnosis')->nullable();
            $table->timestamps();
            $table->unique(['diagnostic_report_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['report_feedback', 'report_evidence', 'report_action_translations', 'report_actions', 'fault_cause_translations', 'fault_causes', 'suspected_fault_translations', 'suspected_faults', 'diagnostic_report_translations', 'diagnostic_reports', 'media_observations', 'ai_runs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
