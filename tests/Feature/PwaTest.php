<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_served_with_install_fields(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Mahale Tours and Travels', $manifest['name']);
        $this->assertSame('Mahale Tours', $manifest['short_name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertFileExists(public_path('sw.js'));
    }

    public function test_app_layout_links_manifest_and_icons(): void
    {
        $owner = User::factory()->create(['role' => Role::Owner]);

        $html = $this->actingAs($owner)->get('/')->getContent();

        $this->assertStringContainsString('rel="manifest" href="/manifest.webmanifest"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
    }

    public function test_service_worker_never_caches_pages_or_bills(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("'/build/'", $sw);
        $this->assertStringNotContainsString('/b/', $sw);
    }
}
