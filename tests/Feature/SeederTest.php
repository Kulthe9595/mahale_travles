<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_fills_business_contact_details_from_website(): void
    {
        $this->seed();

        $s = BusinessSetting::current();
        $this->assertSame('Mahale Tours and Travels', $s->name);
        $this->assertSame('+91 97656 79191', $s->phone);
        $this->assertSame('mahale.veeru1@gmail.com', $s->email);
        $this->assertSame('Navale Colony, Nashik–Pune Highway, near Shivaji Maharaj Statue, Nashik Road, Maharashtra', $s->address);
        $this->assertSame('Maharashtra', $s->state);
    }

    public function test_seeder_does_not_overwrite_details_already_entered(): void
    {
        BusinessSetting::current()->update(['phone' => '90000 00000', 'email' => 'other@example.com']);

        $this->seed();

        $s = BusinessSetting::current();
        $this->assertSame('90000 00000', $s->phone);
        $this->assertSame('other@example.com', $s->email);
    }
}
