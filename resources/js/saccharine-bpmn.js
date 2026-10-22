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

// --- Engine-Driven Modules ---
import { setSupportedElements } from './engineCapabilities.js';
import { FilteredPaletteModule } from './FilteredPaletteProvider.js';
import { FilteredContextPadModule } from './FilteredContextPadProvider.js';
import { FilteredReplaceMenuModule } from './FilteredReplaceMenuProvider.js';

window.initBpmnDesigner = function(initialXml, elementTemplates, saveCallback, supportedElementsOverride) {
    // Configure supported engine capabilities once; all UI providers consume this
    if (supportedElementsOverride) {
        setSupportedElements(supportedElementsOverride);
    }

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
            TokenSimulationModule,
            // Filter modules register as non-destructive middleware
            FilteredPaletteModule,
            FilteredContextPadModule,
            FilteredReplaceMenuModule
        ],
        moddleExtensions: {
            camunda: camundaModdleDescriptor
        },
        elementTemplates: elementTemplates
    });

    window.__bpmnModelerInstance = modeler;

    function showTemplateErrors(errors) {
        console.error('Failed to parse element templates', errors);
        const errorMessage = `Failed to parse element templates:\n\n${errors.map(e => e.message).join('\n    ')}\n\nCheck developer tools for details.`;
        const pre = document.querySelector('.error-panel pre');
        if (pre) pre.textContent = errorMessage;
        document.querySelector('.error-panel')?.classList.toggle('hidden');
    }

    modeler.on('elementTemplates.errors', event => {
        showTemplateErrors(event.errors);
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

    document.getElementById('save-button')?.addEventListener('click', async () => {
        try {
            const { xml } = await modeler.saveXML({ format: true });
            if (saveCallback) {
                saveCallback(xml);
            }
        } catch (err) {
            console.error('Error saving diagram:', err);
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

        if (!currentActive.includes(element.id)) {
            return;
        }

        if (element.type === 'bpmn:UserTask' && typeof openTaskModal === 'function') {
            openTaskModal(element.id, element.businessObject.name || 'User Task Approval');
        }
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

    return {
        viewer,
        syncState: function(activeNodeIds = [], historyNodeIds = [], isFailed = false) {
            const canvas = viewer.get('canvas');

            currentActive.forEach(id => {
                if (!activeNodeIds.includes(id)) {
                    canvas.removeMarker(id, 'bpmn-active-token');
                    canvas.removeMarker(id, 'bpmn-failed-token');
                }
            });

            historyNodeIds.forEach(id => {
                if (!activeNodeIds.includes(id)) {
                    canvas.addMarker(id, 'bpmn-history-completed');
                }
            });

            activeNodeIds.forEach(id => {
                canvas.removeMarker(id, 'bpmn-history-completed');
                canvas.addMarker(id, isFailed ? 'bpmn-failed-token' : 'bpmn-active-token');
            });

            currentActive = activeNodeIds;
            currentHistory = historyNodeIds;
        }
    };
};