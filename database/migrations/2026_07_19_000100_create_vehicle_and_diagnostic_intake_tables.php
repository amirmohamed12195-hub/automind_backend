<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_makes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 80)->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('vehicle_models', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('make_id')->constrained('vehicle_makes')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name_en');
            $table->string('name_ar');
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->unique(['make_id', 'code']);
            $table->index(['make_id', 'start_year', 'end_year']);
        });

        Schema::create('vehicles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('catalog_make_id')->nullable()->constrained('vehicle_makes')->nullOnDelete();
            $table->foreignUlid('catalog_model_id')->nullable()->constrained('vehicle_models')->nullOnDelete();
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('engine', 120);
            $table->string('fuel_type', 64);
            $table->string('transmission', 64);
            $table->unsignedBigInteger('mileage_km')->default(0);
            $table->string('vin', 17)->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedTinyInteger('health_score')->default(100);
            $table->string('plate_number', 64)->nullable();
            $table->string('nickname')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'vin']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('user_selected_vehicles', function (Blueprint $table): void {
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
        });

        Schema::create('symptom_definitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('label_en');
            $table->string('label_ar');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('diagnostic_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->text('description')->nullable();
            $table->string('input_locale', 2)->default('en');
            $table->string('report_locale', 2)->default('en');
            $table->char('market_country_code', 2)->nullable();
            $table->string('market_city')->nullable();
            $table->char('market_currency', 3)->nullable();
            $table->string('client_reference')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->string('current_step', 64)->default('preparingData');
            $table->string('error_code', 64)->nullable();
            $table->text('safe_error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('prompt_version', 32)->default('diagnostic-v1');
            $table->unsignedInteger('lock_version')->default(0);
            $table->json('input_manifest')->nullable();
            $table->char('input_hash', 64)->nullable();
            $table->string('consent_version', 32)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['vehicle_id', 'created_at']);
        });

        Schema::create('diagnostic_session_symptoms', function (Blueprint $table): void {
            $table->foreignUlid('diagnostic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('symptom_definition_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['diagnostic_session_id', 'symptom_definition_id'], 'diagnostic_symptom_pk');
        });

        Schema::create('diagnostic_media', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_session_id')->constrained()->cascadeOnDelete();
            $table->string('media_kind', 32);
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_milliseconds')->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->unsignedTinyInteger('channels')->nullable();
            $table->string('upload_status', 24)->default('uploaded');
            $table->string('scan_status', 24)->default('pending');
            $table->string('processing_status', 24)->default('pending');
            $table->string('failure_code', 64)->nullable();
            $table->string('provider_file_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['diagnostic_session_id', 'sha256']);
            $table->index(['diagnostic_session_id', 'media_kind', 'deleted_at'], 'diagnostic_media_active_idx');
        });

        Schema::create('obd_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_session_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('rpm', 10, 2)->nullable();
            $table->decimal('speed_kmh', 10, 2)->nullable();
            $table->decimal('coolant_celsius', 7, 2)->nullable();
            $table->decimal('battery_volts', 6, 2)->nullable();
            $table->decimal('short_fuel_trim_percent', 7, 2)->nullable();
            $table->decimal('long_fuel_trim_percent', 7, 2)->nullable();
            $table->decimal('engine_load_percent', 7, 2)->nullable();
            $table->json('raw_json');
            $table->timestamps();
            $table->index(['diagnostic_session_id', 'recorded_at']);
        });

        Schema::create('obd_trouble_codes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('obd_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('code', 7)->index();
            $table->text('raw_description')->nullable();
            $table->string('status', 16)->default('unknown');
            $table->timestamps();
            $table->unique(['obd_snapshot_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obd_trouble_codes');
        Schema::dropIfExists('obd_snapshots');
        Schema::dropIfExists('diagnostic_media');
        Schema::dropIfExists('diagnostic_session_symptoms');
        Schema::dropIfExists('diagnostic_sessions');
        Schema::dropIfExists('symptom_definitions');
        Schema::table('user_selected_vehicles', function (Blueprint $table): void {
            $table->dropForeign(['vehicle_id']);
        });
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('vehicle_makes');
    }
};
