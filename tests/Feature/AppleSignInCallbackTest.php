<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppleSignInCallbackTest extends TestCase
{
    public function test_callback_only_forwards_expected_apple_fields_to_the_fixed_android_app(): void
    {
        config(['automind.apple_android_application_id' => 'com.automind.ai']);

        $response = $this->post('/callbacks/sign_in_with_apple', [
            'code' => 'authorization-code',
            'id_token' => 'identity-token',
            'state' => 'signed-state',
            'unexpected' => 'must-not-be-forwarded',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('intent://callback?', $location);
        $this->assertStringContainsString('code=authorization-code', $location);
        $this->assertStringContainsString('id_token=identity-token', $location);
        $this->assertStringContainsString('state=signed-state', $location);
        $this->assertStringContainsString('#Intent;package=com.automind.ai;scheme=signinwithapple;end', $location);
        $this->assertStringNotContainsString('unexpected', $location);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_callback_rejects_missing_state(): void
    {
        $this->post('/callbacks/sign_in_with_apple', ['code' => 'authorization-code'])
            ->assertSessionHasErrors('state');
    }
}
