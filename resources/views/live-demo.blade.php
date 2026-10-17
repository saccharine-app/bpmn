<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPMN Live Execution Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Load unified local CSS bundle -->
    <link rel="stylesheet" href="{{ asset('vendor/saccharine/bpmn/saccharine-bpmn.css') }}">
    
    <style>
        .bpmn-active-token:not(.djs-connection) .djs-visual > :nth-child(1) {
            stroke: #22c55e !important; /* Vivid Green */
            stroke-width: 4px !important;
            fill: #dcfce7 !important;
        }
        .bpmn-active-token {
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); opacity: 0.9; }
        }
    </style>
</head>
<body class="bg-gray-100 overflow-hidden flex h-screen">

    <!-- LEFT SIDE: The Engine (70%) -->
    <div class="w-2/3 h-full flex flex-col border-r border-gray-300 bg-white">
        <div class="p-4 bg-slate-900 text-white flex justify-between items-center shadow-md z-10">
            <h2 class="font-bold text-lg tracking-wide">BPMN (Backend Perspective)</h2>
            <span class="text-xs bg-slate-700 px-3 py-1 rounded-full text-slate-300">Live Polling: 1000ms</span>
        </div>
        <div id="canvas" class="flex-grow bg-slate-50 relative"></div>
    </div>

    <!-- RIGHT SIDE: The Mock App (30%) -->
    <div class="w-1/3 h-full bg-white flex flex-col shadow-xl z-20">
        <div class="p-4 bg-indigo-600 text-white flex justify-between items-center shadow-md">
            <h2 class="font-bold text-lg tracking-wide">E-Commerce Dashboard</h2>
            <span class="text-xs bg-indigo-500 px-3 py-1 rounded-full text-indigo-100">User Interface</span>
        </div>
        
        <div class="p-8 flex-grow flex flex-col">
            <!-- Order Details -->
            <div class="mb-8 p-6 bg-gray-50 border border-gray-200 rounded-xl shadow-inner">
                <h3 class="text-xs font-bold text-gray-400 uppercase mb-4 tracking-wider">Active Order Context</h3>
                <p class="text-sm text-gray-600 mb-2">Order ID: <span class="font-mono text-gray-900 font-bold">#ORD-{{ rand(1000, 9999) }}</span></p>
                <p class="text-sm text-gray-600 mb-2">Customer: <span class="font-bold text-gray-900">Family Demo User</span></p>
                <p class="text-sm text-gray-600 mb-4">Total Amount: <span class="font-bold text-green-600">$1,500.00</span></p>
                
                <div class="pt-4 border-t border-gray-200">
                    <p class="text-xs font-bold text-gray-500 uppercase">System Status:</p>
                    <p id="system-status" class="text-indigo-600 font-bold mt-1 animate-pulse">Initializing Order...</p>
                </div>
            </div>

            <!-- Hidden Approval Form -->
            <div id="approval-widget" class="hidden transform transition-all duration-500 scale-95 opacity-0">
                <div class="p-6 bg-yellow-50 border-2 border-yellow-400 rounded-xl shadow-lg">
                    <div class="flex items-center space-x-3 mb-3">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <h3 class="font-bold text-yellow-800 text-lg">Manager Review Required</h3>
                    </div>
                    <p class="text-sm text-yellow-700 mb-5">This order exceeds $1,000 and requires manual authorization to proceed to invoicing.</p>
                    
                    <button id="approve-btn" class="w-full py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-bold rounded-lg shadow-md transition-colors text-sm uppercase tracking-wide">
                        Approve Order &rarr;
                    </button>
                </div>
            </div>

            <!-- Hidden Success State -->
            <div id="success-widget" class="hidden mt-auto">
                <div class="p-6 bg-green-50 border border-green-200 rounded-xl text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3 class="font-bold text-green-900 text-lg">Process Complete</h3>
                    <p class="text-sm text-green-700 mt-2">The workflow engine has automatically generated the invoice and terminated the process.</p>
                </div>
            </div>

        </div>
    </div>

    <script src="{{ asset('vendor/saccharine/bpmn/bpmn-modeler.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            // 1. Boot the Navigated Viewer
            const tracker = window.initBpmnViewer(@json($xml), '#canvas');
            let previousTokens = [];
            
            const instanceId = {{ $instance->id }};
            const statusText = document.getElementById('system-status');
            const approvalWidget = document.getElementById('approval-widget');
            const successWidget = document.getElementById('success-widget');

            // 2. Poll the API for token locations
            const pollInterval = setInterval(async () => {
                try {
                    const response = await fetch(`/api/bpmn/instances/${instanceId}/tokens`);
                    const data = await response.json();
                    
                    tracker.updateTokens(data.active_node_ids, previousTokens);
                    previousTokens = data.active_node_ids;

                    // 3. React to the engine state
                    if (data.status === 'completed') {
                        clearInterval(pollInterval);
                        statusText.innerText = "Order Complete.";
                        statusText.classList.remove('animate-pulse', 'text-indigo-600');
                        statusText.classList.add('text-green-600');
                        approvalWidget.classList.add('hidden');
                        successWidget.classList.remove('hidden');
                    } 
                    else if (data.active_node_ids.includes('Task_ManualReview')) {
                        statusText.innerText = "Stalled: Awaiting Human Input...";
                        statusText.classList.replace('text-indigo-600', 'text-yellow-600');
                        
                        // Reveal the widget smoothly
                        approvalWidget.classList.remove('hidden');
                        setTimeout(() => {
                            approvalWidget.classList.remove('scale-95', 'opacity-0');
                            approvalWidget.classList.add('scale-100', 'opacity-100');
                        }, 50);
                    }
                    else if (data.active_node_ids.length > 0) {
                        statusText.innerText = "Engine processing tasks...";
                        statusText.classList.replace('text-yellow-600', 'text-indigo-600');
                        approvalWidget.classList.add('hidden');
                    }

                } catch (e) {
                    console.error("Polling error", e);
                }
            }, 1000); // Check every second for dramatic effect

            // 4. Handle Human Input Action
            document.getElementById('approve-btn').addEventListener('click', async (e) => {
                const btn = e.target;
                btn.innerText = "Approving...";
                btn.disabled = true;
                btn.classList.replace('bg-yellow-500', 'bg-gray-400');

                await fetch(`/live-demo/${instanceId}/approve`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                
                // The widget will hide automatically on the next poll when the token moves!
            });
        });
    </script>
</body>
</html>