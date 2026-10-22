import { getAllowedActionIds } from './engineCapabilities.js';

/**
 * Palette filter middleware.
 * Rather than rebuilding drag-and-drop tools and duplicating styling,
 * this provider intercepts entries built by the default PaletteProvider
 * and removes create actions for unsupported elements.
 */
export default class FilteredPaletteProvider {
    constructor(palette) {
        palette.registerProvider(this);
    }

    getPaletteEntries() {
        const allowedPaletteActions = getAllowedActionIds('palette');

        return function(entries) {
            Object.keys(entries).forEach(key => {
                // Keep standard manipulation tools: hand, lasso, space, connect, separators
                if (!key.startsWith('create.')) {
                    return;
                }

                // Remove create actions not registered in engine capabilities
                if (!allowedPaletteActions.has(key)) {
                    delete entries[key];
                }
            });

            return entries;
        };
    }
}

FilteredPaletteProvider.$inject = ['palette'];

export const FilteredPaletteModule = {
    __init__: ['filteredPaletteProvider'],
    filteredPaletteProvider: ['type', FilteredPaletteProvider]
};