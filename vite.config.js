import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                entryFileNames: `[name].js`,
                chunkFileNames: `[name].js`,
                assetFileNames: `[name].[ext]`
            }
        },
        manifest: false
    },
    plugins: [
        laravel({
            hotFile: "vite.hot",
            publicDirectory: "resources",
            input: ['resources/css/slf-styles.css', 'resources/js/slf-scripts.js'],
            refresh: true,
        }),
    ],
});