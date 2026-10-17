// vite.config.js
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
    build: {
        outDir: 'public',
        emptyOutDir: false,
        lib: {
            entry: 'resources/js/saccharine-bpmn.js',
            name: 'BpmnDesigner', // The global variable name for the IIFE wrapper
            fileName: () => 'saccharine-bpmn.js',
            formats: ['iife'] // Compiles a standard, classic script (no export errors!)
        },
        rollupOptions: {
            output: {
                // Explicitly control output filenames for CSS and other bundled assets
                assetFileNames: (assetInfo) => {
                    // Rollup 4+ uses `names` (array of string names)
                    const names = assetInfo.names ?? (assetInfo.name ? [assetInfo.name] : []);
                    const isCss = names.some((name) => name.endsWith('.css'));

                    if (isCss) {
                        return 'saccharine-bpmn.css';
                    }

                    return '[name].[ext]';
                },
            },
        },
    }
});