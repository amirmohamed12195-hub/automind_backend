<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('billing_plans')
            ->where('code', 'FREE')
            ->where('reports_per_period', 1)
            ->update([
                'reports_per_period' => 10,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Keep the live allowance unchanged; it may have been customized in the dashboard.
    }
};
