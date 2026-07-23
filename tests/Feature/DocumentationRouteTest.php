<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationRouteTest extends TestCase
{
    public function test_landing_admin_and_non_production_swagger_are_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Your car talks.', false)
            ->assertSee('AI Powered Car Diagnostics', false);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('AutoMind Admin', false)
            ->assertSee('Landing page', false);

        $this->get('/docs/api')->assertOk()->assertSee('SwaggerUIBundle', false);
        $this->get('/docs/openapi.yaml')->assertOk()->assertHeader('Content-Type', 'application/yaml');
    }
}
