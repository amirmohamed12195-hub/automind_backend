<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('deletion_requested_at')->index();
            $table->string('suspension_reason', 500)->nullable()->after('suspended_at');
        });

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->string('group', 50)->index();
            $table->string('label', 160);
            $table->string('type', 24);
            $table->json('value');
            $table->string('description', 500)->nullable();
            $table->string('updated_by_admin', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
