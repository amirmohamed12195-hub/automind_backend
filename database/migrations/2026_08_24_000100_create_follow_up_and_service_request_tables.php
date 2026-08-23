<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_follow_ups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_report_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->text('question')->nullable();
            $table->longText('answer_en');
            $table->longText('answer_ar');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->boolean('professional_inspection_required')->default(false);
            $table->json('suggested_evidence_json')->nullable();
            $table->json('attachments_json')->nullable();
            $table->timestamps();
            $table->index(['diagnostic_report_id', 'created_at'], 'report_follow_ups_timeline_idx');
        });

        Schema::table('maintenance_reminders', function (Blueprint $table): void {
            $table->foreignUlid('source_report_id')->nullable()->after('service_definition_id')->constrained('diagnostic_reports')->nullOnDelete();
            $table->foreignUlid('source_report_action_id')->nullable()->after('source_report_id')->constrained('report_actions')->nullOnDelete();
            $table->string('source_action_text_en')->nullable()->after('source_report_action_id');
            $table->string('source_action_text_ar')->nullable()->after('source_action_text_en');
            $table->unique(['vehicle_id', 'source_report_action_id'], 'maintenance_source_action_unique');
        });

        Schema::table('mechanics', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('UTC')->after('country_code');
        });

        Schema::create('service_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('diagnostic_report_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('requested')->index();
            $table->text('description')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('service_request_mechanic', function (Blueprint $table): void {
            $table->foreignUlid('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('mechanic_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('invited');
            $table->timestamps();
            $table->primary(['service_request_id', 'mechanic_id'], 'service_request_mechanic_pk');
        });

        Schema::create('service_quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('mechanic_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('submitted')->index();
            $table->char('currency', 3);
            $table->decimal('labor_amount', 14, 2)->default(0);
            $table->decimal('parts_amount', 14, 2)->default(0);
            $table->decimal('fees_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->text('warranty_text')->nullable();
            $table->text('notes')->nullable();
            $table->json('line_items_json')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['service_request_id', 'mechanic_id']);
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->foreignUlid('selected_quote_id')->nullable()->after('diagnostic_report_id')->constrained('service_quotes')->nullOnDelete();
        });

        Schema::create('service_request_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('mechanic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender_role', 24);
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['service_request_id', 'created_at'], 'service_request_messages_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_messages');
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('selected_quote_id');
        });
        Schema::dropIfExists('service_quotes');
        Schema::dropIfExists('service_request_mechanic');
        Schema::dropIfExists('service_requests');

        Schema::table('mechanics', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });

        Schema::table('maintenance_reminders', function (Blueprint $table): void {
            $table->dropUnique('maintenance_source_action_unique');
            $table->dropConstrainedForeignId('source_report_action_id');
            $table->dropConstrainedForeignId('source_report_id');
            $table->dropColumn(['source_action_text_en', 'source_action_text_ar']);
        });

        Schema::dropIfExists('report_follow_ups');
    }
};
