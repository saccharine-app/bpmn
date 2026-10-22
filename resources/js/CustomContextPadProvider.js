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
            // Strip out the default options we don't want
            delete entries['replace'];
            delete entries['append.append-task']; // Removes the generic task
            delete entries['append.intermediate-event'];
            delete entries['append.text-annotation'];
            delete entries['append.gateway'];
            delete entries['append.end-event'];

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
                entries['append.service-task'] = appendAction(
                    'bpmn:ServiceTask', 'bpmn-icon-service-task', 'Append Service Task'
                );
                entries['append.user-task'] = appendAction(
                    'bpmn:UserTask', 'bpmn-icon-user-task', 'Append User Task'
                );
                entries['append.exclusive-gateway'] = appendAction(
                    'bpmn:ExclusiveGateway', 'bpmn-icon-gateway-xor', 'Append Exclusive Gateway'
                );
                entries['append.parallel-gateway'] = appendAction(
                    'bpmn:ParallelGateway', 'bpmn-icon-gateway-parallel', 'Append Parallel Gateway'
                );
                entries['append.end-event'] = appendAction(
                    'bpmn:EndEvent', 'bpmn-icon-end-event-none', 'Append End Event'
                );
            }

            entries['delete'] = {
                group: 'edit',
                className: 'bpmn-icon-trash',
                title: translate('Delete'),
                action: {
                    click: removeElement
                }
            };
            
            if (element.type !== 'bpmn:EndEvent') {
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