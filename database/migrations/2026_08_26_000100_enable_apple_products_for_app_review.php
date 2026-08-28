<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('store_products')
            ->where('platform', 'apple')
            ->whereIn('product_id', [
                'com.automind.ai.full_report.single.v1',
                'com.automind.ai.plus.monthly.v1',
                'com.automind.ai.plus.yearly.v1',
            ])
            ->update([
                'active_for_sale' => true,
                'store_status' => 'active',
                'last_synced_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('store_products')
            ->where('platform', 'apple')
            ->whereIn('product_id', [
                'com.automind.ai.full_report.single.v1',
                'com.automind.ai.plus.monthly.v1',
                'com.automind.ai.plus.yearly.v1',
            ])
            ->update([
                'active_for_sale' => false,
                'store_status' => 'pending',
                'last_synced_at' => null,
                'updated_at' => now(),
            ]);
    }
};
