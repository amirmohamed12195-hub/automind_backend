<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_recommendations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_report_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('suspected_fault_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('canonical_part_name');
            $table->string('part_number')->nullable();
            $table->text('vehicle_compatibility_text')->nullable();
            $table->decimal('compatibility_confidence', 5, 4);
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('part_recommendation_translations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('part_recommendation_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('display_name');
            $table->text('reason');
            $table->timestamps();
            $table->unique(['part_recommendation_id', 'locale'], 'part_translation_locale_unique');
        });

        Schema::create('price_searches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_report_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->string('city')->nullable();
            $table->char('currency', 3);
            $table->json('query_json');
            $table->string('status', 24);
            $table->string('idempotency_key')->nullable();
            $table->timestamp('searched_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['diagnostic_report_id', 'idempotency_key'], 'price_search_idempotency_unique');
        });

        Schema::create('web_sources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('price_search_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('url_hash', 64);
            $table->text('url');
            $table->string('title');
            $table->string('domain');
            $table->string('source_type', 32);
            $table->timestamp('retrieved_at');
            $table->timestamp('source_date')->nullable();
            $table->decimal('quality_score', 5, 4)->nullable();
            $table->text('raw_price_text')->nullable();
            $table->json('citation_metadata_json')->nullable();
            $table->timestamps();
            $table->unique(['price_search_id', 'url_hash']);
        });

        Schema::create('part_price_quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('part_recommendation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('web_source_id')->constrained()->cascadeOnDelete();
            $table->string('merchant');
            $table->string('condition', 24);
            $table->string('brand_or_manufacturer')->nullable();
            $table->string('part_number')->nullable();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);
            $table->decimal('normalized_amount', 14, 2)->nullable();
            $table->char('normalized_currency', 3)->nullable();
            $table->decimal('normalized_shipping_amount', 14, 2)->nullable();
            $table->decimal('currency_rate', 20, 10)->nullable();
            $table->string('currency_rate_provider')->nullable();
            $table->timestamp('currency_rate_effective_at')->nullable();
            $table->string('availability')->nullable();
            $table->decimal('shipping_amount', 14, 2)->nullable();
            $table->boolean('tax_included')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();
        });

        Schema::create('service_estimates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('diagnostic_report_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 24);
            $table->char('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->char('currency', 3);
            foreach (['parts', 'labor', 'fees', 'total'] as $group) {
                foreach (['low', 'typical', 'high'] as $band) {
                    $table->decimal("{$group}_{$band}", 14, 2)->nullable();
                }
            }
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('assumptions_json')->nullable();
            $table->timestamp('searched_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_estimate_line_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('service_estimate_id')->constrained()->cascadeOnDelete();
            $table->string('category', 16);
            $table->string('canonical_code');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 32);
            $table->decimal('low_amount', 14, 2)->nullable();
            $table->decimal('typical_amount', 14, 2)->nullable();
            $table->decimal('high_amount', 14, 2)->nullable();
            $table->char('currency', 3);
            $table->json('source_confidence_metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('labor_rate_sources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('web_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('administrator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('country_code', 2);
            $table->string('city')->nullable();
            $table->string('service_category');
            $table->decimal('hourly_low', 14, 2);
            $table->decimal('hourly_typical', 14, 2);
            $table->decimal('hourly_high', 14, 2);
            $table->decimal('hours_low', 8, 2)->nullable();
            $table->decimal('hours_typical', 8, 2)->nullable();
            $table->decimal('hours_high', 8, 2)->nullable();
            $table->char('currency', 3);
            $table->timestamp('observed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('currency_rates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 20, 10);
            $table->string('provider');
            $table->timestamp('effective_at');
            $table->timestamps();
            $table->unique(['base_currency', 'quote_currency', 'effective_at'], 'currency_rate_unique');
        });

        Schema::create('maintenance_service_definitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->unsignedSmallInteger('default_month_interval')->nullable();
            $table->unsignedInteger('default_km_interval')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_maintenance_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_definition_id')->nullable()->constrained('maintenance_service_definitions')->nullOnDelete();
            $table->string('custom_service')->nullable();
            $table->date('service_date');
            $table->unsignedBigInteger('odometer_km');
            $table->decimal('amount', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('mechanic')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->date('next_due_date')->nullable();
            $table->unsignedBigInteger('next_due_km')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'service_date']);
        });

        Schema::create('maintenance_reminders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_definition_id')->constrained('maintenance_service_definitions')->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('due_km')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->foreignUlid('completed_record_id')->nullable()->constrained('vehicle_maintenance_records')->nullOnDelete();
            $table->json('notification_preferences')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'due_date']);
        });
    }

    public function down(): void
    {
        foreach (['maintenance_reminders', 'vehicle_maintenance_records', 'maintenance_service_definitions', 'currency_rates', 'labor_rate_sources', 'service_estimate_line_items', 'service_estimates', 'part_price_quotes', 'web_sources', 'price_searches', 'part_recommendation_translations', 'part_recommendations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
