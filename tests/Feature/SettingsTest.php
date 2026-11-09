<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mahale Tours and Travels',
            'address' => 'Shrirampur, Ahilyanagar',
            'phone' => '9876543210',
            'email' => 'mahale@example.com',
            'gstin' => '27ABCDE1234F1Z5',
            'state' => 'Maharashtra',
            'sac_code' => '996601',
            'gst_rate' => '5',
            'min_km_per_day' => 250,
            'invoice_prefix' => 'MTT',
            'bank_details' => 'SBI A/c 123, IFSC SBIN0000001',
            'upi_id' => 'mahale@upi',
            'terms' => 'Thank you for travelling with us.',
        ], $overrides);
    }

    public function test_current_settings_have_defaults(): void
    {
        $s = BusinessSetting::current();

        $this->assertSame('5.00', $s->gst_rate);
        $this->assertSame(0, $s->min_km_per_day);
        $this->assertSame('MTT', $s->invoice_prefix);
        $this->assertSame('Maharashtra', $s->state);
        $this->assertSame(1, BusinessSetting::count());
        BusinessSetting::current();
        $this->assertSame(1, BusinessSetting::count());
    }

    public function test_staff_cannot_update_settings(): void
    {
        $staff = User::factory()->create(['role' => Role::Staff]);

        $this->actingAs($staff)->get('/settings')->assertForbidden();
        $this->actingAs($staff)->post('/settings', $this->validPayload())->assertForbidden();
    }

    public function test_owner_updates_settings_and_uploads_logo(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => Role::Owner]);

        $this->actingAs($owner)
            ->post('/settings', $this->validPayload([
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ]))
            ->assertRedirect('/settings')
            ->assertSessionHasNoErrors();

        $s = BusinessSetting::current();
        $this->assertSame('27ABCDE1234F1Z5', $s->gstin);
        $this->assertSame(250, $s->min_km_per_day);
        $this->assertNotNull($s->logo_path);
        Storage::disk('public')->assertExists($s->logo_path);
    }

    public function test_invalid_gstin_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => Role::Owner]);

        $this->actingAs($owner)
            ->post('/settings', $this->validPayload(['gstin' => 'ABC123']))
            ->assertSessionHasErrors('gstin');
    }

    public function test_gstin_is_uppercased_and_optional(): void
    {
        $owner = User::factory()->create(['role' => Role::Owner]);

        $this->actingAs($owner)->post('/settings', $this->validPayload(['gstin' => '27abcde1234f1z5']))
            ->assertSessionHasNoErrors();
        $this->assertSame('27ABCDE1234F1Z5', BusinessSetting::current()->gstin);

        $this->actingAs($owner)->post('/settings', $this->validPayload(['gstin' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull(BusinessSetting::current()->gstin);
    }
}
