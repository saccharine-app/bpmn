// --- Core CSS Imports ---
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import '@bpmn-io/properties-panel/dist/assets/properties-panel.css';
import '@bpmn-io/element-template-chooser/dist/element-template-chooser.css';
import 'bpmn-js-token-simulation/assets/css/bpmn-js-token-simulation.css';

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

// Expose a globally accessible initialization function
window.initBpmnDesigner = function(initialXml, elementTemplates, saveCallback) {
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
            TokenSimulationModule // Inject the simulator into the toolkit
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
        viewer: viewer,
        updateTokens: function(activeNodeIds, previousNodeIds = []) {
            const canvas = viewer.get('canvas');
            
            // Clean up old markers
            previousNodeIds.forEach(nodeId => {
                try { canvas.removeMarker(nodeId, 'bpmn-active-token'); } catch (e) {}
            });

            // Apply new markers
            activeNodeIds.forEach(nodeId => {
                try { canvas.addMarker(nodeId, 'bpmn-active-token'); } catch (e) {}
            });
        }
    };
};