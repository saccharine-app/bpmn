<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tracking Instance #{{ $instance->id }} - {{$instance->version->definition->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('vendor/bpmn-engine/bpmn-engine.css') }}">
</head>
<body class="bg-gray-100 flex flex-col h-screen overflow-hidden font-sans">

    <!-- Top Cockpit Header -->
    <header class="h-14 bg-slate-900 text-white px-6 flex items-center justify-between border-b border-slate-800 z-10">
        <div class="flex items-center gap-4">
            <a href="{{ \Saccharine\BpmnEngine\Support\BpmnRouteHelper::dashboardUrl() }}" class="text-slate-400 hover:text-white text-sm font-semibold">
                &larr; Back
            </a>
            <div class="h-4 w-px bg-slate-700"></div>
            <h1 class="text-sm font-bold tracking-wide">
                Instance #{{ $instance->id }} 
                <span class="text-slate-400 font-normal">({{ $instance->version->definition->name }} v{{$instance->version->version }})</span>
            </h1>
            <span id="cockpit-status-badge" class="px-2 py-0.5 rounded-full text-xs font-semibold uppercase bg-green-500/20 text-green-400">
                {{ $instance->status->value }}
            </span>
        </div>

        <!-- Live Operator Intervention Controls -->
        <div class="flex items-center gap-2">
            @can('bpmn:suspend-instance')
                <button id="btn-suspend" class="px-3 py-1 bg-amber-600 hover:bg-amber-500 text-xs font-semibold rounded">Suspend</button>
            @endcan
            @can('bpmn:resume-instance')
                <button id="btn-resume" class="px-3 py-1 bg-green-600 hover:bg-green-500 text-xs font-semibold rounded hidden">Resume</button>
            @endcan
            @can('bpmn:halt-instance')
                <button id="btn-halt" class="px-3 py-1 bg-rose-600 hover:bg-rose-500 text-xs font-semibold rounded">Halt</button>
            @endcan
        </div>
    </header>

    <!-- Cockpit Main (70% Diagram, 30% Context & Execution Audit) -->
    <div class="flex flex-grow h-[calc(100vh-56px)]">
        <!-- Diagram Viewport -->
        <div class="w-3/4 h-full relative bg-slate-50 border-r border-slate-200">
            <div id="tracking-canvas" class="w-full h-full"></div>
        </div>

        <!-- Telemetry & Variable Inspector -->
        <aside class="w-1/4 h-full bg-white flex flex-col overflow-y-auto border-l border-slate-200 shadow-sm p-4">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Audit Execution History</h2>
            <div id="history-timeline" class="space-y-2 mb-6 text-xs text-slate-600">
                <!-- Dynamically populated timeline nodes -->
            </div>

            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Live Context Payload</h2>
            <pre id="payload-inspector" class="bg-slate-900 text-slate-100 p-3 rounded text-[11px] overflow-x-auto font-mono flex-grow">
Loading payload...
            </pre>
        </aside>
    </div>

    <script src="{{ asset('vendor/bpmn-engine/bpmn-engine.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tracker = window.initBpmnViewer(@json($instance->version->bpmn_xml), '#tracking-canvas');
            const instanceId = {{ $instance->id }};
            const pollRate = 1200; // ms

            const poll = setInterval(async () => {
                try {
                    const res = await fetch(`/api/bpmn/instances/${instanceId}/telemetry`);
                    const data = await res.json();

                    tracker.syncState(
                        data.active_tokens, 
                        data.history_trail || [], 
                        ['halted', 'failed'].includes(data.status)
                    );

                    document.getElementById('cockpit-status-badge').textContent = data.status;

                    if (['completed', 'halted', 'failed'].includes(data.status)) {
                        clearInterval(poll);
                    }
                } catch (e) {
                    console.error('Telemetry polling failed', e);
                }
            }, pollRate);
        });
    </script>
</body>
</html>