<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tracking Instance #{{ $instance->id }} - {{$instance->version->definition->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('vendor/saccharine/bpmn/saccharine-bpmn.css') }}">
    @routes
</head>
<body class="bg-gray-100 flex flex-col h-screen overflow-hidden font-sans">

    <!-- Top Cockpit Header -->
    <header class="h-14 bg-slate-900 text-white px-6 flex items-center justify-between border-b border-slate-800 z-10">
        <div class="flex items-center gap-4">
            <a href="{{ \Saccharine\Bpmn\Support\BpmnRouteHelper::dashboardUrl() }}" class="text-slate-400 hover:text-white text-sm font-semibold">
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

    <!-- User Task Interaction Modal -->
    <div id="task-modal-backdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 transition-opacity duration-200">
        <div id="task-modal-container" class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full max-w-md overflow-hidden transform transition-all duration-200 scale-95 opacity-0">
            <!-- Header -->
            <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                    <h3 id="task-modal-title" class="font-bold text-sm tracking-wide">Human Task Approval</h3>
                </div>
                <button type="button" onclick="closeTaskModal()" class="text-slate-400 hover:text-white text-lg leading-none">&times;</button>
            </div>

            <!-- Body Form -->
            <form id="task-modal-form" class="p-6 space-y-4">
                <input type="hidden" id="task-modal-element-id" value="" />

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Decision</label>
                    <select id="task-input-decision" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="approved">Approve & Continue</option>
                        <option value="rejected">Reject</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Approver Name</label>
                    <input type="text" id="task-input-approver" value="Manager / Operator" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" required />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Review Notes</label>
                    <textarea id="task-input-notes" rows="3" placeholder="Optional comments to pass into workflow payload..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                </div>

                <!-- Context Hint -->
                <p class="text-[11px] text-slate-500 italic">
                    Submitting this form injects the payload into the workflow's inbox and signals the queue worker to advance past this step.
                </p>

                <!-- Actions -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeTaskModal()" class="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="task-submit-btn" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                        <span>Submit & Resume</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="{{ asset('vendor/saccharine/bpmn/saccharine-bpmn.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tracker = window.initBpmnViewer(@json($instance->version->bpmn_xml), '#tracking-canvas');
            const instanceId = {{ $instance->id }};
            const pollRate = 1200; // ms

            const poll = setInterval(async () => {
                try {
                    const res = await fetch(route('bpmn.instances.telemetry', instanceId));
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

        // Global references
        const modalBackdrop = document.getElementById('task-modal-backdrop');
        const modalContainer = document.getElementById('task-modal-container');
        const modalTitle = document.getElementById('task-modal-title');
        const modalElementId = document.getElementById('task-modal-element-id');
        const modalForm = document.getElementById('task-modal-form');
        const submitBtn = document.getElementById('task-submit-btn');

        /**
         * Opens the approval modal for a paused User Task.
         */
        function openTaskModal(elementId, taskLabel) {
            modalElementId.value = elementId;
            modalTitle.textContent = taskLabel || `User Task: ${elementId}`;

            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = `<span>Submit & Resume</span><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>`;

            // Show backdrop
            modalBackdrop.classList.remove('hidden');

            // Smooth enter animation
            setTimeout(() => {
                modalContainer.classList.remove('scale-95', 'opacity-0');
                modalContainer.classList.add('scale-100', 'opacity-100');
            }, 20);
        }

        /**
         * Closes and resets the modal.
         */
        function closeTaskModal() {
            modalContainer.classList.remove('scale-100', 'opacity-100');
            modalContainer.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modalBackdrop.classList.add('hidden');
            }, 150);
        }

        // Close modal on backdrop click or Escape key
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) closeTaskModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modalBackdrop.classList.contains('hidden')) {
                closeTaskModal();
            }
        });

        // Handle Form Submission
        modalForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const instanceId = {{ $instance->id }};
            const elementId = modalElementId.value;
            const payload = {
                decision: document.getElementById('task-input-decision').value,
                approved_by: document.getElementById('task-input-approver').value.trim(),
                comments: document.getElementById('task-input-notes').value.trim(),
                submitted_at: new Date().toISOString(),
            };

            // UI Feedback: Loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<span>Resuming...</span>`;

            try {
                
                const response = await fetch(route('bpmn.instances.task.submit', [
                    instanceId, elementId
                ]), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ payload: payload })
                });

                const result = await response.json();

                if (response.ok) {
                    closeTaskModal();
                    // Optional: trigger immediate re-poll so the UI reflects the resume instantly
                    if (typeof triggerPoll === 'function') {
                        triggerPoll();
                    }
                } else {
                    alert('Error submitting task: ' + (result.message || 'Unknown error'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Retry Submission';
                }
            } catch (err) {
                console.error('Submission request failed', err);
                alert('Network error while submitting task.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Retry Submission';
            }
        });
    </script>
</body>
</html>