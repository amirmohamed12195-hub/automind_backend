<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            });
        }
    }

    public function down(): void
    {
        // The column belongs to the canonical users schema, so compatibility
        // deployments must not remove it during a rollback.
    }
};
