<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_makes', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('name_ar');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_makes', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
