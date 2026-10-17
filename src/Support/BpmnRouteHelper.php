<?php

namespace Saccharine\Bpmn\Support;

use Illuminate\Support\Facades\Route;

/**
 * Resolves the canonical "dashboard" URL for Saccharine BPMN.
 *
 * The package publishes a single entry point (bpmn.index). Which UI is shown
 * behind it is a runtime concern:
 *
 *   1. An explicit override via config('saccharine-bpmn.dashboard_url')
 *   2. The Filament resource table, when a panel has the BPMN resources registered
 *   3. The package's own Blade dashboard
 *
 * This keeps every link (the designer's back button, the post-archive redirect,
 * the instances header) pointed at the UI the user actually entered from, instead
 * of always ejecting them out of an admin panel to the standalone view.
 */
class BpmnRouteHelper
{
    /**
     * The Filament resource route name for workflow definitions, if registered.
     *
     * Filament names resource routes after the resource slug, e.g.
     * filament.{panel}.resources.{slug}.index, where slashes in the slug become
     * dots (bpmn/workflows/definitions -> bpmn.workflows.definitions).
     */
    public static function filamentResourceRoute(): ?string
    {
        $prefix = trim(config('saccharine-bpmn.prefix', 'bpmn/workflows'), '/');

        // Filament turns slashes in the slug into dots in the route name, so the
        // default slug "bpmn/workflows/definitions" becomes
        // "filament.{panel}.resources.bpmn.workflows.definitions.index".
        $slug = str_replace('/', '.', $prefix) . '.definitions';

        $patterns = [
            // Slug derived from the package's `prefix` config key (the stubs).
            '/^filament\..+\.resources\.' . preg_quote($slug, '/') . '\.index$/',
            // Fallback for hosts whose resource predates the getSlug() override,
            // where Filament derives the slug from the class namespace.
            '/^filament\..+\.resources\..*workflow-definitions\.index$/',
        ];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * The URL of the dashboard the user should return to.
     */
    public static function dashboardUrl(): string
    {
        $configured = config('saccharine-bpmn.dashboard_url');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $filamentRoute = static::filamentResourceRoute();

        if ($filamentRoute !== null) {
            return route($filamentRoute);
        }

        if (Route::has('bpmn.index')) {
            return route('bpmn.index');
        }

        // The standalone dashboard routes were disabled (saccharine-bpmn.dashboard =
        // false) and no Filament resource is registered. Fall back to the app root
        // rather than throwing a RouteNotFoundException inside a view.
        return '/';
    }
}
