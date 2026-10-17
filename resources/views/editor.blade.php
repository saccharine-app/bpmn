<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $definition->name }} - BPMN Workflow Designer</title>
    
    <link rel="stylesheet" href="{{ asset('vendor/saccharine/bpmn/saccharine-bpmn.css') }}">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background-color: #f8fafc;
        }
        #designer-viewport {
            display: flex;
            flex-grow: 1;
            height: calc(100vh - 60px); /* Exactly fills remaining height below workbench */
            position: relative;
        }
        #canvas {
            flex-grow: 1;
            height: 100%;
            background-color: #f8fafc;
        }
        #properties-panel {
            width: 350px;
            height: 100%;
            background: #ffffff;
            border-left: 1px solid #e5e7eb;
            overflow-y: auto;
        }
    </style>
</head>
<body>

    <!-- INJECT THE WORKBENCH HEADER -->
    @include('saccharine-bpmn::partials.workbench-header', [
        'definition'     => $definition,
        'currentVersion' => $currentVersion
    ])

    <!-- DIAGRAM VIEWPORT (Canvas + Modeler Properties) -->
    <div id="designer-viewport">
        <div id="canvas"></div>
        <div id="properties-panel"></div>
    </div>

    <!-- BUNDLED JS & INITIALIZATION -->
    <script src="{{ asset('vendor/saccharine/bpmn/saccharine-bpmn.js') }}"></script>
    <script>
        // Use a safe JSON-decoding assignment to prevent raw quotes or newlines from breaking JS syntax
        const dbXml = {!! $xml ? json_encode($xml) : 'null' !!};
        const elementTemplates = @json($elementTemplates);
        const defaultBlankXml = '<' + '?xml version="1.0" encoding="UTF-8"?>\n' +
        `<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                           xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                           xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                           xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                           id="Definitions_1"
                           targetNamespace="http://bpmn.io/schema/bpmn">
          <bpmn:process id="Process_1" isExecutable="true">
            <bpmn:startEvent id="StartEvent_1" />
          </bpmn:process>
          <bpmndi:BPMNDiagram id="BPMNDiagram_1">
            <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
              <bpmndi:BPMNShape id="_BPMNShape_StartEvent_2" bpmnElement="StartEvent_1">
                <dc:Bounds x="173" y="102" width="36" height="36" />
              </bpmndi:BPMNShape>
            </bpmndi:BPMNPlane>
          </bpmndi:BPMNDiagram>
        </bpmn:definitions>`;

        const initialXml = dbXml ? dbXml : defaultBlankXml;

        // Bridge Workbench Events to the Backend API.
        // NOTE: these listeners are registered BEFORE initBpmnDesigner runs so a
        // failure inside the designer bundle can never leave the workbench
        // buttons (Save Draft / Publish & Activate / Archive) unwired.
        window.addEventListener('bpmn:save-requested', async (event) => {
            const shouldActivate = event.detail.activate;
            
            try {
                // modeler instance can be accessed from window/wrapper
                const { xml } = await window.__bpmnModelerInstance.saveXML({ format: true });
                
                // url() keeps this absolute (a bare relative path would resolve
                // against the designer's own URL and 404).
                const response = await fetch('{{ url(rtrim(config('saccharine-bpmn.api_prefix', 'api/bpmn'), '/')) }}/workflows/{{ $definition->id }}/versions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ 
                        xml: xml,
                        is_active: shouldActivate 
                    })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(shouldActivate ? 'Workflow version published and activated!' : 'Draft version saved.');
                    window.location.reload();
                } else {
                    alert('Compilation failed: ' + result.message);
                }
            } catch (err) {
                console.error('Error saving diagram:', err);
                alert('An error occurred while compiling the XML.');
            }
        });

        window.addEventListener('bpmn:archive-requested', async () => {
            const response = await fetch('{{ route('bpmn.definitions.destroy', $definition->id) }}', {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            if (response.ok) {
                window.location.href = '{{ \Saccharine\Bpmn\Support\BpmnRouteHelper::dashboardUrl() }}';
            }
        });

        // Initialize modeler via bundled entry point (guarded so a missing or
        // stale bundle degrades gracefully instead of aborting this script)
        if (typeof window.initBpmnDesigner === 'function') {
            window.initBpmnDesigner(initialXml, elementTemplates, (xml) => {
                // Handled via custom events above
            });
        } else {
            console.error('saccharine-bpmn bundle failed to load: window.initBpmnDesigner is unavailable.');
        }
    </script>
</body>
</html>