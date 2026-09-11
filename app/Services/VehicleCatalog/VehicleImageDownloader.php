<?php

namespace App\Services\VehicleCatalog;

use App\Models\VehicleModelGeneration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class VehicleImageDownloader
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function download(VehicleModelGeneration $generation): bool
    {
        $generation->loadMissing('model.make');
        $make = $generation->model->make;
        $model = $generation->model;
        $attemptedAt = now();

        $pages = $this->client()->get(config('automind.vehicle_catalog_images.wikimedia_api_url'), [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'generator' => 'search',
            'gsrsearch' => $this->searchQuery($make->name_en, $model->name_en, $generation),
            'gsrnamespace' => 6,
            'gsrlimit' => 10,
            'prop' => 'imageinfo',
            'iiprop' => 'url|mime|size|extmetadata',
            'iiurlwidth' => config('automind.vehicle_catalog_images.width'),
        ])->throw()->json('query.pages', []);

        $candidate = $this->bestCandidate($pages, $make->name_en, $model->name_en, $generation);
        if (! $candidate) {
            $generation->update([
                'image_status' => $generation->image_path ? 'downloaded' : 'not_found',
                'image_last_attempted_at' => $attemptedAt,
            ]);

            return false;
        }

        $downloadUrl = $candidate['info']['thumburl'] ?? $candidate['info']['url'] ?? null;
        $host = is_string($downloadUrl) ? parse_url($downloadUrl, PHP_URL_HOST) : null;
        if (! in_array($host, ['upload.wikimedia.org', 'thumb.wikimedia.org'], true)) {
            throw new RuntimeException('Wikimedia returned an unexpected image host.');
        }

        $response = $this->client()->withOptions(['allow_redirects' => false])->get($downloadUrl)->throw();
        $bytes = $response->body();
        if ($bytes === '' || strlen($bytes) > config('automind.vehicle_catalog_images.max_bytes')) {
            throw new RuntimeException('Vehicle image is empty or exceeds the configured maximum size.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
        $dimensions = getimagesizefromstring($bytes);
        if (! $extension || $dimensions === false) {
            throw new RuntimeException('Downloaded vehicle image is not a supported bitmap.');
        }

        $disk = config('automind.vehicle_catalog_images.disk');
        $path = "vehicle-models/{$make->code}/{$model->code}/{$generation->code}.{$extension}";
        if (! Storage::disk($disk)->put($path, $bytes, ['visibility' => 'public', 'ContentType' => $mimeType])) {
            throw new RuntimeException('Vehicle image could not be written to the configured disk.');
        }

        $metadata = $candidate['info']['extmetadata'] ?? [];
        $license = $this->metadataValue($metadata, 'LicenseShortName');
        $author = $this->plainText($this->metadataValue($metadata, 'Artist') ?: $this->metadataValue($metadata, 'Credit'));
        $sourcePageUrl = 'https://commons.wikimedia.org/wiki/'.str_replace('%3A', ':', rawurlencode(str_replace(' ', '_', $candidate['title'])));
        $attribution = implode(', ', array_filter([$author, $license, 'via Wikimedia Commons']));

        $generation->update([
            'image_status' => 'downloaded',
            'image_disk' => $disk,
            'image_path' => $path,
            'image_source_page_url' => $sourcePageUrl,
            'image_author' => $author ?: null,
            'image_license' => $license,
            'image_license_url' => $this->metadataValue($metadata, 'LicenseUrl') ?: null,
            'image_attribution' => $attribution,
            'image_sha256' => hash('sha256', $bytes),
            'image_width' => $dimensions[0],
            'image_height' => $dimensions[1],
            'image_last_attempted_at' => $attemptedAt,
            'image_downloaded_at' => now(),
        ]);

        return true;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => config('automind.vehicle_catalog_images.user_agent')])
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500);
    }

    private function searchQuery(string $make, string $model, VehicleModelGeneration $generation): string
    {
        $year = $generation->start_year ? ' "'.$generation->start_year.'"' : '';
        $shape = $generation->body_type ?: $this->shapeFromName($generation->name);

        return sprintf('intitle:"%s %s"%s%s filetype:bitmap', $make, $model, $year, $shape ? ' '.$shape : '');
    }

    private function bestCandidate(array $pages, string $make, string $model, VehicleModelGeneration $generation): ?array
    {
        $candidates = [];
        foreach ($pages as $page) {
            $info = $page['imageinfo'][0] ?? null;
            if (! is_array($info) || ! isset(self::ALLOWED_MIME_TYPES[$info['mime'] ?? ''])) {
                continue;
            }

            $license = $this->metadataValue($info['extmetadata'] ?? [], 'LicenseShortName');
            if (! $this->isAllowedLicense($license)) {
                continue;
            }

            $title = $this->normalize((string) ($page['title'] ?? ''));
            if (! $this->containsTokens($title, $this->normalize($make)) || ! $this->containsTokens($title, $this->normalize($model))) {
                continue;
            }

            $categories = $this->normalize($this->metadataValue($info['extmetadata'] ?? [], 'Categories'));
            $evidence = $title.' '.$categories;
            if ($generation->start_year && ! $this->containsGenerationYear($evidence, $generation->start_year)) {
                continue;
            }

            $score = 100;
            foreach (['front' => 8, 'side' => 4, 'exterior' => 3, 'sedan' => 2, 'hatchback' => 2, 'wagon' => 2, 'coupe' => 2] as $term => $points) {
                $score += str_contains($evidence, $term) ? $points : 0;
            }
            foreach (['rear' => 5, 'interior' => 30, 'dashboard' => 30, 'engine' => 20, 'wreck' => 25, 'accident' => 25, 'police' => 12, 'toy car' => 30] as $term => $points) {
                $score -= str_contains($evidence, $term) ? $points : 0;
            }

            $candidates[] = ['score' => $score, 'title' => $page['title'], 'info' => $info];
        }

        usort($candidates, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return $candidates[0] ?? null;
    }

    private function containsGenerationYear(string $evidence, int $startYear): bool
    {
        return str_contains($evidence, (string) $startYear)
            || str_contains($evidence, (string) ($startYear + 1));
    }

    private function containsTokens(string $haystack, string $needle): bool
    {
        $tokens = array_filter(explode(' ', $needle), fn (string $token): bool => strlen($token) >= 2 || ctype_digit($token));

        return $tokens !== [] && collect($tokens)->every(fn (string $token): bool => str_contains(' '.$haystack.' ', ' '.$token.' '));
    }

    private function isAllowedLicense(string $license): bool
    {
        return (bool) preg_match('/^(?:CC0|CC BY(?:-SA)?|Public domain|PD(?:-|$))/i', trim($license));
    }

    private function shapeFromName(string $name): ?string
    {
        foreach (['sedan', 'saloon', 'hatchback', 'wagon', 'estate', 'coupe', 'cabrio', 'convertible', 'suv', 'pickup', 'van'] as $shape) {
            if (str_contains(strtolower($name), $shape)) {
                return $shape;
            }
        }

        return null;
    }

    private function metadataValue(array $metadata, string $key): string
    {
        return trim((string) ($metadata[$key]['value'] ?? ''));
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    private function normalize(string $value): string
    {
        return ' '.Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString().' ';
    }
}
