// --- Core CSS Imports ---
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import '@bpmn-io/properties-panel/dist/assets/properties-panel.css';
import '@bpmn-io/element-template-chooser/dist/element-template-chooser.css';
import 'bpmn-js-token-simulation/assets/css/bpmn-js-token-simulation.css';
import '../css/saccharine-bpmn.css';

// --- Module Imports ---
import BpmnModeler from 'bpmn-js/lib/Modeler';
import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import ElementTemplateChooserModule from '@bpmn-io/element-template-chooser';

import {
    BpmnPropertiesPanelModule,
    BpmnPropertiesProviderModule,
    CamundaPlatformPropertiesProviderModule
} from 'bpmn-js-properties-panel';
import { ElementTemplatesPropertiesProviderModule } from 'bpmn-js-element-templates';
import camundaModdleDescriptor from 'camunda-bpmn-moddle/resources/camunda.json';

import TokenSimulationModule from 'bpmn-js-token-simulation';

// Custom palette & context-pad providers (restricted, modular palette)
import { CustomPaletteModule } from './CustomPaletteProvider.js';
import { setPaletteConfig } from './CustomPaletteProvider.js';
import CustomContextPadProvider from './CustomContextPadProvider.js';

// Custom replace-menu (gear) provider (modular, independent config)
import { setReplaceMenuConfig, defaultReplaceMenuConfig } from './CustomReplaceMenuProvider.js';
import CustomReplaceMenuProvider from './CustomReplaceMenuProvider.js';

// Expose a globally accessible initialization function
window.initBpmnDesigner = function(initialXml, elementTemplates, saveCallback, paletteConfig, replaceMenuConfig) {
    // Apply optional per-node palette overrides before the modeler boots so the
    // custom providers read them on their first getPaletteEntries() call.
    if (paletteConfig) {
        setPaletteConfig(paletteConfig);
    }

    // Apply optional replace-menu (gear) overrides independently of palette config.
    // If no override was passed, fall back to the engine's default replace-menu
    // configuration (replace-with-<node> actions the BPMN engine can execute).
    setReplaceMenuConfig(
        replaceMenuConfig || defaultReplaceMenuConfig
    );

    const modeler = new BpmnModeler({
        container: '#canvas',
        propertiesPanel: {
            parent: '#properties-panel'
        },
        additionalModules: [
            BpmnPropertiesPanelModule,
            BpmnPropertiesProviderModule,
            CamundaPlatformPropertiesProviderModule,
            ElementTemplatesPropertiesProviderModule,
            ElementTemplateChooserModule,
            TokenSimulationModule, // Inject the simulator into the toolkit
            CustomPaletteModule   // Restricted, modular palette (replaces defaults)
        ],
        moddleExtensions: {
            camunda: camundaModdleDescriptor
        },
        // Provide the templates data to the modeler
        elementTemplates: elementTemplates
    });

    // Expose the modeler so host pages can extract XML (e.g. the editor's
    // `bpmn:save-requested` bridge reads window.__bpmnModelerInstance).
    window.__bpmnModelerInstance = modeler;

    // Suppress the default bpmn-js PaletteProvider so CustomPaletteProvider is
    // the sole source of palette entries. bpmn-js merges entries from all
    // registered providers; without this step the default (full) palette would
    // be layered on top of our restricted one.
    try {
        const palette = modeler.get('palette');
        const providers = palette._providers;
        if (Array.isArray(providers)) {
            palette._providers = providers.filter(
                provider => provider.constructor.name !== 'PaletteProvider'
            );
        }
    } catch (err) {
        console.warn('Could not suppress default bpmn-js palette provider:', err);
    }

    // Manually register custom context-pad and replace-menu providers AFTER
    // the default bpmn-js providers have been instantiated by the modeler.
    // This ensures default providers populate their entries first (e.g. the
    // 'replace' context-pad entry that renders the wrench icon, and the
    // replace-with-* entries in the gear popup), and our providers can filter
    // without removing them. DI '['type', ...]' registration would overwrite
    // the defaults instead of composing with them.
    try {
        const contextPad = modeler.get('contextPad');
        const popupMenu = modeler.get('popupMenu');

        new CustomContextPadProvider(
            contextPad,
            modeler.get('modeling'),
            modeler.get('autoPlace'),
            modeler.get('connect'),
            modeler.get('create'),
            modeler.get('elementFactory'),
            modeler.get('translate'),
            modeler.get('appendPreview')
        );

        new CustomReplaceMenuProvider(popupMenu);
    } catch (err) {
        console.warn('Could not register custom context-pad / replace-menu providers:', err);
    }

    function showTemplateErrors(errors) {
        console.error('Failed to parse element templates', errors);

        const errorMessage = `Failed to parse element templates:

            ${ errors.map(error => error.message).join('\n    ') }

        Check the developer tools for details.`;

        document.querySelector('.error-panel pre').textContent = errorMessage;
        document.querySelector('.error-panel').classList.toggle('hidden');
    }

    // Load element templates
    modeler.on('elementTemplates.errors', event => {
        const { errors } = event;

        showTemplateErrors(errors);
    });

    modeler.get('elementTemplatesLoader').setTemplates(elementTemplates);

    async function render() {
        try {
            await modeler.importXML(initialXml);
            modeler.get('canvas').zoom('fit-viewport');
        } catch (err) {
            console.error('Error rendering diagram:', err);
            alert('An error occurred while displaying the BPMN canvas.');
        }
    }

    render();

    // Attach the save event to the legacy standalone button (optional: the
    // workbench header dispatches `bpmn:save-requested` custom events instead).
    document.getElementById('save-button')?.addEventListener('click', async () => {
        try {
            const { xml } = await modeler.saveXML({ format: true });
            saveCallback(xml);
        } catch (err) {
            console.error('Error saving diagram:', err);
            // alert('An error occurred while exporting the XML.');
        }
    });
};

