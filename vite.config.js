import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        emptyOutDir: false,
        lib: {
            entry: 'resources/js/filepond.js',
            name: 'MoonshineFilepond',
            formats: ['iife'],
            fileName: () => 'filepond.js',
            cssFileName: 'filepond',
        },
        rollupOptions: {
            output: {
                assetFileNames: 'filepond.[ext]',
            }
        },
        outDir: 'dist',
    },
});
