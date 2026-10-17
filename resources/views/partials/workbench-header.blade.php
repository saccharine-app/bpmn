<header id="bpmn-workbench-header" class="bpmn-workbench">
    <div class="workbench-left">
        <a href="{{ \Saccharine\Bpmn\Support\BpmnRouteHelper::dashboardUrl() }}" class="workbench-back-link" title="Back to Workflow Definitions">
            <svg class="icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
            </svg>
        </a>

        <div class="workbench-meta">
            <div class="workbench-title-group">
                <input 
                    type="text" 
                    id="workbench-definition-name" 
                    value="{{ $definition->name }}" 
                    class="workbench-input-name"
                    title="Click to edit workflow name"
                />
            </div>
            
            <div class="workbench-status-bar">
                @if($currentVersion)
                    <span class="version-tag">v{{ $currentVersion->version }}</span>
                    @if($currentVersion->is_active)
                        <span class="status-pill status-active">Active</span>
                    @else
                        <span class="status-pill status-draft">Draft</span>
                    @endif
                @else
                    <span class="version-tag">v1 (Initial)</span>
                    <span class="status-pill status-draft">Unsaved Draft</span>
                @endif

                <span class="workbench-key-badge" title="Unique machine identifier">
                    {{ $definition->key }}
                </span>
            </div>
        </div>
    </div>

    <div class="workbench-right">
        <!-- Save Draft Button -->
        <button id="workbench-btn-save-draft" class="btn btn-secondary">
            Save Draft
        </button>

        <!-- Publish / Promote Version Button -->
        <button id="workbench-btn-publish" class="btn btn-primary">
            Publish & Activate
        </button>

        <!-- Context Overflow (Archive / Delete) -->
        <div class="workbench-dropdown">
            <button id="workbench-btn-menu" class="btn btn-icon" title="More Options">
                <svg class="icon" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                </svg>
            </button>
            <div id="workbench-menu-dropdown" class="dropdown-menu hidden">
                <button type="button" id="workbench-action-archive" class="dropdown-item text-danger">
                    Archive Workflow
                </button>
            </div>
        </div>
    </div>
</header>

<style>
    /* Scoped Workbench CSS to prevent canvas collisions */
    .bpmn-workbench {
        position: relative;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 60px;
        padding: 0 1.25rem;
        background-color: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        font-family: ui-sans-serif, system-ui, sans-serif;
    }
    .workbench-left, .workbench-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .workbench-back-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 6px;
        color: #64748b;
        text-decoration: none;
        border: 1px solid #e2e8f0;
        transition: background-color 0.15s, color 0.15s;
    }
    .workbench-back-link:hover {
        background-color: #f1f5f9;
        color: #0f172a;
    }
    .workbench-meta {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .workbench-title-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .workbench-input-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
        border: 1px solid transparent;
        field-sizing: content;
        max-width: 500px;
        border-radius: 4px;
        padding: 2px 6px;
        outline: none;
        transition: border-color 0.15s, background-color 0.15s;
    }
    .workbench-input-name:hover {
        background-color: #f8fafc;
        border-color: #cbd5e1;
    }
    .workbench-input-name:focus {
        background-color: #ffffff;
        border-color: #6366f1;
        box-shadow: 0 0 0 1px #6366f1;
    }
    .workbench-key-badge {
        font-family: ui-monospace, SFMono-Regular, monospace;
        font-size: 0.75rem;
        background-color: #f1f5f9;
        color: #475569;
        padding: 2px 6px;
        border-radius: 4px;
    }
    .workbench-status-bar {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
    }
    .version-tag {
        color: #64748b;
        font-weight: 600;
    }
    .status-pill {
        padding: 1px 6px;
        border-radius: 9999px;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.65rem;
    }
    .status-active { background-color: #dcfce7; color: #15803d; }
    .status-draft { background-color: #fef3c7; color: #b45309; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.825rem;
        font-weight: 600;
        padding: 0.45rem 0.85rem;
        border-radius: 6px;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all 0.15s;
    }
    .btn-secondary { background-color: #ffffff; border-color: #cbd5e1; color: #334155; }
    .btn-secondary:hover { background-color: #f8fafc; border-color: #94a3b8; }
    .btn-primary { background-color: #4f46e5; color: #ffffff; }
    .btn-primary:hover { background-color: #4338ca; }
    .btn-icon { width: 34px; height: 34px; padding: 0; background-color: transparent; color: #64748b; }
    .btn-icon:hover { background-color: #f1f5f9; color: #0f172a; }
    .icon { width: 1.15rem; height: 1.15rem; }
    
    .workbench-dropdown { position: relative; }
    .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 0.25rem;
        width: 160px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        padding: 0.25rem 0;
    }
    .dropdown-item {
        width: 100%;
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
        background: none;
        border: none;
        text-align: left;
        cursor: pointer;
    }
    .dropdown-item:hover { background-color: #f8fafc; }
    .text-danger { color: #dc2626; }
    .hidden { display: none; }
</style>

<script>
    (function() {
        // Dropdown toggle
        const menuBtn = document.getElementById('workbench-btn-menu');
        const menuDropdown = document.getElementById('workbench-menu-dropdown');
        if (menuBtn && menuDropdown) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                menuDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => menuDropdown.classList.add('hidden'));
        }

        // Live Title Auto-Persist (on blur)
        const nameInput = document.getElementById('workbench-definition-name');
        if (nameInput) {
            nameInput.addEventListener('blur', async () => {
                const newName = nameInput.value.trim();
                if (!newName) return;

                try {
                    await fetch('{{ route('bpmn.definitions.update', $definition->id) }}', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ name: newName })
                    });
                } catch (e) {
                    console.error('Failed to auto-save workflow name', e);
                }
            });
        }

        // Dispatch save events with activation intent
        document.getElementById('workbench-btn-save-draft')?.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('bpmn:save-requested', { detail: { activate: false } }));
        });

        document.getElementById('workbench-btn-publish')?.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('bpmn:save-requested', { detail: { activate: true } }));
        });

        document.getElementById('workbench-action-archive')?.addEventListener('click', () => {
            if (confirm('Are you sure you want to archive this workflow definition? Running instances will complete, but no new executions will start.')) {
                window.dispatchEvent(new CustomEvent('bpmn:archive-requested'));
            }
        });
    })();
</script>