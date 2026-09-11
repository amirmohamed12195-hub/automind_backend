<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_model_generations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('model_id')->constrained('vehicle_models')->cascadeOnDelete();
            $table->string('code', 160);
            $table->string('name');
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->string('body_type', 120)->nullable();
            $table->string('data_source', 80)->default('catalog-range');
            $table->string('image_status', 24)->default('pending')->index();
            $table->string('image_disk', 32)->nullable();
            $table->string('image_path')->nullable();
            $table->text('image_source_page_url')->nullable();
            $table->text('image_author')->nullable();
            $table->string('image_license', 120)->nullable();
            $table->text('image_license_url')->nullable();
            $table->text('image_attribution')->nullable();
            $table->char('image_sha256', 64)->nullable();
            $table->unsignedSmallInteger('image_width')->nullable();
            $table->unsignedSmallInteger('image_height')->nullable();
            $table->timestamp('image_last_attempted_at')->nullable();
            $table->timestamp('image_downloaded_at')->nullable();
            $table->timestamps();
            $table->unique(['model_id', 'code']);
            $table->index(['model_id', 'start_year', 'end_year'], 'vehicle_generation_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_model_generations');
    }
};
