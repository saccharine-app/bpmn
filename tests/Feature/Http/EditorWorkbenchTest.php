<?php

use Illuminate\Support\Facades\File;
use Saccharine\Bpmn\Models\WorkflowDefinition;

/*
|--------------------------------------------------------------------------
| Editor Workbench Wiring
|--------------------------------------------------------------------------
|
| Guards against the regression where the legacy `save-button` binding in
| resources/js/saccharine-bpmn.js threw a TypeError (the element was removed
| when the workbench header shipped). That exception aborted the editor's
| inline script BEFORE the `bpmn:save-requested` listener was registered,
| leaving the workbench buttons (Save Draft / Publish & Activate /
| Archive) silently dead while the canvas still rendered fine.
|
*/

const SACCHARINE_BPMN_WORKBENCH_PACKAGE_PATH = __DIR__ . '/../../..';

it('renders the designer page with the workbench action buttons', function () {
    $definition = WorkflowDefinition::create([
        'name' => 'Order Fulfilment',
        'key'  => 'order-fulfilment',
    ]);

    $response = $this->get(route('bpmn.design', $definition->id));

    $response->assertOk();

    $html = $response->getContent();
    expect($html)
        ->toContain('id="workbench-btn-save-draft"')
        ->toContain('id="workbench-btn-publish"')
        ->toContain('id="workbench-action-archive"');
});

it('registers the workbench event bridge before initializing the designer', function () {
    $definition = WorkflowDefinition::create([
        'name' => 'Order Fulfilment',
        'key'  => 'order-fulfilment',
    ]);

    $html = $this->get(route('bpmn.design', $definition->id))->getContent();

    // If initBpmnDesigner throws, every statement after it never runs — so the
    // bridge listeners must be wired first, not afterwards.
    $bridgePosition   = strpos($html, "window.addEventListener('bpmn:save-requested'");
    $archivePosition  = strpos($html, "window.addEventListener('bpmn:archive-requested'");
    $designerPosition = strpos($html, 'window.initBpmnDesigner(initialXml');

    expect($bridgePosition)->not->toBeFalse()
        ->and($archivePosition)->not->toBeFalse()
        ->and($designerPosition)->not->toBeFalse()
        ->and($bridgePosition)->toBeLessThan($designerPosition)
        ->and($archivePosition)->toBeLessThan($designerPosition);

    // ... and the designer init must be guarded so a missing or stale bundle
    // degrades gracefully instead of aborting the whole inline script.
    expect($html)->toContain("typeof window.initBpmnDesigner === 'function'");
});

it('exposes the modeler instance the editor bridge depends on', function () {
    $source = File::get(SACCHARINE_BPMN_WORKBENCH_PACKAGE_PATH . '/resources/js/saccharine-bpmn.js');

    // editor.blade.php reads window.__bpmnModelerInstance.saveXML(...)
    expect($source)->toContain('window.__bpmnModelerInstance = modeler;');

    // The element was removed when the workbench header shipped; the legacy
    // binding must be null-safe so it can never abort initBpmnDesigner again.
    expect($source)->not->toContain("getElementById('save-button').addEventListener");
});

it('ships a compiled bundle in sync with the designer source', function () {
    $bundle = File::get(SACCHARINE_BPMN_WORKBENCH_PACKAGE_PATH . '/public/saccharine-bpmn.js');

    // `npx vite build` must be re-run after editing resources/js — the host
    // app loads the compiled public/ bundle, never the raw source file.
    // (The minifier quotes strings with backticks in the bundle.)
    expect($bundle)->toContain('__bpmnModelerInstance')
        ->and($bundle)->not->toContain('getElementById(`save-button`).addEventListener');
});

it('renders an absolute version-save URL the browser cannot resolve against the designer path', function () {
    $definition = WorkflowDefinition::create([
        'name' => 'Order Fulfilment',
        'key'  => 'order-fulfilment',
    ]);

    $html = $this->get(route('bpmn.design', $definition->id))->getContent();

    // A relative `fetch('api/bpmn/...')` would resolve against
    // /bpmn/workflows/definitions/{id}/design and 404 with a combined path.
    expect($html)
        ->toContain(implode("", ["fetch(`", route('bpmn.definitions.versions.store', $definition->id)]))
        ->not->toContain("fetch(`bpmn/");
});
