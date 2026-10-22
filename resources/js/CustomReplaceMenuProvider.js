// ---------------------------------------------------------------------------
// Modular replace-menu (gear menu) configuration
// ---------------------------------------------------------------------------
// The bpmn-js "gear" menu lets users change an existing element into another
// node type. This config filters out unimplemented node types while keeping
// the menu functional for engine-supported types.
//
// Entries are identified by their replace action name (e.g.
// `replace-with-service-task`). Only listed actions are kept in the menu;
// everything else is hidden. Defaults cover every node type the Saccharine
// BPMN engine can execute.
//
// This list doubles as a coverage checklist: as new node handler classes are
// implemented, add the corresponding action name here and it immediately
// appears in both the palette and gear menu.
//
// NOTE: This is independent of the palette config. Palette (creation) and
// replace menu (transformation) are governed by separate configs.
// ---------------------------------------------------------------------------
export const defaultReplaceMenuConfig = {
  allowedActions: [
    // events
    'replace-with-none-start',   // bpmn:StartEvent
    'replace-with-none-end',     // bpmn:EndEvent

    // activities
    'replace-with-service-task',        // bpmn:ServiceTask
    'replace-with-user-task',           // bpmn:UserTask
    'replace-with-call-activity',       // bpmn:CallActivity
    'replace-with-collapsed-subprocess', // bpmn:SubProcess
    'replace-with-expanded-subprocess',  // bpmn:SubProcess

    // gateways
    'replace-with-exclusive-gateway',   // bpmn:ExclusiveGateway
    'replace-with-parallel-gateway',    // bpmn:ParallelGateway
  ]
};

// Mutable runtime config. Read on every getPopupMenuEntries() call.
export let replaceMenuConfig = {
  allowedActions: [...defaultReplaceMenuConfig.allowedActions]
};

// Replace the entire config (defaults applied for missing keys).
export function setReplaceMenuConfig(next) {
  replaceMenuConfig = {
    allowedActions: next && next.allowedActions
      ? [...next.allowedActions]
      : [...defaultReplaceMenuConfig.allowedActions]
  };
}

// Reset to defaults.
export function resetReplaceMenuConfig() {
  replaceMenuConfig = {
    allowedActions: [...defaultReplaceMenuConfig.allowedActions]
  };
}

export default class CustomReplaceMenuProvider {
  constructor(popupMenu) {
    this._popupMenu = popupMenu;

    // Register under the same provider id as the default bpmn-js
    // ReplaceMenuProvider ('bpmn-replace') but at a LOWER priority so our
    // entry-updater runs AFTER the default provider has populated the menu,
    // allowing us to filter out unsupported node types.
    popupMenu.registerProvider('bpmn-replace', 500, this);
  }

  /**
   * @return {Function} An entries updater that filters the gear-menu entries
   *                    down to the engine-supported node types.
   */
  getPopupMenuEntries(target) {
    const allowed = new Set(replaceMenuConfig.allowedActions);

    return function(entries) {
      Object.keys(entries).forEach(id => {
        if (!allowed.has(id)) {
          delete entries[id];
        }
      });

      return entries;
    };
  }
}

CustomReplaceMenuProvider.$inject = [
  'popupMenu'
];

export const CustomReplaceMenuModule = {
  __init__: [ 'replaceMenuProvider' ],
  replaceMenuProvider: [ 'type', CustomReplaceMenuProvider ]
};
