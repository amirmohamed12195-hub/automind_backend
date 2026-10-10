<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationRouteTest extends TestCase
{
    public function test_landing_admin_login_and_non_production_swagger_are_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Your car talks.', false)
            ->assertSee('AI Powered Car Diagnostics', false);

        $this->get('/admin')
            ->assertRedirect(route('admin.login'));

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Welcome back', false);

        $this->get('/docs/api')->assertOk()->assertSee('SwaggerUIBundle', false);
        $this->get('/docs/openapi.yaml')->assertOk()->assertHeader('Content-Type', 'application/yaml');
    }

    public function test_store_badges_use_configured_links_without_javascript(): void
    {
        config([
            'public.app_store_url' => 'https://apps.apple.com/app/id6801621951',
            'public.play_store_url' => 'https://play.google.com/store/apps/details?id=com.automind.ai',
        ]);
        foreach (['/', '/download'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('href="'.config('public.app_store_url').'"', false)
                ->assertSee('href="'.config('public.play_store_url').'"', false)
                ->assertSee('images/stores/app-store.svg')
                ->assertSee('images/stores/google-play.png')
                ->assertDontSee('Coming soon on');
        }
    }
}
