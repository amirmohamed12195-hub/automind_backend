<?php

namespace Tests\Feature;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VehicleCatalogImageSyncTest extends ApiTestCase
{
    public function test_model_year_resolves_to_the_correct_shape_photo(): void
    {
        Storage::fake('public');
        $make = VehicleMake::query()->create([
            'code' => 'toyota',
            'name_en' => 'Toyota',
            'name_ar' => 'تويوتا',
            'logo_path' => 'images/vehicle-makes/toyota-logo.svg',
            'active' => true,
        ]);
        $model = VehicleModel::query()->create([
            'make_id' => $make->id,
            'code' => 'corolla',
            'name_en' => 'Corolla',
            'name_ar' => 'كورولا',
            'start_year' => 2008,
            'end_year' => 2020,
            'active' => true,
        ]);
        $model->generations()->delete();
        foreach ([
            ['code' => 'e140', 'name' => 'Corolla E140', 'start_year' => 2008, 'end_year' => 2012, 'image_path' => 'vehicle-models/toyota/corolla/e140.jpg'],
            ['code' => 'e170', 'name' => 'Corolla E170', 'start_year' => 2013, 'end_year' => 2020, 'image_path' => 'vehicle-models/toyota/corolla/e170.jpg'],
        ] as $generation) {
            Storage::disk('public')->put($generation['image_path'], 'photo');
            $model->generations()->create($generation + [
                'data_source' => 'test',
                'image_status' => 'downloaded',
                'image_disk' => 'public',
            ]);
        }

        $this->getJson('/api/v1/vehicle-catalog/makes/toyota/models?year=2010')
            ->assertOk()
            ->assertJsonPath('data.0.generationCode', 'e140')
            ->assertJsonPath('data.0.imageType', 'model_photo');
        $this->getJson('/api/v1/vehicle-catalog/makes/toyota/models?year=2018')
            ->assertOk()
            ->assertJsonPath('data.0.generationCode', 'e170')
            ->assertJsonPath('data.0.imageType', 'model_photo');
        $this->getJson('/api/v1/vehicle-catalog/makes/toyota/models/corolla/generations?year=2010')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'e140');
    }

    public function test_it_downloads_a_licensed_generation_photo_and_exposes_attribution(): void
    {
        Storage::fake('public');
        config([
            'automind.vehicle_catalog_images.disk' => 'public',
            'automind.vehicle_catalog_images.download_delay_ms' => 0,
        ]);
        $make = VehicleMake::query()->create([
            'code' => 'toyota',
            'name_en' => 'Toyota',
            'name_ar' => 'تويوتا',
            'logo_path' => 'images/vehicle-makes/toyota-logo.svg',
            'active' => true,
        ]);
        $model = VehicleModel::query()->create([
            'make_id' => $make->id,
            'code' => 'corolla',
            'name_en' => 'Corolla',
            'name_ar' => 'كورولا',
            'start_year' => 2018,
            'end_year' => 2020,
            'active' => true,
        ]);
        $generation = $model->generations()->sole();

        Http::fake([
            'https://commons.wikimedia.org/w/api.php*' => Http::response([
                'query' => ['pages' => [[
                    'title' => 'File:2018 Toyota Corolla Front Left.jpg',
                    'imageinfo' => [[
                        'mime' => 'image/png',
                        'thumburl' => 'https://thumb.wikimedia.org/toyota-corolla.png',
                        'url' => 'https://upload.wikimedia.org/toyota-corolla.png',
                        'extmetadata' => [
                            'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                            'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0'],
                            'Artist' => ['value' => '<a href="//commons.wikimedia.org/wiki/User:Photographer">Photographer</a>'],
                            'Categories' => ['value' => '2018 Toyota automobiles|Toyota Corolla'],
                        ],
                    ]],
                ]]],
            ]),
            'https://thumb.wikimedia.org/*' => Http::response(base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
            ), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('automind:sync-vehicle-images', [
            '--make' => 'toyota',
            '--model' => 'corolla',
            '--limit' => 1,
        ])->assertSuccessful();

        $generation->refresh();
        $this->assertSame('downloaded', $generation->image_status);
        $this->assertSame('CC BY-SA 4.0', $generation->image_license);
        $this->assertSame('Photographer', $generation->image_author);
        Storage::disk('public')->assertExists($generation->image_path);

        $this->getJson('/api/v1/vehicle-catalog/makes/toyota/models?year=2018')
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'corolla',
                'imageType' => 'model_photo',
                'generationCode' => 'default',
            ])
            ->assertJsonPath('data.0.imageAttribution.license', 'CC BY-SA 4.0')
            ->assertJsonPath('data.0.imageAttribution.author', 'Photographer');
    }

    public function test_it_rejects_an_unlicensed_search_result_and_keeps_logo_fallback(): void
    {
        config(['automind.vehicle_catalog_images.download_delay_ms' => 0]);
        $make = VehicleMake::query()->create([
            'code' => 'toyota',
            'name_en' => 'Toyota',
            'name_ar' => 'تويوتا',
            'logo_path' => 'images/vehicle-makes/toyota-logo.svg',
            'active' => true,
        ]);
        $model = VehicleModel::query()->create([
            'make_id' => $make->id,
            'code' => 'corolla',
            'name_en' => 'Corolla',
            'name_ar' => 'كورولا',
            'active' => true,
        ]);

        Http::fake([
            'https://commons.wikimedia.org/w/api.php*' => Http::response([
                'query' => ['pages' => [[
                    'title' => 'File:Toyota Corolla.jpg',
                    'imageinfo' => [[
                        'mime' => 'image/jpeg',
                        'thumburl' => 'https://thumb.wikimedia.org/toyota-corolla.jpg',
                        'extmetadata' => ['LicenseShortName' => ['value' => 'All Rights Reserved']],
                    ]],
                ]]],
            ]),
        ]);

        $this->artisan('automind:sync-vehicle-images', ['--make' => 'toyota', '--limit' => 1])->assertSuccessful();

        $this->assertSame('not_found', $model->generations()->sole()->image_status);
        $this->getJson('/api/v1/vehicle-catalog/makes/toyota/models')
            ->assertOk()
            ->assertJsonPath('data.0.imageType', 'brand_logo')
            ->assertJsonPath('data.0.imageUrl', 'http://localhost/images/vehicle-makes/toyota-logo.svg');
    }
}
