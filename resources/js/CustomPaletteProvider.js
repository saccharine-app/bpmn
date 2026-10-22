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

        return {
            'hand-tool': {
                group: 'tools',
                className: 'bpmn-icon-hand-tool',
                title: 'Activate the hand tool',
                action: {
                    click: function(event) {
                        handTool.activateHand(event);
                    }
                }
            },
            'lasso-tool': {
                group: 'tools',
                className: 'bpmn-icon-lasso-tool',
                title: 'Activate the lasso tool',
                action: {
                    click: function(event) {
                        lassoTool.activateSelection(event);
                    }
                }
            },
            'space-tool': {
                group: 'tools',
                className: 'bpmn-icon-space-tool',
                title: 'Activate the create/remove space tool',
                action: {
                    click: function(event) {
                        spaceTool.activateSelection(event);
                    }
                }
            },
            'global-connect-tool': {
                group: 'tools',
                className: 'bpmn-icon-connection-multi',
                title: 'Activate the global connect tool',
                action: {
                    click: function(event) {
                        globalConnect.start(event);
                    }
                }
            },
            'tool-separator': {
                group: 'tools',
                separator: true
            },
            'create.start-event': createAction(
                'bpmn:StartEvent', 'event', 'bpmn-icon-start-event-none', 'Create Start Event'
            ),
            'create.end-event': createAction(
                'bpmn:EndEvent', 'event', 'bpmn-icon-end-event-none', 'Create End Event'
            ),
            'create.service-task': createAction(
                'bpmn:ServiceTask', 'activity', 'bpmn-icon-service-task', 'Create Automated Service Task'
            ),
            'create.user-task': createAction(
                'bpmn:UserTask', 'activity', 'bpmn-icon-user-task', 'Create Human-in-the-Loop Task'
            ),
            'create.exclusive-gateway': createAction(
                'bpmn:ExclusiveGateway', 'gateway', 'bpmn-icon-gateway-xor', 'Create Exclusive Gateway (If/Else)'
            ),
            'create.parallel-gateway': createAction(
                'bpmn:ParallelGateway', 'gateway', 'bpmn-icon-gateway-parallel', 'Create Parallel Gateway (AND)'
            )
        };
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