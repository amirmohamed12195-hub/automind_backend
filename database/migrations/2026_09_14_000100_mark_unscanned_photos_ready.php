<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('diagnostic_media')
            ->where('media_kind', 'photo')
            ->where('scan_status', 'not_configured')
            ->where('processing_status', 'pending')
            ->update([
                'processing_status' => 'ready',
                'failure_code' => null,
                'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    }

    public function down(): void
    {
        // A validated photo that became ready must not be made pending again.
    }
};
