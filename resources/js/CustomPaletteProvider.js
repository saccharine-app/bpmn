// ---------------------------------------------------------------------------
// Modular palette configuration
// ---------------------------------------------------------------------------
// Specific palette entries can be enabled/disabled without removing the palette
// UI itself. Defaults cover every node type the Saccharine BPMN engine can
// execute. Mutating this object (or passing overrides to initBpmnDesigner)
// toggles individual entries at runtime; the palette groups stay intact.
// ---------------------------------------------------------------------------
export const defaultPaletteConfig = {
    // tools (always rendered, but made togglable for completeness)
    handTool: true,
    lassoTool: true,
    spaceTool: true,
    globalConnectTool: true,

    // events
    startEvent: true,
    endEvent: true,

    // activities
    serviceTask: true,
    userTask: true,
    callActivity: true,
    subProcess: true,

    // gateways
    exclusiveGateway: true,
    parallelGateway: true
};

// Mutable runtime config. Read on every getPaletteEntries() call so hosts can
// enable/disable specific nodes without rebuilding the modeler.
export let paletteConfig = { ...defaultPaletteConfig };

// Replace the entire config (flat boolean map; defaults applied for missing keys).
export function setPaletteConfig(next) {
    paletteConfig = { ...defaultPaletteConfig, ...next };
}

// Reset to defaults.
export function resetPaletteConfig() {
    paletteConfig = { ...defaultPaletteConfig };
}

export default class CustomPaletteProvider {
    constructor(palette, create, elementFactory, spaceTool, lassoTool, handTool, globalConnect) {
        this._create = create;
        this._elementFactory = elementFactory;
        this._spaceTool = spaceTool;
        this._lassoTool = lassoTool;
        this._handTool = handTool;
        this._globalConnect = globalConnect;

        // Register this class as a provider for the palette
        palette.registerProvider(this);
    }

    getPaletteEntries(element) {
        const {
            _create: create,
            _elementFactory: elementFactory,
            _spaceTool: spaceTool,
            _lassoTool: lassoTool,
            _handTool: handTool,
            _globalConnect: globalConnect
        } = this;
        const cfg = paletteConfig;

        // Helper function to create a drag-and-drop action for a specific BPMN element
        function createAction(type, group, className, title, options) {
            function createListener(event) {
                const shape = elementFactory.createShape(Object.assign({ type: type }, options));
                create.start(event, shape);
            }

            return {
                group: group,
                className: className,
                title: title,
                action: {
                    dragstart: createListener,
                    click: createListener
                }
            };
        }

        const entries = {};

        // --- tools -----------------------------------------------------------
        if (cfg.handTool) {
            entries['hand-tool'] = {
                group: 'tools',
                className: 'bpmn-icon-hand-tool',
                title: 'Activate the hand tool',
                action: {
                    click: function(event) {
                        handTool.activateHand(event);
                    }
                }
            };
        }
        if (cfg.lassoTool) {
            entries['lasso-tool'] = {
                group: 'tools',
                className: 'bpmn-icon-lasso-tool',
                title: 'Activate the lasso tool',
                action: {
                    click: function(event) {
                        lassoTool.activateSelection(event);
                    }
                }
            };
        }
        if (cfg.spaceTool) {
            entries['space-tool'] = {
                group: 'tools',
                className: 'bpmn-icon-space-tool',
                title: 'Activate the create/remove space tool',
                action: {
                    click: function(event) {
                        spaceTool.activateSelection(event);
                    }
                }
            };
        }
        if (cfg.globalConnectTool) {
            entries['global-connect-tool'] = {
                group: 'tools',
                className: 'bpmn-icon-connection-multi',
                title: 'Activate the global connect tool',
                action: {
                    click: function(event) {
                        globalConnect.start(event);
                    }
                }
            };
        }
        entries['tool-separator'] = {
            group: 'tools',
            separator: true
        };

        // --- events ----------------------------------------------------------
        if (cfg.startEvent) {
            entries['create.start-event'] = createAction(
                'bpmn:StartEvent', 'event', 'bpmn-icon-start-event-none', 'Create Start Event'
            );
        }
        if (cfg.endEvent) {
            entries['create.end-event'] = createAction(
                'bpmn:EndEvent', 'event', 'bpmn-icon-end-event-none', 'Create End Event'
            );
        }

        // --- activities ------------------------------------------------------
        if (cfg.serviceTask) {
            entries['create.service-task'] = createAction(
                'bpmn:ServiceTask', 'activity', 'bpmn-icon-service-task', 'Create Automated Service Task'
            );
        }
        if (cfg.userTask) {
            entries['create.user-task'] = createAction(
                'bpmn:UserTask', 'activity', 'bpmn-icon-user-task', 'Create Human-in-the-Loop Task'
            );
        }
        if (cfg.callActivity) {
            entries['create.call-activity'] = createAction(
                'bpmn:CallActivity', 'activity', 'bpmn-icon-call-activity', 'Create Call Activity'
            );
        }
        if (cfg.subProcess) {
            entries['create.sub-process-expanded'] = createAction(
                'bpmn:SubProcess', 'activity', 'bpmn-icon-subprocess-expanded', 'Create Sub-Process'
            );
        }

        // --- gateways --------------------------------------------------------
        if (cfg.exclusiveGateway) {
            entries['create.exclusive-gateway'] = createAction(
                'bpmn:ExclusiveGateway', 'gateway', 'bpmn-icon-gateway-xor', 'Create Exclusive Gateway (If/Else)'
            );
        }
        if (cfg.parallelGateway) {
            entries['create.parallel-gateway'] = createAction(
                'bpmn:ParallelGateway', 'gateway', 'bpmn-icon-gateway-parallel', 'Create Parallel Gateway (AND)'
            );
        }

        return entries;
    }
}

// Inject the internal bpmn-js modules our provider needs
CustomPaletteProvider.$inject = [
    'palette',
    'create',
    'elementFactory',
    'spaceTool',
    'lassoTool',
    'handTool',
    'globalConnect'
];

// Export it as a standard module format that bpmn-js expects
export const CustomPaletteModule = {
    __init__: [ 'paletteProvider' ],
    paletteProvider: [ 'type', CustomPaletteProvider ]
};