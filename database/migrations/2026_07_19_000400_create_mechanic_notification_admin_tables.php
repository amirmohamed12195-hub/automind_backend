<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mechanics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address');
            $table->string('city');
            $table->char('country_code', 2);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('verified')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->string('logo_path')->nullable();
            $table->json('working_hours_json')->nullable();
            $table->timestamps();
            $table->index(['country_code', 'city']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('mechanic_specialties', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->timestamps();
        });

        Schema::create('mechanic_specialty_assignments', function (Blueprint $table): void {
            $table->foreignUlid('mechanic_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('mechanic_specialty_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['mechanic_id', 'mechanic_specialty_id'], 'mechanic_specialty_pk');
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('mechanic_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('diagnostic_report_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('requested_start_at');
            $table->timestamp('requested_end_at');
            $table->string('status', 24)->default('requested')->index();
            $table->text('customer_note')->nullable();
            $table->text('mechanic_note')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['mechanic_id', 'requested_start_at', 'requested_end_at'], 'appointment_availability_idx');
        });

        Schema::create('mechanic_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('mechanic_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('moderation_status', 24)->default('pending');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64)->index();
            $table->string('title_en');
            $table->string('title_ar');
            $table->text('body_en');
            $table->text('body_ar');
            $table->json('data_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at', 'created_at'], 'notifications_user_read_idx');
        });

        Schema::create('webhook_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider', 32);
            $table->string('provider_event_id');
            $table->string('event_type');
            $table->string('provider_object_id')->nullable();
            $table->char('payload_hash', 64);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('status', 24)->default('received');
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('target_type')->nullable();
            $table->ulid('target_id')->nullable();
            $table->string('request_id', 64)->index();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_summary')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'webhook_receipts', 'notifications', 'mechanic_reviews', 'appointments', 'mechanic_specialty_assignments', 'mechanic_specialties', 'mechanics'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
