import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/public/system.css',
                'resources/js/public/behaviors.js',
            ],
            refresh: [
                'app/Livewire/**',
                'app/View/Components/**',
                'resources/views/**',
                'routes/**',
            ],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        chunkSizeWarningLimit: 1100,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/html2pdf.js')) {
                        return 'html2pdf';
                    }

                    if (id.includes('node_modules/exceljs')) {
                        return 'exceljs';
                    }

                    if (id.includes('node_modules/echarts-gl')) {
                        return 'echarts-gl';
                    }

                    if (id.includes('node_modules/echarts')) {
                        return 'echarts';
                    }

                    if (id.includes('node_modules/@codemirror')) {
                        return 'codemirror';
                    }
                },
            },
        },
    },
});
