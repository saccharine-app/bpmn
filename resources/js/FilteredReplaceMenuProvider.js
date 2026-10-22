import { getAllowedActionIds } from './engineCapabilities.js';

/**
 * Replace Menu (gear icon) filter middleware.
 * Intercepts replacement candidates and filters out actions that
 * the engine cannot execute.
 */
export default class FilteredReplaceMenuProvider {
    constructor(popupMenu) {
        popupMenu.registerProvider('bpmn-replace', this);
    }

    getPopupMenuEntries() {
        const allowedReplaceActions = getAllowedActionIds('replace');

        return function(entries) {
            if (!allowedReplaceActions.size) {
                return entries;
            }

            // Supports diagram-js v8+ array structure
            if (Array.isArray(entries)) {
                return entries.filter(entry => allowedReplaceActions.has(entry.id || entry.actionName));
            }

            // Supports classic diagram-js / bpmn-js dictionary structure
            const filtered = {};
            Object.keys(entries).forEach(key => {
                const entry = entries[key];
                const actionId = (entry && entry.id) ? entry.id : key;
                if (allowedReplaceActions.has(actionId) || allowedReplaceActions.has(key)) {
                    filtered[key] = entry;
                }
            });

            return filtered;
        };
    }
}

FilteredReplaceMenuProvider.$inject = ['popupMenu'];

export const FilteredReplaceMenuModule = {
    __init__: ['filteredReplaceMenuProvider'],
    filteredReplaceMenuProvider: ['type', FilteredReplaceMenuProvider]
};