<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('admin_role', 32)->nullable()->after('is_admin')->index();
        });

        Schema::create('billing_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('apple_app_account_token')->unique();
            $table->char('google_obfuscated_account_id', 64)->unique();
            $table->timestamps();
        });

        Schema::create('billing_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('type', 32);
            $table->boolean('active')->default(false)->index();
            $table->boolean('published')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('recommended')->default(false);
            $table->string('badge', 80)->nullable();
            $table->boolean('default_for_new_users')->default(false);
            $table->unsignedInteger('max_vehicles')->nullable();
            $table->unsignedInteger('reports_per_period')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_plan_localizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('billing_plan_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('display_name', 120);
            $table->string('short_description', 255)->nullable();
            $table->text('full_description')->nullable();
            $table->string('badge_text', 80)->nullable();
            $table->json('feature_copy_json')->nullable();
            $table->timestamps();
            $table->unique(['billing_plan_id', 'locale'], 'billing_plan_locale_unique');
        });

        Schema::create('billing_plan_features', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('billing_plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->boolean('enabled')->default(false);
            $table->integer('limit_value')->nullable();
            $table->json('configuration_json')->nullable();
            $table->timestamps();
            $table->unique(['billing_plan_id', 'feature_key'], 'billing_plan_feature_unique');
        });

        Schema::create('billing_plan_regions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('billing_plan_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->boolean('visible')->default(true);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->string('paywall_variant', 64)->nullable();
            $table->timestamps();
            $table->unique(['billing_plan_id', 'country_code'], 'billing_plan_region_unique');
        });

        Schema::create('store_products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('billing_plan_id')->constrained()->restrictOnDelete();
            $table->string('mapping_key', 255)->unique();
            $table->string('platform', 16)->index();
            $table->string('product_id', 255);
            $table->string('product_type', 32);
            $table->string('base_plan_id', 255)->nullable();
            $table->string('offer_id', 255)->nullable();
            $table->string('environment', 16)->default('production')->index();
            $table->boolean('active_for_sale')->default(false)->index();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->string('store_status', 32)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['platform', 'product_id', 'environment']);
        });

        Schema::create('store_price_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_product_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2)->nullable();
            $table->char('currency', 3);
            $table->decimal('customer_price', 18, 6);
            $table->string('formatted_price', 80);
            $table->string('billing_period', 64)->nullable();
            $table->json('offer_summary')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->index(['store_product_id', 'country_code', 'fetched_at'], 'store_price_lookup');
        });

        Schema::create('store_purchases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('store_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 16)->index();
            $table->string('environment', 16)->index();
            $table->string('product_id', 255);
            $table->string('base_plan_id', 255)->nullable();
            $table->string('offer_id', 255)->nullable();
            $table->string('transaction_id', 255)->nullable();
            $table->string('original_transaction_id', 255)->nullable()->index();
            $table->text('purchase_token')->nullable();
            $table->char('purchase_token_hash', 64)->nullable()->unique();
            $table->string('order_id', 255)->nullable();
            $table->string('state', 48)->index();
            $table->boolean('acknowledged')->default(false);
            $table->boolean('consumed')->default(false);
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->text('raw_reference')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->unique(['platform', 'environment', 'transaction_id'], 'store_purchase_transaction_unique');
        });

        Schema::create('user_entitlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('billing_plan_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('store_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entitlement_key', 255)->unique();
            $table->string('source', 32);
            $table->string('platform', 16)->nullable();
            $table->string('status', 48)->index();
            $table->timestamp('purchase_date')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable()->index();
            $table->boolean('auto_renew_enabled')->default(false);
            $table->timestamp('grace_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('verification_source', 32)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'period_end'], 'user_entitlement_access_lookup');
        });

        Schema::create('entitlement_period_usage', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_entitlement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedInteger('report_limit');
            $table->unsignedInteger('reports_used')->default(0);
            $table->unsignedInteger('reports_reserved')->default(0);
            $table->timestamps();
            $table->unique(['user_entitlement_id', 'period_start', 'period_end'], 'entitlement_usage_period_unique');
        });

        Schema::create('credit_ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('diagnostic_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_type', 48);
            $table->integer('quantity');
            $table->unsignedInteger('balance_after');
            $table->string('idempotency_key', 255)->unique();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('report_entitlement_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('diagnostic_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_entitlement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('entitlement_period_usage_id')->nullable()->constrained(
                'entitlement_period_usage',
                indexName: 'report_reservation_period_usage_fk',
            )->nullOnDelete();
            $table->string('source', 24);
            $table->string('status', 24)->default('reserved')->index();
            $table->timestamp('reserved_at');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('platform', 16)->index();
            $table->string('external_event_id', 255);
            $table->string('event_type', 100);
            $table->string('event_subtype', 100)->nullable();
            $table->string('environment', 16)->index();
            $table->string('processing_status', 32)->default('received')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->text('encrypted_payload_reference');
            $table->timestamps();
            $table->unique(['platform', 'external_event_id'], 'billing_event_external_unique');
        });

        Schema::create('manual_entitlement_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('billing_plan_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason', 500);
            $table->foreignUlid('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'starts_at', 'ends_at'], 'manual_grant_access_lookup');
        });

        Schema::create('billing_admin_audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_identifier', 255)->nullable();
            $table->string('action', 120)->index();
            $table->string('resource_type', 120);
            $table->string('resource_id', 255)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'billing_admin_audit_logs', 'manual_entitlement_grants', 'billing_events',
            'report_entitlement_reservations', 'credit_ledger_entries', 'entitlement_period_usage',
            'user_entitlements', 'store_purchases', 'store_price_snapshots', 'store_products',
            'billing_plan_regions', 'billing_plan_features', 'billing_plan_localizations',
            'billing_plans', 'billing_accounts',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('admin_role');
        });
    }
};
