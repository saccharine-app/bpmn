<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPMN - Instance Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans min-h-screen">
    <div class="max-w-7xl mx-auto py-10 px-4">
        
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between border-b pb-6">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Active Instances</h1>
                <p class="text-sm text-gray-500 mt-1">Monitor tokens and control live workflow executions.</p>
            </div>
            <a href="{{ \Saccharine\Bpmn\Support\BpmnRouteHelper::dashboardUrl() }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                &larr; Back to Designer
            </a>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-md text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-md text-sm font-medium">
                {{ session('error') }}
            </div>
        @endif

        <!-- Instances Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50 text-gray-700 uppercase text-xs font-semibold border-b">
                    <tr>
                        <th class="py-3 px-4">ID</th>
                        <th class="py-3 px-4">Process</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Active Tokens (Node IDs)</th>
                        <th class="py-3 px-4">Started</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($instances as $instance)
                        <tr class="hover:bg-gray-50">
                            <td class="py-4 px-4 font-mono text-xs text-gray-500">#{{ $instance->id }}</td>
                            <td class="py-4 px-4">
                                <div class="font-bold text-gray-900">
                                    {{ $instance->version->definition->name ?? 'Unknown' }}
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    v{{ $instance->version->version ?? '?' }} | {{ $instance->durable_workflow_id }}
                                </div>
                            </td>
                            <td class="py-4 px-4">
                                @php
                                    $color = match($instance->status->value) {
                                        'running' => 'bg-green-100 text-green-800',
                                        'suspended' => 'bg-yellow-100 text-yellow-800',
                                        'halted', 'failed' => 'bg-red-100 text-red-800',
                                        'completed' => 'bg-blue-100 text-blue-800',
                                        default => 'bg-gray-100 text-gray-800',
                                    };
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase {{ $color }}">
                                    {{ $instance->status->value }}
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                @if($instance->tokens->isEmpty())
                                    <span class="text-gray-400 italic text-xs">No active tokens</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($instance->tokens as $token)
                                            <span class="px-2 py-1 bg-gray-100 border border-gray-200 text-gray-700 font-mono text-[10px] rounded">
                                                {{ $token->bpmn_element_id }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-xs">
                                {{ $instance->created_at->diffForHumans() }}
                            </td>
                            <td class="py-4 px-4 text-right space-x-2">
                                <!-- Suspend/Resume Buttons -->
                                @if($instance->status->value === 'running')
                                    @can('bpmn:suspend-instance')
                                    <form action="{{ route('bpmn.instances.suspend', $instance->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="text-yellow-600 hover:text-yellow-900 font-semibold text-xs">Suspend</button>
                                    </form>
                                    @endcan
                                @elseif($instance->status->value === 'suspended')
                                    @can('bpmn:resume-instance')
                                    <form action="{{ route('bpmn.instances.resume', $instance->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-900 font-semibold text-xs">Resume</button>
                                    </form>
                                    @endcan
                                @endif

                                <!-- Halt Button -->
                                @if(in_array($instance->status->value, ['running', 'suspended']))
                                    @can('bpmn:halt-instance')
                                    <form action="{{ route('bpmn.instances.halt', $instance->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to permanently halt this workflow?');">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:text-red-900 font-semibold text-xs ml-2">Halt</button>
                                    </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-gray-500 text-sm">
                                No workflow instances found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-4">
            {{ $instances->links() }}
        </div>
    </div>
</body>
</html>