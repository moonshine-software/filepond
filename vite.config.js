import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        emptyOutDir: false,
        rollupOptions: {
            input: ['resources/js/filepond.js', 'resources/css/filepond.css'],
            output: {
                entryFileNames: `filepond.js`,
                assetFileNames: file => {
                    let ext = file.name.split('.').pop()
                    if (ext === 'css') {
                        return 'filepond.css'
                    }

                    return '[name].[ext]'
                }
            }
        },
        outDir: 'dist',
    },
});