// Live viewer
window.initBpmnViewer = function(initialXml, containerSelector) {
    const viewer = new BpmnNavigatedViewer({
        container: containerSelector
    });
    
    let currentActive = [];
    let currentHistory = [];
    const eventBus = viewer.get('eventBus');

    eventBus.on('element.click', function(event) {
        const element = event.element;
        
        // Only intercept if this node is currently an active token
        if (!currentActive.includes(element.id)) {
            return;
        }

        // Check if it's a User Task
        if (element.type === 'bpmn:UserTask') {
            openTaskModal(element.id, element.businessObject.name || 'User Task Approval');
        }
    });
    
    async function completeTask(elementId, payload) {
        const res = await fetch(`/api/bpmn/instances/${instanceId}/tasks/${elementId}/submit`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ payload: payload })
        });

        const data = await res.json();
        if (res.ok) {
            closeTaskModal();
            // The polling loop will naturally clear the green marker on the next beat!
        } else {
            alert(data.message);
        }
    }

    async function render() {
        try {
            await viewer.importXML(initialXml);
            viewer.get('canvas').zoom('fit-viewport');
        } catch (err) {
            console.error('Error rendering tracking diagram:', err);
        }
    }
    render();

    // Return a control object so the host app can easily update markers
    return {
        viewer,
        syncState: function(activeNodeIds = [], historyNodeIds = [], isFailed = false) {
            const canvas = viewer.get('canvas');

            // Remove obsolete active markers
            currentActive.forEach(id => {
                if (!activeNodeIds.includes(id)) {
                    canvas.removeMarker(id, 'bpmn-active-token');
                    canvas.removeMarker(id, 'bpmn-failed-token');
                }
            });

            // Mark completed trail nodes
            historyNodeIds.forEach(id => {
                if (!activeNodeIds.includes(id)) {
                    canvas.addMarker(id, 'bpmn-history-completed');
                }
            });

            // Mark active / pulsing tokens
            activeNodeIds.forEach(id => {
                canvas.removeMarker(id, 'bpmn-history-completed');
                canvas.addMarker(id, isFailed ? 'bpmn-failed-token' : 'bpmn-active-token');
            });

            currentActive = activeNodeIds;
            currentHistory = historyNodeIds;
        }
    };
};