<?php

namespace Tests\Feature;

use App\Models\TiuMember;
use Tests\TestCase;

/**
 * Installable app (PWA): the manifest, its icons, the service worker and the
 * "Install app" button on the Home Dashboard.
 *
 * Read-only: no RefreshDatabase, the suite runs against the application's real
 * connection and every request below is a GET.
 */
class ProgressiveWebAppTest extends TestCase
{
    /**
     * The manifest is valid JSON, is installable (standalone) and every icon it
     * points at really exists on disk.
     */
    public function test_manifest_is_valid_and_every_icon_exists()
    {
        $path = public_path('manifest.webmanifest');

        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);

        $this->assertIsArray($manifest, 'manifest.webmanifest is not valid JSON.');
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/home', $manifest['start_url']);
        $this->assertNotEmpty($manifest['icons'], 'A manifest needs at least one icon.');

        foreach ($manifest['icons'] as $icon) {
            $this->assertArrayHasKey('src', $icon);
            $this->assertFileExists(
                public_path(ltrim($icon['src'], '/')),
                "The manifest references a missing icon: {$icon['src']}"
            );
        }

        $this->assertContains(
            'maskable',
            array_column($manifest['icons'], 'purpose'),
            'Android crops the launcher icon, so a maskable icon is required.'
        );
    }

    /**
     * The signed-in shell links the manifest, registers the service worker and
     * captures `beforeinstallprompt`; the dashboard renders the install button.
     */
    public function test_home_dashboard_links_the_manifest_and_offers_the_install_button()
    {
        $member = TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the dashboard request.');
        }

        $response = $this->actingAs($member)->get('/home');

        $response->assertStatus(200);
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('manifest.webmanifest', false);
        $response->assertSee('icons/icon-192-v2.png', false);
        $response->assertSee('service-worker.js', false);
        $response->assertSee('beforeinstallprompt', false);
        $response->assertSee('data-pwa-install', false);
        $response->assertSee('data-pwa-install-button', false);
        $response->assertSee('Add to Home Screen', false);
    }

    /**
     * The worker must keep the handlers that make the app installable and the
     * assignment notifications clickable.
     */
    public function test_service_worker_handles_install_fetch_and_notification_clicks()
    {
        $worker = file_get_contents(public_path('service-worker.js'));

        $this->assertStringContainsString("addEventListener('install'", $worker);
        $this->assertStringContainsString("addEventListener('activate'", $worker);
        $this->assertStringContainsString("addEventListener('fetch'", $worker);
        $this->assertStringContainsString("addEventListener('notificationclick'", $worker);
        $this->assertStringContainsString('clients.openWindow', $worker);
    }
}
