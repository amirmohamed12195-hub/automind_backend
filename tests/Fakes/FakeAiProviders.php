<?php

namespace Tests\Fakes;

use App\Contracts\AiDiagnosticProvider;
use App\Contracts\AudioUnderstandingProvider;
use App\Contracts\SpeechTranscriptionProvider;
use App\Contracts\VisionUnderstandingProvider;
use App\Contracts\WebPriceSearchProvider;
use App\DTO\AiProviderResult;

class FakeAiProviders implements AiDiagnosticProvider, AudioUnderstandingProvider, SpeechTranscriptionProvider, VisionUnderstandingProvider, WebPriceSearchProvider
{
    public array $calls = [];

    public function synthesize(array $evidenceManifest, string $safetyIdentifier): AiProviderResult
    {
        $this->calls[] = ['synthesize', $evidenceManifest, $safetyIdentifier];

        return new AiProviderResult(self::report(), 'resp_test', 'fake-diagnostic', '/v1/responses', ['inputTokens' => 100, 'outputTokens' => 200]);
    }

    public function understand(string $disk, string $path, string $mimeType, string $safetyIdentifier): AiProviderResult
    {
        $this->calls[] = ['audio'];

        return new AiProviderResult(['quality' => 'moderate', 'observations' => [['code' => 'rhythmic_variation', 'confidence' => 0.55, 'textEn' => 'A rhythmic variation is audible.', 'textAr' => 'يمكن سماع تفاوت إيقاعي.']]], 'resp_audio', 'fake-audio', '/v1/chat/completions');
    }

    public function transcribe(string $disk, string $path, string $mimeType, ?string $language): AiProviderResult
    {
        $this->calls[] = ['speech'];

        return new AiProviderResult(['text' => 'The engine shakes.', 'language' => $language], 'transcript_test', 'fake-transcription', '/v1/audio/transcriptions');
    }

    public function observe(array $media, string $safetyIdentifier): AiProviderResult
    {
        $this->calls[] = ['vision'];

        return new AiProviderResult(['quality' => 'moderate', 'observations' => array_map(fn (array $item) => ['sourceMediaId' => $item['id'], 'code' => 'visible_surface', 'confidence' => 0.6, 'text' => ['en' => 'A visible automotive surface is present.', 'ar' => 'يظهر سطح من مكونات المركبة.'], 'boundingBox' => null], $media)], 'resp_vision', 'fake-vision', '/v1/responses');
    }

    public function research(array $vehicle, array $parts, array $market, string $safetyIdentifier): AiProviderResult
    {
        $this->calls[] = ['prices'];

        return new AiProviderResult(['status' => 'unavailable', 'reason' => 'No current attributable price.', 'quotes' => []], 'resp_prices', 'fake-price', '/v1/responses', [], ['sources' => []]);
    }

    public static function report(): array
    {
        return [
            'title' => ['en' => 'Possible cylinder misfire', 'ar' => 'احتمال اختلال احتراق إحدى الأسطوانات'],
            'summary' => ['en' => 'The evidence is consistent with a misfire, but inspection is required.', 'ar' => 'تتوافق الأدلة مع اختلال احتراق، لكن يلزم إجراء فحص.'],
            'overallConfidence' => 0.82, 'severity' => 'high', 'drivingRecommendation' => 'stopSoon',
            'drivingAdvice' => ['en' => 'Avoid hard acceleration and arrange an inspection soon.', 'ar' => 'تجنب التسارع الشديد ورتب فحصاً قريباً.'],
            'evidenceQuality' => 'moderate', 'professionalInspectionRequired' => true, 'emergencyWarnings' => [],
            'suspectedFaults' => [[
                'canonicalCode' => 'engine_misfire', 'obdCode' => 'P0301',
                'title' => ['en' => 'Possible engine misfire', 'ar' => 'احتمال اختلال احتراق المحرك'],
                'description' => ['en' => 'Multiple causes remain possible.', 'ar' => 'لا تزال هناك أسباب متعددة محتملة.'],
                'confidence' => 0.82, 'severity' => 'high',
                'evidence' => [['sourceType' => 'text', 'referenceId' => null, 'observation' => ['en' => 'The user reports shaking.', 'ar' => 'أبلغ المستخدم عن اهتزاز.'], 'reliability' => 0.7]],
                'possibleCauses' => [['en' => 'Ignition or fuel delivery issue', 'ar' => 'مشكلة في الإشعال أو توصيل الوقود']],
                'recommendedActions' => [], 'recommendedParts' => [],
            ]],
            'safeChecks' => [['text' => ['en' => 'With the engine off and cool, check whether the fuel cap is secure.', 'ar' => 'بعد إيقاف المحرك وبرودته، تحقق من إحكام غطاء الوقود.'], 'stopCondition' => ['en' => 'Stop if fuel or strong fumes are present.', 'ar' => 'توقف إذا وُجد وقود أو رائحة أبخرة قوية.']]],
            'recommendedActions' => [['code' => 'professional_inspection', 'text' => ['en' => 'Arrange a professional diagnostic inspection.', 'ar' => 'رتب فحصاً تشخيصياً متخصصاً.'], 'priority' => 1, 'professionalRequired' => true]],
            'limitations' => [['en' => 'Remote analysis cannot confirm the failed component.', 'ar' => 'لا يمكن للتحليل عن بعد تأكيد القطعة المعطلة.']],
            'missingEvidence' => ['engineSound', 'photos'],
        ];
    }
}
