// ---------------------------------------------------------------------------
// Engine Capabilities: Single Source of Truth
// ---------------------------------------------------------------------------
// Add BPMN node types here as their backend handlers are completed.
// All UI components (Palette, Context Pad, Replace Menu) derive their
// visibility rules directly from this registry.
// ---------------------------------------------------------------------------

export const DEFAULT_SUPPORTED_ELEMENTS = [
    // Events
    'bpmn:StartEvent',
    'bpmn:EndEvent',
    'bpmn:BoundaryEvent',

    // Activities / Tasks
    'bpmn:ServiceTask',
    'bpmn:UserTask',
    'bpmn:CallActivity',
    'bpmn:SubProcess',

    // Gateways
    'bpmn:ExclusiveGateway',
    'bpmn:ParallelGateway'
];

/**
 * Mapping between BPMN element types and their respective UI action keys
 * inside bpmn-js (palette entries, context-pad actions, and replace menu actions).
 */
export const ELEMENT_UI_MAPPING = {
    'bpmn:StartEvent': {
        palette: ['create.start-event'],
        contextPad: [],
        replace: ['replace-with-none-start']
    },
    'bpmn:EndEvent': {
        palette: ['create.end-event'],
        contextPad: ['append.end-event'],
        replace: ['replace-with-none-end']
    },
    'bpmn:BoundaryEvent': {
        palette: ['create.intermediate-event'],
        contextPad: ['append.intermediate-event'],
        replace: ['replace-with-error-boundary']
    },
    'bpmn:ServiceTask': {
        palette: ['create.service-task'],
        contextPad: ['append.service-task'],
        replace: ['replace-with-service-task']
    },
    'bpmn:UserTask': {
        palette: ['create.user-task'],
        contextPad: ['append.user-task'],
        replace: ['replace-with-user-task']
    },
    'bpmn:CallActivity': {
        palette: ['create.call-activity'],
        contextPad: ['append.call-activity'],
        replace: ['replace-with-call-activity']
    },
    'bpmn:SubProcess': {
        palette: ['create.subprocess-expanded', 'create.sub-process-expanded'],
        contextPad: ['append.subprocess-expanded'],
        replace: ['replace-with-expanded-subprocess', 'replace-with-collapsed-subprocess']
    },
    'bpmn:ExclusiveGateway': {
        palette: ['create.exclusive-gateway'],
        contextPad: ['append.exclusive-gateway'],
        replace: ['replace-with-exclusive-gateway']
    },
    'bpmn:ParallelGateway': {
        palette: ['create.parallel-gateway'],
        contextPad: ['append.parallel-gateway'],
        replace: ['replace-with-parallel-gateway']
    },
    'bpmn:Task': {
        palette: ['create.task'],
        contextPad: ['append.task'],
        replace: ['replace-with-task']
    },
    'bpmn:ScriptTask': {
        palette: ['create.script-task'],
        contextPad: ['append.script-task'],
        replace: ['replace-with-script-task']
    },
    'bpmn:BusinessRuleTask': {
        palette: ['create.business-rule-task'],
        contextPad: ['append.business-rule-task'],
        replace: ['replace-with-business-rule-task']
    },
    'bpmn:SendTask': {
        palette: ['create.send-task'],
        contextPad: ['append.send-task'],
        replace: ['replace-with-send-task']
    },
    'bpmn:ReceiveTask': {
        palette: ['create.receive-task'],
        contextPad: ['append.receive-task'],
        replace: ['replace-with-receive-task']
    },
    'bpmn:IntermediateCatchEvent': {
        palette: ['create.intermediate-event'],
        contextPad: ['append.intermediate-event'],
        replace: ['replace-with-intermediate-catch-none']
    }
};

// Global active list that can be updated dynamically
export let activeSupportedElements = new Set(DEFAULT_SUPPORTED_ELEMENTS);

export function setSupportedElements(elements) {
    activeSupportedElements = new Set(elements || DEFAULT_SUPPORTED_ELEMENTS);
}

export function resetSupportedElements() {
    activeSupportedElements = new Set(DEFAULT_SUPPORTED_ELEMENTS);
}

/**
 * Returns allowed IDs for a specific feature: 'palette', 'contextPad', or 'replace'
 */
export function getAllowedActionIds(type) {
    const allowed = new Set();

    activeSupportedElements.forEach(elementType => {
        const mapping = ELEMENT_UI_MAPPING[elementType];
        if (mapping && mapping[type]) {
            mapping[type].forEach(id => allowed.add(id));
        }
    });

    return allowed;
}