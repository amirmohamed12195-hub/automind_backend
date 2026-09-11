<?php

namespace App\Console\Commands;

use App\Models\VehicleModelGeneration;
use App\Services\VehicleCatalog\VehicleImageDownloader;
use Illuminate\Console\Command;
use Throwable;

class SyncVehicleCatalogImages extends Command
{
    protected $signature = 'automind:sync-vehicle-images
        {--make= : Only this make code}
        {--model= : Only this model code (requires --make)}
        {--generation= : Only this generation code (requires --make and --model)}
        {--limit=100 : Maximum generations to attempt; 0 means all}
        {--force : Replace already downloaded photos}
        {--retry-missing : Retry generations previously marked not_found}
        {--dry-run : Show how many generations would be attempted}';

    protected $description = 'Download licensed vehicle-generation photos from Wikimedia Commons';

    public function handle(VehicleImageDownloader $downloader): int
    {
        if ($this->option('model') && ! $this->option('make')) {
            $this->error('--model requires --make.');

            return self::INVALID;
        }
        if ($this->option('generation') && (! $this->option('make') || ! $this->option('model'))) {
            $this->error('--generation requires --make and --model.');

            return self::INVALID;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 0) {
            $this->error('--limit must be a non-negative integer.');

            return self::INVALID;
        }

        $query = VehicleModelGeneration::query()
            ->with('model.make')
            ->when(! $this->option('force'), function ($query): void {
                $statuses = ['pending', 'failed'];
                if ($this->option('retry-missing')) {
                    $statuses[] = 'not_found';
                }
                $query->whereIn('image_status', $statuses);
            })
            ->when($this->option('make'), fn ($query, $make) => $query->whereHas('model.make', fn ($q) => $q->where('code', $make)))
            ->when($this->option('model'), fn ($query, $model) => $query->whereHas('model', fn ($q) => $q->where('code', $model)))
            ->when($this->option('generation'), fn ($query, $generation) => $query->where('code', $generation))
            ->orderByRaw('image_last_attempted_at IS NOT NULL')
            ->orderBy('image_last_attempted_at')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }
        $generations = $query->get();

        if ($this->option('dry-run')) {
            $this->info("{$generations->count()} vehicle generation(s) would be attempted.");

            return self::SUCCESS;
        }

        $downloaded = 0;
        $missing = 0;
        $failed = 0;
        $delay = max(0, (int) config('automind.vehicle_catalog_images.download_delay_ms'));
        foreach ($generations as $index => $generation) {
            try {
                $downloader->download($generation) ? $downloaded++ : $missing++;
            } catch (Throwable $exception) {
                $failed++;
                $generation->update([
                    'image_status' => $generation->image_path ? 'downloaded' : 'failed',
                    'image_last_attempted_at' => now(),
                ]);
                $this->warn("{$generation->model->make->code}/{$generation->model->code}/{$generation->code}: {$exception->getMessage()}");
            }

            if ($delay > 0 && $index < $generations->count() - 1) {
                usleep($delay * 1000);
            }
        }

        $this->info("Vehicle image sync complete: {$downloaded} downloaded, {$missing} not found, {$failed} failed.");
        $this->table(
            ['Status', 'Generation shapes'],
            collect(['downloaded', 'not_found', 'failed', 'pending'])
                ->map(fn (string $status): array => [
                    $status,
                    VehicleModelGeneration::query()->where('image_status', $status)->count(),
                ])
                ->all(),
        );

        return self::SUCCESS;
    }
}
