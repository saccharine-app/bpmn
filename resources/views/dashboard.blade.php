<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saccharine BPMN Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans min-h-screen">

    <div class="max-w-6xl mx-auto py-10 px-4">
        <!-- Header -->
        <div class="mb-10 flex items-center justify-between border-b pb-6">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">BPMN Workflow Orchestrator</h1>
                <p class="text-sm text-gray-500 mt-1">Manage, model, and deploy durable background workflows inside Laravel.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Left Panel: Form to Create Workflows -->
             @can(\Saccharine\Bpmn\Enums\WorkflowPermission::EDIT->value)
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 h-fit">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Create New Workflow</h2>
                
                <form action="{{ route('bpmn.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Friendly Name</label>
                        <input type="text" name="name" placeholder="e.g., Send Invoice" required
                               class="w-full px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Unique Key (Identifier)</label>
                        <input type="text" name="key" placeholder="e.g., send-invoice" required
                               class="w-full px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>

                    <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-md transition duration-150">
                        Create & Design
                    </button>
                </form>
            </div>
            @else
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 h-fit text-center">
                <p class="text-sm text-gray-500">You do not have permission to create new workflows.</p>
            </div>
            @endcan

            <!-- Right Panel: List of Current Workflows -->
            <div class="md:col-span-2 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Active Workflow Definitions</h2>

                @if($definitions->isEmpty())
                    <div class="text-center py-12 border-2 border-dashed border-gray-200 rounded-md">
                        <p class="text-gray-400 text-sm">No workflow definitions found.</p>
                        <p class="text-xs text-gray-400 mt-1">Use the panel on the left to create your first executable workflow blueprint.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                                <tr>
                                    <th class="py-3 px-4">Name</th>
                                    <th class="py-3 px-4">Key</th>
                                    <th class="py-3 px-4">Versions</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($definitions as $def)
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-4 px-4 font-semibold text-gray-900">{{ $def->name }}</td>
                                        <td class="py-4 px-4"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-indigo-600">{{ $def->key }}</code></td>
                                        <td class="py-4 px-4">
                                            <span class="px-2 py-0.5 bg-gray-100 text-gray-800 text-xs font-semibold rounded">
                                                {{ $def->versions->count() }} v
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-right">
                                            @can(\Saccharine\Bpmn\Enums\WorkflowPermission::EDIT->value)
                                            <a href="{{ route('bpmn.design', $def->id) }}" class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-900">
                                                Design & Model
                                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                            </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

</body>
</html>