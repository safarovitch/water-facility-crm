import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts', "resources/css/app.css"],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
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
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // Only chunk physical files inside node_modules
                    if (!id.includes('node_modules')) {
                        return;
                    }

                    // vendor-vue (Safely catches vue, inertia, and vueuse)
                    if (
                        id.includes('node_modules/vue/') || 
                        id.includes('node_modules/@inertiajs/vue3') || 
                        id.includes('node_modules/@vueuse/core')
                    ) {
                        return 'vendor-vue';
                    }

                    // vendor-ui
                    if (
                        id.includes('node_modules/reka-ui') || 
                        id.includes('node_modules/class-variance-authority') || 
                        id.includes('node_modules/clsx') || 
                        id.includes('node_modules/tailwind-merge')
                    ) {
                        return 'vendor-ui';
                    }

                    // vendor-sip
                    if (id.includes('node_modules/sip.js')) {
                        return 'vendor-sip';
                    }
                }
            },
        },
    },
});
