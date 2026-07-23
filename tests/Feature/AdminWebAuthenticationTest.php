<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminWebAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.username' => 'admin',
            'admin.password_hash' => password_hash('admin', PASSWORD_BCRYPT),
        ]);
    }

    public function test_guest_is_redirected_to_the_admin_login_page(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_page_is_available(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false);
    }

    public function test_invalid_admin_credentials_are_rejected(): void
    {
        $this->from('/admin/login')
            ->post('/admin/login', [
                'username' => 'admin',
                'password' => 'incorrect',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('username');

        $this->get('/admin')
            ->assertRedirect(route('admin.login'));
    }

    public function test_valid_admin_credentials_open_the_dashboard(): void
    {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'admin',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Super administrator');
    }

    public function test_admin_can_log_out(): void
    {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'admin',
        ]);

        $this->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->get('/admin')
            ->assertRedirect(route('admin.login'));
    }
}
