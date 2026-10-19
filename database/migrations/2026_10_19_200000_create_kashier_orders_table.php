<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kashier_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('billing_plan_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('plan_code', 64);
            $table->string('mode', 8);
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3)->default('EGP');
            $table->string('status', 32)->default('creating')->index();
            $table->string('session_id', 255)->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('access_ends_at')->nullable();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->foreignUlid('store_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kashier_orders');
    }
};
