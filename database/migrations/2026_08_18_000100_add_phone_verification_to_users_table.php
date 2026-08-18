<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            });

            // Existing accounts predate mandatory phone verification. Preserve
            // their login access; every new password registration starts null.
            DB::table('users')->whereNotNull('phone')->update(['phone_verified_at' => DB::raw('CURRENT_TIMESTAMP')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'phone_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('phone_verified_at');
            });
        }
    }
};
