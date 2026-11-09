<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'secret-pass',
            'role' => Role::Owner,
        ]);
    }

    public function test_owner_can_log_in_with_email_and_password(): void
    {
        $user = $this->owner();

        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'secret-pass'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->owner();

        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_role_gets_403_on_dashboard(): void
    {
        $customer = User::factory()->create(['role' => Role::Customer]);

        $this->actingAs($customer)->get('/')->assertForbidden();
    }

    public function test_staff_can_open_dashboard(): void
    {
        $staff = User::factory()->create(['role' => Role::Staff]);

        $this->actingAs($staff)->get('/')->assertOk();
    }

    public function test_login_is_rate_limited_after_5_failures(): void
    {
        $this->owner();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong']);
        }

        $response = $this->post('/login', ['email' => 'owner@example.com', 'password' => 'secret-pass']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_remember_me_sets_recaller_cookie(): void
    {
        $this->owner();

        $response = $this->post('/login', [
            'email' => 'owner@example.com',
            'password' => 'secret-pass',
            'remember' => true,
        ]);

        $recaller = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($recaller);
    }

    public function test_logout(): void
    {
        $this->actingAs($this->owner())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
