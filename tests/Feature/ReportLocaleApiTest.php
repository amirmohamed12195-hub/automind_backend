<?php

namespace Tests\Feature;

use App\Contracts\AiDiagnosticProvider;
use App\Contracts\ReportAssistantProvider;
use App\Contracts\SpeechTranscriptionProvider;
use App\DTO\AiProviderResult;
use App\Jobs\AnalyzeDiagnosticSession;
use App\Jobs\SendMaintenanceReminders;
use App\Models\DiagnosticMedia;
use App\Models\DiagnosticReport;
use App\Models\DiagnosticSession;
use App\Models\SymptomDefinition;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Services\Diagnostics\DiagnosticManifestBuilder;
use App\Services\Diagnostics\DiagnosticReportPersister;
use App\Support\ContentLocale;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\Fakes\FakeAiProviders;

class ReportLocaleApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.enabled' => false]);
    }

    public function test_creation_and_update_keep_input_detection_separate_from_app_report_locale(): void
    {
        $user = $this->actingAsUser(['locale' => 'ar']);
        $vehicle = Vehicle::factory()->for($user)->create();
        $payload = ['vehicleId' => $vehicle->id, 'description' => 'noise', 'inputLocale' => 'ar', 'reportLocale' => 'en', 'consentVersion' => 'privacy-v1'];
        $id = $this->withHeader('Accept-Language', 'ar-EG')->postJson('/api/v1/diagnoses', $payload)
            ->assertCreated()->assertJsonPath('data.inputLocale', 'en')->assertJsonPath('data.reportLocale', 'ar')->json('data.id');
        $this->withHeader('Accept-Language', 'en')->patchJson("/api/v1/diagnoses/$id", ['description' => 'المحرك يهتز عند التوقف', 'reportLocale' => 'ar'])
            ->assertOk()->assertJsonPath('data.inputLocale', 'ar')->assertJsonPath('data.reportLocale', 'en');
        $session = DiagnosticSession::findOrFail($id);
        $session->update(['input_locale' => 'en']);
        $manifest = app(DiagnosticManifestBuilder::class)->build($session);
        $this->assertSame('ar', $manifest['trustedMetadata']['inputLocale']);
        $this->assertSame('en', $manifest['trustedMetadata']['reportLocale']);
        $this->withHeader('Accept-Language', 'ar')->patchJson("/api/v1/diagnoses/$id", ['reportLocale' => 'en'])
            ->assertOk()->assertJsonPath('data.reportLocale', 'ar');
    }

    public function test_analysis_and_retry_capture_current_app_language_and_repair_cached_manifest_locale(): void
    {
        Queue::fake();
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'description' => 'Why does the engine shake?', 'status' => 'draft', 'report_locale' => 'en']);
        $manifest = app(DiagnosticManifestBuilder::class)->build($session);
        $session->update(['input_manifest' => $manifest, 'input_hash' => hash('sha256', json_encode($manifest))]);
        $this->withHeader('Accept-Language', 'ar')->postJson("/api/v1/diagnoses/$session->id/analyze")->assertAccepted();
        $this->assertSame('ar', $session->fresh()->report_locale);
        $fake = new FakeAiProviders;
        $this->app->instance(AiDiagnosticProvider::class, $fake);
        $this->app->call([new AnalyzeDiagnosticSession($session->id), 'handle']);
        $this->assertSame('ar', data_get($session->fresh()->input_manifest, 'trustedMetadata.reportLocale'));
        $this->assertSame('ar', $fake->calls[0][1]['trustedMetadata']['reportLocale']);
        $this->assertSame('en', $fake->calls[0][1]['trustedMetadata']['inputLocale']);
        $session->refresh()->update(['status' => 'failed']);
        $this->withHeader('Accept-Language', 'en')->postJson("/api/v1/diagnoses/$session->id/retry")->assertAccepted();
        $this->assertSame('en', $session->fresh()->report_locale);
    }

    public function test_technical_description_uses_input_hint_but_report_always_uses_app_language(): void
    {
        $user = $this->actingAsUser(['locale' => 'ar']);
        $vehicle = Vehicle::factory()->for($user)->create();
        $payload = ['vehicleId' => $vehicle->id, 'description' => 'Toyota Corolla P0301 800 RPM', 'consentVersion' => 'privacy-v1'];
        $this->withHeader('Accept-Language', 'ar')->postJson('/api/v1/diagnoses', $payload)
            ->assertCreated()->assertJsonPath('data.inputLocale', 'ar')->assertJsonPath('data.reportLocale', 'ar');
        $this->postJson('/api/v1/diagnoses', [...$payload, 'inputLocale' => 'en', 'reportLocale' => 'en'])
            ->assertCreated()->assertJsonPath('data.inputLocale', 'en')->assertJsonPath('data.reportLocale', 'ar');
    }

    public function test_detail_history_and_other_ui_use_current_app_locale_without_rerunning_ai(): void
    {
        $report = $this->report('Why does the engine shake?');
        $fake = new FakeAiProviders;
        $this->app->instance(AiDiagnosticProvider::class, $fake);
        SymptomDefinition::create(['code' => 'engine', 'label_en' => 'Engine', 'label_ar' => 'المحرك', 'active' => true]);
        foreach (['ar', 'en', 'ar'] as $locale) {
            $this->withHeader('Accept-Language', $locale)->getJson("/api/v1/reports/$report->id")
                ->assertOk()->assertJsonPath('data.reportLocale', $locale)->assertJsonPath('meta.locale', $locale)
                ->assertJsonPath('data.title', FakeAiProviders::report()['title'][$locale])
                ->assertJsonPath('data.summary', FakeAiProviders::report()['summary'][$locale])
                ->assertJsonPath('data.drivingAdvice', FakeAiProviders::report()['drivingAdvice'][$locale])
                ->assertJsonPath('data.suspectedFaults.0.title', FakeAiProviders::report()['suspectedFaults'][0]['title'][$locale])
                ->assertJsonPath('data.suspectedFaults.0.possibleCauses.0', FakeAiProviders::report()['suspectedFaults'][0]['possibleCauses'][0][$locale])
                ->assertJsonPath('data.suspectedFaults.0.evidence.0.observation', FakeAiProviders::report()['suspectedFaults'][0]['evidence'][0]['observation'][$locale])
                ->assertJsonPath('data.safeChecks.0.text', FakeAiProviders::report()['safeChecks'][0]['text'][$locale])
                ->assertJsonPath('data.recommendedActions.0.text', FakeAiProviders::report()['recommendedActions'][0]['text'][$locale])
                ->assertJsonPath('data.limitations.0', FakeAiProviders::report()['limitations'][0][$locale]);
            $this->getJson("/api/v1/diagnoses/$report->diagnostic_session_id/report")->assertOk()->assertJsonPath('data.reportLocale', $locale);
            $this->getJson('/api/v1/reports')->assertOk()->assertJsonPath('data.0.reportLocale', $locale)->assertJsonPath('data.0.title', FakeAiProviders::report()['title'][$locale]);
        }
        $this->getJson('/api/v1/symptoms')->assertOk()->assertJsonPath('data.0.label', 'المحرك');
        $this->assertSame([], $fake->calls);
    }

    public function test_arabic_description_and_legacy_report_locale_do_not_override_english_app(): void
    {
        $report = $this->report('العربية BMW بتقطع مع RPM عالي');
        $report->session->update(['report_locale' => 'ar']);
        $this->withHeader('Accept-Language', 'en')->getJson("/api/v1/reports/$report->id")
            ->assertOk()->assertJsonPath('data.reportLocale', 'en')->assertJsonPath('meta.locale', 'en')
            ->assertJsonPath('data.title', FakeAiProviders::report()['title']['en'])
            ->assertJsonPath('data.missingEvidenceLabels.0', 'Engine sound recording');
    }

    public function test_without_natural_language_detail_follows_current_app_locale(): void
    {
        $report = $this->report('Toyota Corolla P0301 RPM 800');
        foreach (['ar', 'en'] as $locale) {
            $this->withHeader('Accept-Language', $locale)->getJson("/api/v1/reports/$report->id")
                ->assertOk()->assertJsonPath('data.reportLocale', $locale)->assertJsonPath('data.title', FakeAiProviders::report()['title'][$locale]);
        }
    }

    public function test_share_signs_app_locale_json_follows_app_and_browser_html_preserves_sender_locale(): void
    {
        $report = $this->report('The engine shakes.');
        $url = $this->withHeader('Accept-Language', 'ar')->getJson("/api/v1/reports/$report->id/share")
            ->assertOk()->json('data.url');
        $this->assertStringContainsString('locale=ar', $url);
        $this->getJson($url)->assertOk()->assertJsonPath('data.reportLocale', 'ar');
        $this->withHeader('Accept-Language', 'en')->getJson($url)->assertOk()
            ->assertJsonPath('data.reportLocale', 'en')->assertJsonPath('meta.locale', 'en')
            ->assertJsonPath('data.title', FakeAiProviders::report()['title']['en']);
        $this->get($url, ['Accept' => 'text/html'])->assertOk()->assertSee('تقرير تشخيص مشترك')->assertDontSee('driveWithCaution');
        $this->withHeader('Accept-Language', '')->getJson($url)->assertOk()->assertJsonPath('data.reportLocale', 'en');
        $this->getJson(str_replace('locale=ar', 'locale=en', $url))->assertForbidden();
    }

    public function test_public_shared_json_uses_signed_locale_only_without_supported_header_or_account(): void
    {
        $session = DiagnosticSession::factory()->create(['description' => 'The engine shakes.']);
        $report = app(DiagnosticReportPersister::class)->persist($session, FakeAiProviders::report());
        $url = URL::temporarySignedRoute('reports.shared', now()->addMinutes(5), ['report' => $report->id, 'locale' => 'ar']);
        foreach (['', 'fr-FR'] as $header) {
            $this->withHeader('Accept-Language', $header)->getJson($url)->assertOk()
                ->assertJsonPath('data.reportLocale', 'ar')->assertJsonPath('meta.locale', 'ar')
                ->assertJsonPath('data.title', FakeAiProviders::report()['title']['ar']);
        }
        $this->withHeader('Accept-Language', 'en')->getJson($url)->assertOk()->assertJsonPath('data.reportLocale', 'en');
        $this->get($url, ['Accept' => 'text/html'])->assertOk()->assertSee('تقرير تشخيص مشترك');
    }

    public function test_follow_up_answers_always_use_current_app_language_and_reuse_stored_translations(): void
    {
        $report = $this->report('المحرك يهتز أثناء التوقف');
        $fake = new FakeAiProviders;
        $this->app->instance(ReportAssistantProvider::class, $fake);
        $this->withHeader('Accept-Language', 'ar')->postJson("/api/v1/reports/$report->id/follow-ups", ['question' => 'Can I drive?'])
            ->assertCreated()->assertJsonPath('data.answerLocale', 'ar')
            ->assertJsonPath('data.question', 'Can I drive?')
            ->assertJsonPath('data.suggestedEvidence.0', 'أضف لقطة OBD أثناء دوران المحرك في وضع الخمول.');
        $this->withHeader('Accept-Language', 'en')->postJson("/api/v1/reports/$report->id/follow-ups", ['question' => 'هل أستطيع القيادة؟'])
            ->assertCreated()->assertJsonPath('data.answerLocale', 'en')->assertJsonPath('data.question', 'هل أستطيع القيادة؟');
        foreach (['en', 'ar'] as $locale) {
            $answers = $this->withHeader('Accept-Language', $locale)->getJson("/api/v1/reports/$report->id/follow-ups")->assertOk()->json('data');
            $this->assertSame([$locale, $locale], array_column($answers, 'answerLocale'));
            foreach ($answers as $answer) {
                $this->assertSame($locale, app(ContentLocale::class)->detect($answer['answer']));
            }
        }
        $this->assertCount(2, $fake->calls);
    }

    public function test_wrong_language_ai_report_fails_cleanly_without_publishing(): void
    {
        $user = $this->actingAsUser(['locale' => 'ar']);
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'status' => 'queued', 'description' => 'المحرك يهتز']);
        $this->app->instance(AiDiagnosticProvider::class, new class extends FakeAiProviders
        {
            public function synthesize(array $evidenceManifest, string $safetyIdentifier): AiProviderResult
            {
                $data = self::report();
                $data['summary']['ar'] = $data['summary']['en'];

                return new AiProviderResult($data, 'fake', 'fake', '/v1/responses');
            }
        });
        $this->app->call([new AnalyzeDiagnosticSession($session->id), 'handle']);
        $this->assertSame('failed', $session->fresh()->status);
        $this->assertSame('schema', $session->fresh()->error_code);
        $this->assertNull($session->fresh()->report);
        $this->withHeader('Accept-Language', 'ar')->getJson("/api/v1/diagnoses/$session->id/status")
            ->assertOk()->assertJsonPath('data.error.message', 'تعذر التأكد من صحة نتيجة التشخيص. حاول مرة أخرى.');
    }

    public function test_wrong_language_follow_up_is_not_published(): void
    {
        $report = $this->report('المحرك يهتز');
        $this->app->instance(ReportAssistantProvider::class, new class extends FakeAiProviders
        {
            public function answer(array $reportContext, ?string $question, array $images, string $safetyIdentifier): AiProviderResult
            {
                return new AiProviderResult(['answer' => ['en' => 'Arrange an inspection.', 'ar' => 'Arrange an inspection.'], 'suggestedEvidence' => []], 'fake', 'fake', '/v1/responses');
            }
        });
        $this->withHeader('Accept-Language', 'ar')->postJson("/api/v1/reports/$report->id/follow-ups", ['question' => 'هل أستطيع القيادة؟'])
            ->assertStatus(502)->assertJsonPath('error.code', 'FOLLOW_UP_INVALID_RESPONSE');
        $this->assertSame(0, $report->followUps()->count());
    }

    public function test_vehicle_display_and_estimate_labels_are_localized_without_changing_codes(): void
    {
        $report = $this->report('المحرك يهتز');
        $make = VehicleMake::create(['code' => 'toyota', 'name_en' => 'Toyota', 'name_ar' => 'تويوتا']);
        $make->models()->create(['code' => 'corolla', 'name_en' => 'Corolla', 'name_ar' => 'كورولا']);
        $estimate = $report->estimate()->create(['status' => 'partial', 'country_code' => 'US', 'currency' => 'USD']);
        $estimate->lineItems()->create(['category' => 'labor', 'canonical_code' => 'general_service_labor', 'quantity' => 1, 'unit' => 'job', 'low_amount' => 10, 'typical_amount' => 20, 'high_amount' => 30, 'currency' => 'USD']);
        $this->withHeader('Accept-Language', 'ar')->getJson("/api/v1/vehicles/$report->vehicle_id")
            ->assertOk()->assertJsonPath('data.brand', 'Toyota')->assertJsonPath('data.model', 'Corolla')
            ->assertJsonPath('data.displayBrand', 'تويوتا')->assertJsonPath('data.displayModel', 'كورولا')->assertJsonPath('data.displayLocale', 'ar');
        $this->getJson("/api/v1/reports/$report->id")->assertOk()->assertJsonPath('data.vehicleName', 'تويوتا كورولا')
            ->assertJsonPath('data.serviceEstimate.lineItems.0.canonicalCode', 'general_service_labor')
            ->assertJsonPath('data.serviceEstimate.lineItems.0.displayName', 'تكلفة العمل')->assertJsonPath('data.serviceEstimate.lineItems.0.unitLabel', 'خدمة');
    }

    public function test_spoken_question_detects_input_language_without_changing_app_report_language(): void
    {
        $user = $this->actingAsUser(['locale' => 'ar']);
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'description' => null, 'status' => 'queued', 'input_locale' => 'ar', 'report_locale' => 'ar']);
        DiagnosticMedia::create([
            'diagnostic_session_id' => $session->id, 'media_kind' => 'spoken_description', 'storage_disk' => 'local',
            'storage_path' => 'fixtures/question.wav', 'original_filename' => 'question.wav', 'mime_type' => 'audio/wav',
            'extension' => 'wav', 'byte_size' => 100, 'sha256' => hash('sha256', 'spoken-question'),
            'upload_status' => 'uploaded', 'scan_status' => 'clean', 'processing_status' => 'ready',
        ]);
        $speech = new class extends FakeAiProviders
        {
            public array $languageHints = [];

            public function transcribe(string $disk, string $path, string $mimeType, ?string $language): AiProviderResult
            {
                $this->languageHints[] = $language;

                return new AiProviderResult(['text' => 'Why does my engine shake?', 'language' => null], 'fake', 'fake', '/v1/audio/transcriptions');
            }
        };
        $this->app->instance(SpeechTranscriptionProvider::class, $speech);
        $this->app->instance(AiDiagnosticProvider::class, new FakeAiProviders);
        $this->app->call([new AnalyzeDiagnosticSession($session->id), 'handle']);
        $this->assertSame([null], $speech->languageHints);
        $session->refresh();
        $this->assertSame('en', $session->input_locale);
        $this->assertSame('ar', $session->report_locale);
        $this->assertSame('ar', data_get($session->input_manifest, 'trustedMetadata.reportLocale'));
        $this->assertSame('en', data_get($session->input_manifest, 'trustedMetadata.inputLocale'));
        $this->withHeader('Accept-Language', 'ar')->getJson("/api/v1/reports/{$session->report->id}")
            ->assertOk()->assertJsonPath('data.reportLocale', 'ar');
        $session->update(['description' => 'المحرك يهتز أثناء القيادة']);
        $this->withHeader('Accept-Language', 'en')->getJson("/api/v1/reports/{$session->report->id}")->assertOk()->assertJsonPath('data.reportLocale', 'en');
    }

    public function test_bearer_authenticated_user_locale_is_fallback_when_header_is_missing_or_unsupported(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);
        $token = $user->createToken('locale-test')->plainTextToken;
        SymptomDefinition::create(['code' => 'engine', 'label_en' => 'Engine', 'label_ar' => 'المحرك', 'active' => true]);
        $this->withToken($token)->withHeader('Accept-Language', '')->getJson('/api/v1/symptoms')->assertOk()->assertJsonPath('data.0.label', 'المحرك');
        $this->withHeader('Accept-Language', 'fr-FR')->getJson('/api/v1/symptoms')->assertOk()->assertJsonPath('data.0.label', 'المحرك');
        $this->withHeader('Accept-Language', 'en-US')->getJson('/api/v1/symptoms')->assertOk()->assertJsonPath('data.0.label', 'Engine');
    }

    public function test_missing_translation_never_copies_english_prose_into_arabic_report(): void
    {
        $report = $this->report('المحرك يهتز');
        $report->translations()->where('locale', 'ar')->delete();
        $report->faults->first()->translations()->where('locale', 'ar')->delete();
        $report->update(['limitations' => [['en' => 'An inspection is needed.']]]);
        $this->withHeader('Accept-Language', 'ar')->getJson("/api/v1/reports/$report->id")
            ->assertOk()->assertJsonPath('data.reportLocale', 'ar')->assertJsonPath('data.title', null)
            ->assertJsonPath('data.suspectedFaults.0.title', null)->assertJsonPath('data.limitations', []);
    }

    public function test_report_action_errors_always_follow_current_app_language(): void
    {
        $report = $this->report('The engine shakes.');
        $this->withHeader('Accept-Language', 'ar')->postJson("/api/v1/reports/$report->id/feedback", [])
            ->assertUnprocessable()->assertJsonPath('error.message', trans('api.validation_failed', [], 'ar'));
        $this->postJson("/api/v1/reports/$report->id/follow-ups", [])
            ->assertUnprocessable()->assertJsonPath('error.details.question.0', trans('api.follow_up_content_required', [], 'ar'));
        $this->postJson("/api/v1/reports/$report->id/maintenance-reminders", [])
            ->assertUnprocessable()->assertJsonPath('error.message', trans('api.validation_failed', [], 'ar'));
        $this->withHeader('Accept-Language', 'en')->postJson("/api/v1/reports/$report->id/follow-ups", ['question' => 'هل أستطيع القيادة؟', 'photos' => 'invalid'])
            ->assertUnprocessable()->assertJsonPath('error.message', trans('api.validation_failed', [], 'en'));
        SymptomDefinition::create(['code' => 'engine', 'label_en' => 'Engine', 'label_ar' => 'المحرك', 'active' => true]);
        $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/symptoms')->assertOk()->assertJsonPath('data.0.label', 'المحرك');
    }

    public function test_maintenance_notifications_and_definitions_stay_in_app_language(): void
    {
        config(['automind.push_notifications_enabled' => false]);
        $report = $this->report('The engine shakes.');
        $report->vehicle->user->update(['maintenance_reminders_enabled' => true]);
        $report->vehicle->reminders()->update(['due_date' => now()->subDay()]);
        $make = VehicleMake::create(['code' => 'toyota', 'name_en' => 'Toyota', 'name_ar' => 'تويوتا']);
        $make->models()->create(['code' => 'corolla', 'name_en' => 'Corolla', 'name_ar' => 'كورولا']);
        $this->app->call([new SendMaintenanceReminders, 'handle']);
        $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.title', 'تذكير بالصيانة')->assertJsonPath('data.0.body', 'حان موعد صيانة تويوتا كورولا.');
        $this->getJson('/api/v1/maintenance-service-definitions')->assertOk()->assertJsonPath('data.0.name', 'متابعة التشخيص');
        $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.body', 'Maintenance is due for Toyota Corolla.');
    }

    private function report(?string $description): DiagnosticReport
    {
        $user = $this->actingAsUser(['locale' => 'en']);
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'description' => $description, 'report_locale' => 'en', 'status' => 'completed']);

        return app(DiagnosticReportPersister::class)->persist($session, FakeAiProviders::report());
    }
}
