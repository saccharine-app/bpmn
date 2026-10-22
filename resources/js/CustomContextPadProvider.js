// ---------------------------------------------------------------------------
// Modular context-pad configuration
// ---------------------------------------------------------------------------
// Specific context-pad (append) actions can be enabled/disabled without
// removing the context pad UI itself. Defaults cover every node type the
// Saccharine BPMN engine can execute.
// ---------------------------------------------------------------------------
export const defaultContextPadConfig = {
    serviceTask: true,
    userTask: true,
    exclusiveGateway: true,
    parallelGateway: true,
    endEvent: true,
    delete: true,
    connect: true
};

// Mutable runtime config. Read on every getContextPadEntries() call.
export let contextPadConfig = { ...defaultContextPadConfig };

export function setContextPadConfig(next) {
    contextPadConfig = { ...defaultContextPadConfig, ...next };
}

export function resetContextPadConfig() {
    contextPadConfig = { ...defaultContextPadConfig };
}

export default class CustomContextPadProvider {
    constructor(contextPad, modeling, autoPlace, connect, create, elementFactory, translate, appendPreview) {
        this._modeling = modeling;
        this._autoPlace = autoPlace;
        this._connect = connect;
        this._create = create;
        this._elementFactory = elementFactory;
        this._translate = translate;
        this._appendPreview = appendPreview;

        contextPad.registerProvider(this);
    }

    getContextPadEntries(element) {
        const {
            _modeling: modeling,
            _autoPlace: autoPlace,
            _connect: connect,
            _create: create,
            _elementFactory: elementFactory,
            _translate: translate,
            _appendPreview: appendPreview
        } = this;

        return function(entries) {
            const cfg = contextPadConfig;

            // Strip out the default options we don't want
            // NOTE: 'replace' (the wrench/gear menu) is intentionally kept so
            // users can transform existing elements. Its replace-with-* targets
            // are filtered by CustomReplaceMenuProvider instead.
            /* delete entries['append.append-task']; // Removes the generic task
            delete entries['append.intermediate-event'];
            delete entries['append.text-annotation'];
            delete entries['append.gateway'];
            delete entries['append.end-event']; */

            // Helper function to create an "Append" action
            function appendAction(type, className, title, options) {
                // Dragging the icon onto the canvas
                function appendStart(event, element) {
                    const shape = elementFactory.createShape({ type: type });
                    create.start(event, shape, { source: element });
                }

                // Clicking the icon to auto-place it next to the current node
                function append(event, element) {
                    const shape = elementFactory.createShape({ type: type });
                    autoPlace.append(element, shape);
                }

                var previewAppend = autoPlace ? function(_, element) {
                    // mouseover
                    appendPreview.create(element, type, options);

                    return () => {
                        // mouseout
                        appendPreview.cleanUp();
                    };
                } : null;

                return {
                    group: 'model',
                    className: className,
                    title: translate(title),
                    action: {
                        dragstart: appendStart,
                        click: append,
                        hover: previewAppend
                    }
                };
            }

            function startConnect(event, element) {
                connect.start(event, element);
            }

            function removeElement(e, element) {
                modeling.removeElements([ element ]);
            }

            // Hoist our custom elements into the menu!
            // We ensure these options don't appear if the user clicks an End Event (which can't have outgoing arrows)
            if (element.type !== 'bpmn:EndEvent') {
                if (cfg.serviceTask) {
                    entries['append.service-task'] = appendAction(
                        'bpmn:ServiceTask', 'bpmn-icon-service-task', 'Append Service Task'
                    );
                }
                if (cfg.userTask) {
                    entries['append.user-task'] = appendAction(
                        'bpmn:UserTask', 'bpmn-icon-user-task', 'Append User Task'
                    );
                }
                if (cfg.exclusiveGateway) {
                    entries['append.exclusive-gateway'] = appendAction(
                        'bpmn:ExclusiveGateway', 'bpmn-icon-gateway-xor', 'Append Exclusive Gateway'
                    );
                }
                if (cfg.parallelGateway) {
                    entries['append.parallel-gateway'] = appendAction(
                        'bpmn:ParallelGateway', 'bpmn-icon-gateway-parallel', 'Append Parallel Gateway'
                    );
                }
                if (cfg.endEvent) {
                    entries['append.end-event'] = appendAction(
                        'bpmn:EndEvent', 'bpmn-icon-end-event-none', 'Append End Event'
                    );
                }
            }

            if (cfg.delete) {
                entries['delete'] = {
                    group: 'edit',
                    className: 'bpmn-icon-trash',
                    title: translate('Delete'),
                    action: {
                        click: removeElement
                    }
                };
            }

            if (element.type !== 'bpmn:EndEvent' && cfg.connect) {
                entries['connect'] = {
                    group: 'connect',
                    className: 'bpmn-icon-connection-multi',
                    title: translate('Connect to other element'),
                    action: {
                        click: startConnect,
                        dragstart: startConnect,
                    },
                };
            }

            return entries;
        };
    }
}

// Inject the core bpmn-js drawing tools required to make appending work
CustomContextPadProvider.$inject = [
    'contextPad',
    'modeling',
    'autoPlace',
    'connect',
    'create',
    'elementFactory',
    'translate',
    'appendPreview'
];

export const CustomContextPadModule = {
    __init__: [ 'contextPadProvider' ],
    contextPadProvider: [ 'type', CustomContextPadProvider ]
};