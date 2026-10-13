<?php

namespace Saccharine\BpmnEngine\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Saccharine\BpmnEngine\Support\BpmnRouteHelper;
use Saccharine\BpmnEngine\Models\WorkflowDefinition;
use Saccharine\BpmnEngine\Services\BpmnParserService;
use Saccharine\BpmnEngine\Enums\WorkflowPermission;
use Exception;

class WorkflowController extends Controller
{
    protected BpmnParserService $parser;

    public function __construct(BpmnParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Display a list of all current workflow definitions.
     */
    public function index()
    {
        Gate::authorize(WorkflowPermission::VIEW->value);

        // When a Filament panel has the BPMN resources registered, forward to it so
        // the package always exposes one canonical entry point (bpmn.index) that
        // resolves to whichever UI the host has installed. Set
        // `bpmn-engine.filament_redirect` to false to pin this route to the
        // standalone Blade dashboard.
        if (config('bpmn-engine.filament_redirect', true)) {
            $filamentRoute = BpmnRouteHelper::filamentResourceRoute();

            if ($filamentRoute !== null) {
                return redirect()->route($filamentRoute);
            }
        }

        $definitions = WorkflowDefinition::with('versions')->get();
        return view('bpmn-engine::dashboard', compact('definitions'));
    }

    /**
     * Create a brand new workflow definition and redirect to its designer.
     */
    public function store(Request $request)
    {
        Gate::authorize(WorkflowPermission::EDIT->value); // Prevent unauthorized users from making changes

        $request->validate([
            'name' => 'required|string|max:255',
            'key'  => 'required|alpha_dash|unique:workflow_definitions,key',
        ]);

        $definition = WorkflowDefinition::create([
            'name' => $request->name,
            'key'  => $request->key,
        ]);

        return redirect()->route('bpmn.design', $definition->id);
    }

    public function storeVersion(Request $request, $definitionId): JsonResponse
    {
        Gate::authorize(WorkflowPermission::EDIT->value);

        $request->validate([
            'xml' => 'required|string',
        ]);

        $definition = WorkflowDefinition::findOrFail($definitionId);

        // Determine the next version number
        $nextVersionNumber = $definition->versions()->max('version') + 1;

        try {
            // The closure has its own scope, so the created version id has to be
            // returned out of it — referencing $version below from the outer scope
            // would throw "Undefined variable" on every save.
            $versionId = DB::transaction(function () use ($definition, $nextVersionNumber, $request) {
                if ($request->boolean('is_active')) {
                    $definition->versions()->update(['is_active' => false]);
                }
                
                // Save the raw layout
                $version = $definition->versions()->create([
                    'version'   => $nextVersionNumber,
                    'bpmn_xml'  => $request->input('xml'),
                    'is_active' => $request->boolean('is_active', false),
                ]);

                // Compile the XML into relational tables
                $this->parser->parseAndStore($version, $request->input('xml'));

                return $version->id;
            });

            return response()->json([
                'success'    => true,
                'message'    => 'Workflow version compiled successfully.',
                'version_id' => $versionId
            ], 201);

        } catch (Exception $e) {
            // A throw inside the transaction rolls it back, so the version row is
            // already gone — no manual cleanup needed.
            return response()->json([
                'success' => false,
                'message' => 'Failed to compile workflow: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the editor canvas.
     */
    public function design($definitionId)
    {
        Gate::authorize(WorkflowPermission::VIEW->value); // Or 'bpmn:edit' depending on how strict we want the canvas to be

        $definition = WorkflowDefinition::findOrFail($definitionId);
        
        // Grab the latest saved XML string
        $latestVersion = $definition->versions()->latest('version')->first();
        
        // If there are no saved versions, we pass a strictly validated, clean XML string
        $xml = $latestVersion ? $latestVersion->bpmn_xml : $this->getBlankBlueprintXml();

        // Scan package-internal built-in templates
        $packageTemplatesPath = __DIR__ . '/../../../resources/bpmn/templates';
        $elementTemplates = $this->loadTemplatesFromDirectory($packageTemplatesPath);

        // Scan and merge host application element templates
        $hostTemplatesPath = resource_path('bpmn/templates');

        if (File::isDirectory($hostTemplatesPath)) {
            $elementTemplates = array_merge($elementTemplates, $this->loadTemplatesFromDirectory($hostTemplatesPath));
        }

        return view('bpmn-engine::editor', [
            'definition'        => $definition,
            'xml'               => $xml,
            'elementTemplates'  => $elementTemplates,
            'currentVersion'    => $latestVersion,
        ]);
    }

    protected function loadTemplatesFromDirectory(string $path) : array {
        $templates = [];

        if (File::isDirectory($path)) {
            foreach (File::files($path) as $file) {
                if ($file->getExtension() === 'json') {
                    $content = json_decode(File::get($file), true);
                    if (is_array($content)) {
                        // Merge the parsed JSON arrays together
                        $templates = array_merge($templates, $content);
                    }
                }
            }
        }
        
        return $templates;
    }

    /**
     * A completely standard, validated, minimal BPMN 2.0 XML blueprint.
     */
    protected function getBlankBlueprintXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
               '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" ' . // Added 'www.'
               'xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" ' .
               'xmlns:dc="http://www.omg.org/spec/DD/20100524/DC" ' .
               'xmlns:di="http://www.omg.org/spec/DD/20100524/DI" ' . // Added 'di' namespace
               'id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">' .
               '<bpmn:process id="Process_1" isExecutable="true">' .
               '<bpmn:startEvent id="StartEvent_1" />' .
               '</bpmn:process>' .
               '<bpmndi:BPMNDiagram id="BPMNDiagram_1">' .
               '<bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">' .
               '<bpmndi:BPMNShape id="_BPMNShape_StartEvent_2" bpmnElement="StartEvent_1">' .
               '<dc:Bounds x="173" y="102" width="36" height="36" />' .
               '</bpmndi:BPMNShape>' .
               '</bpmndi:BPMNPlane>' .
               '</bpmndi:BPMNDiagram>' .
               '</bpmn:definitions>';
    }
}