<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apple_authorizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_id');
            $table->unique(['user_id', 'client_id']);
            $table->text('refresh_token');
            $table->timestamp('revocation_requested_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('retry_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_authorizations');
    }
};
