<?php

namespace Workbench\App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Workbench host application service provider.
 *
 * This provider stands in for the host application that would normally
 * consume the BPMN Engine package. It holds the glue that a real app
 * (e.g. saccharine-demo) would provide, so the package can be developed
 * and integration-tested without a host application.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The package registers its gates so they pass for every request while
        // running inside Workbench. In a real host app these abilities would be
        // backed by a user's roles (e.g. via filament-shield); here we open them
        // up so the dashboard, designer, and API routes are reachable without a
        // full authentication stack.
        Gate::before(function (?Authenticatable $user, string $ability) {
            if (str_starts_with($ability, 'Bpmn:')) {
                return true;
            }
        });
    }
}
