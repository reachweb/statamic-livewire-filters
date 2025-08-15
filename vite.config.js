import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
    ],
    build: {
        outDir: 'resources/frontend',
        emptyOutDir: false,
        rollupOptions: {
            input: {
                'app-css': 'resources/css/app.css',
                'app-js': 'resources/js/app.js'
            },
            output: {
                entryFileNames: (chunkInfo) => {
                    if (chunkInfo.name === 'app-js') {
                        return 'js/livewire-filters.js';
                    }
                    return 'js/[name].js';
                },
                assetFileNames: (assetInfo) => {
                    // Check if this is a CSS file
                    if (assetInfo.name && assetInfo.name.includes('.css')) {
                        // Use the filename prefix to determine which CSS this is
                        if (assetInfo.name.startsWith('app-css')) {
                            return 'css/livewire-filters-tailwind.css';
                        } else if (assetInfo.name.startsWith('app-js')) {
                            return 'css/livewire-filters.css';
                        }
                        
                        // Fallback: assign to tailwind (shouldn't happen)
                        return 'css/livewire-filters-tailwind.css';
                    }
                    
                    // Fallback for other assets
                    return 'assets/[name].[ext]';
                },
            },
        }
    },
});