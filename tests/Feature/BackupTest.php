<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Trip;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_downloads_sql_backup_with_all_data(): void
    {
        $owner = User::factory()->create(['role' => Role::Owner]);
        $invoice = app(InvoiceService::class)->generate(Trip::factory()->create());

        $response = $this->actingAs($owner)->get('/settings/backup');

        $response->assertOk();
        $this->assertStringContainsString('attachment; filename=mahale-backup-', $response->headers->get('content-disposition'));
        $sql = $response->streamedContent();
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO `invoices`', $sql);
        $this->assertStringContainsString("'{$invoice->invoice_no}'", $sql);
        $this->assertStringContainsString('INSERT INTO `invoice_items`', $sql);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $sql);
    }

    public function test_staff_cannot_download_backup(): void
    {
        $staff = User::factory()->create(['role' => Role::Staff]);

        $this->actingAs($staff)->get('/settings/backup')->assertForbidden();
    }
}
