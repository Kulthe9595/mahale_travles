<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MastersTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::Owner]);
    }

    private function carPayload(array $o = []): array
    {
        return array_merge([
            'registration_no' => 'mh 17 ab 1234',
            'model' => 'Toyota Innova Crysta',
            'type' => 'SUV',
            'seats' => 7,
            'rate_per_km' => '14',
            'rate_per_day' => '3000',
            'is_active' => true,
        ], $o);
    }

    public function test_owner_creates_car_with_unique_registration(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/cars', $this->carPayload())
            ->assertRedirect('/cars')->assertSessionHasNoErrors();

        $car = Car::firstOrFail();
        $this->assertSame('MH17AB1234', $car->registration_no);
        $this->assertSame('14.00', $car->rate_per_km);

        $this->actingAs($owner)->post('/cars', $this->carPayload(['registration_no' => 'MH-17-AB-1234']))
            ->assertSessionHasErrors('registration_no');
    }

    public function test_car_can_keep_its_own_registration_on_update(): void
    {
        $owner = $this->owner();
        $car = Car::factory()->create(['registration_no' => 'MH17AB1234']);

        $this->actingAs($owner)->put("/cars/{$car->id}", $this->carPayload(['rate_per_km' => '15']))
            ->assertRedirect('/cars')->assertSessionHasNoErrors();

        $this->assertSame('15.00', $car->fresh()->rate_per_km);
    }

    public function test_inactive_car_hidden_from_active_scope(): void
    {
        Car::factory()->create(['is_active' => true]);
        Car::factory()->create(['is_active' => false]);

        $this->assertSame(1, Car::active()->count());
    }

    public function test_cars_index_lists_cars(): void
    {
        Car::factory()->count(2)->create();

        $this->actingAs($this->owner())->get('/cars')
            ->assertInertia(fn ($page) => $page->component('Cars/Index')->has('cars', 2));
    }

    public function test_driver_crud(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/drivers', [
            'name' => 'Ramesh Pawar', 'phone' => '9822012345', 'licence_no' => 'MH1720110012345', 'is_active' => true,
        ])->assertRedirect('/drivers')->assertSessionHasNoErrors();

        $driver = Driver::firstOrFail();
        $this->actingAs($owner)->put("/drivers/{$driver->id}", [
            'name' => 'Ramesh Pawar', 'phone' => '9822012345', 'licence_no' => null, 'is_active' => false,
        ])->assertRedirect('/drivers');

        $this->assertFalse($driver->fresh()->is_active);
        $this->assertSame(0, Driver::active()->count());

        $this->actingAs($owner)->delete("/drivers/{$driver->id}")->assertRedirect('/drivers');
        $this->assertSame(0, Driver::count());
    }

    public function test_customer_gstin_validated_when_present(): void
    {
        $owner = $this->owner();
        $base = ['name' => 'Patil Industries', 'phone' => '+91 98765 43210', 'state' => 'Maharashtra'];

        $this->actingAs($owner)->post('/customers', $base + ['gstin' => 'BAD'])
            ->assertSessionHasErrors('gstin');

        $this->actingAs($owner)->post('/customers', $base + ['gstin' => '27aaacp1234q1z5'])
            ->assertSessionHasNoErrors();

        $c = Customer::firstOrFail();
        $this->assertSame('27AAACP1234Q1Z5', $c->gstin);
        $this->assertSame('919876543210', $c->phone_digits);
    }

    public function test_customer_without_gstin_and_default_state(): void
    {
        $this->actingAs($this->owner())->post('/customers', ['name' => 'Suresh Kale', 'phone' => '9876543210'])
            ->assertSessionHasNoErrors();

        $c = Customer::firstOrFail();
        $this->assertNull($c->gstin);
        $this->assertSame('Maharashtra', $c->state);
    }

    public function test_customer_create_returns_json_for_inline_picker(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/customers', ['name' => 'Inline Customer', 'phone' => '9000000001'])
            ->assertCreated()
            ->assertJsonPath('customer.name', 'Inline Customer');
    }

    public function test_customer_search_matches_phone_digits_and_name(): void
    {
        Customer::factory()->create(['name' => 'Patil Industries', 'phone' => '+91 98765 43210']);
        Customer::factory()->create(['name' => 'Kale Travels', 'phone' => '9000011111']);

        $this->actingAs($this->owner())->getJson('/customers/search?q=98765')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Patil Industries');

        $this->actingAs($this->owner())->getJson('/customers/search?q=kale')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Kale Travels');
    }

    public function test_staff_can_manage_masters_but_customer_role_cannot(): void
    {
        $staff = User::factory()->create(['role' => Role::Staff]);
        $this->actingAs($staff)->get('/cars')->assertOk();

        $customer = User::factory()->create(['role' => Role::Customer]);
        $this->actingAs($customer)->get('/cars')->assertForbidden();
    }

    public function test_cannot_delete_car_used_by_a_trip(): void
    {
        $this->markTestIncomplete('Enabled in Task 5 once trips exist.');
    }
}
