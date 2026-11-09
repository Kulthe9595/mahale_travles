<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.install_code' => 'MT-7Q4K-92XA']);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'install_code' => 'MT-7Q4K-92XA',
            'name' => 'Mahale',
            'email' => 'mahale.veeru1@gmail.com',
            'password' => 'strong-pass-1',
            'password_confirmation' => 'strong-pass-1',
        ], $o);
    }

    public function test_install_page_shows_while_no_owner_exists(): void
    {
        $this->get('/install')->assertOk()->assertInertia(fn ($p) => $p->component('Install'));
    }

    public function test_login_redirects_to_install_until_installed(): void
    {
        $this->get('/login')->assertRedirect('/install');
    }

    public function test_install_creates_owner_settings_and_logs_in(): void
    {
        $this->post('/install', $this->payload())->assertRedirect('/');

        $owner = User::where('email', 'mahale.veeru1@gmail.com')->firstOrFail();
        $this->assertSame(Role::Owner, $owner->role);
        $this->assertTrue(Hash::check('strong-pass-1', $owner->password));
        $this->assertSame('+91 97656 79191', BusinessSetting::current()->phone);
        $this->assertAuthenticatedAs($owner);
    }

    public function test_install_rejects_wrong_code(): void
    {
        $this->post('/install', $this->payload(['install_code' => 'WRONG']))->assertSessionHasErrors('install_code');
        $this->assertSame(0, User::count());
    }

    public function test_install_is_unavailable_without_a_configured_code(): void
    {
        config(['app.install_code' => null]);

        $this->get('/install')->assertNotFound();
        $this->post('/install', $this->payload())->assertNotFound();
    }

    public function test_install_locks_once_an_owner_exists(): void
    {
        User::factory()->create(['role' => Role::Owner]);

        $this->get('/install')->assertNotFound();
        $this->post('/install', $this->payload(['email' => 'intruder@example.com']))->assertNotFound();
        $this->assertSame(0, User::where('email', 'intruder@example.com')->count());
        $this->get('/login')->assertOk();
    }

    public function test_install_validates_password(): void
    {
        $this->post('/install', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');
    }
}
