<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    public function test_guest_login_page_renders_without_error(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guest_manager_login_page_is_not_a_server_error(): void
    {
        $response = $this->get('/gestor/login');

        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_login_page_links_to_manager_access(): void
    {
        $response = $this->get('/login');

        $response->assertSee(route('manager.login'), false);
        $response->assertSee(route('user.login'), false);
    }

    public function test_manager_login_route_is_registered(): void
    {
        $this->assertNotNull(route('manager.login', [], false));
        $this->assertNotNull(route('manager.login.store', [], false));
        $this->assertNotNull(route('manager.register', [], false));
    }

    /**
     * @return array<int, string>
     */
    private function registeredRouteNames(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->values()
            ->all();
    }

    public function test_portal_manager_routes_are_registered(): void
    {
        $routes = [
            'manager.chargers.index',
            'manager.chargers.create',
            'manager.chargers.store',
            'manager.chargers.edit',
            'manager.chargers.update',
            'manager.demand.index',
            'manager.demand.decide',
            'manager.establishment.setup',
            'manager.establishment.store',
            'manager.establishment.edit',
            'manager.establishment.update',
            'manager.profile',
            'manager.profile.update',
        ];

        $defined = $this->registeredRouteNames();

        foreach ($routes as $name) {
            $this->assertContains($name, $defined, "Rota {$name} nao registrada.");
        }
    }

    public function test_portal_user_routes_are_registered(): void
    {
        $routes = [
            'wallet.index',
            'wallet.deposit.form',
            'wallet.deposit.store',
            'wallet.history',
            'reservations.store',
            'reservations.cancel',
            'reservations.status',
            'charging-sessions.show',
            'charging-sessions.pay',
            'reservations.start-test',
            'charging-sessions.advance-test',
            'charging-sessions.end-test',
            'user.update-password',
        ];

        $defined = $this->registeredRouteNames();

        foreach ($routes as $name) {
            $this->assertContains($name, $defined, "Rota {$name} nao registrada.");
        }
    }

    public function test_every_route_referenced_by_views_exists(): void
    {
        $defined = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->values()
            ->all();

        $referenced = [];

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        ) as $file) {
            if (! $file->isFile() || $file->getFilename() === 'welcome.blade.php') {
                continue;
            }

            preg_match_all(
                "/route\('([a-z][a-z0-9._-]*)'/",
                file_get_contents($file->getPathname()),
                $matches
            );

            $referenced = array_merge($referenced, $matches[1]);
        }

        $missing = array_values(array_unique(array_diff(array_unique($referenced), $defined)));

        $this->assertSame([], $missing, 'Views referenciam rotas inexistentes: '.implode(', ', $missing));
    }
}
