<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('engine', 120)->nullable()->change();
            $table->string('fuel_type', 64)->nullable()->change();
            $table->string('transmission', 64)->nullable()->change();
            $table->unsignedBigInteger('mileage_km')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Restore legacy defaults before restoring the non-null constraints.
        foreach (['engine' => '', 'fuel_type' => 'Other', 'transmission' => 'Other', 'mileage_km' => 0] as $column => $value) {
            DB::table('vehicles')->whereNull($column)->update([$column => $value]);
        }
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('engine', 120)->nullable(false)->change();
            $table->string('fuel_type', 64)->nullable(false)->change();
            $table->string('transmission', 64)->nullable(false)->change();
            $table->unsignedBigInteger('mileage_km')->nullable(false)->default(0)->change();
        });
    }
};
