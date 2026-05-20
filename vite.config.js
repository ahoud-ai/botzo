import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import i18n from 'laravel-vue-i18n/vite'; 
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite';
import vue from '@vitejs/plugin-vue'
import path from 'path';

const normalizedId = (id) => id.split(path.sep).join('/');

const vendorChunkName = (moduleId) => {
    const match = moduleId.match(/node_modules\/((?:@[^/]+\/)?[^/]+)/);

    if (!match) {
        return 'vendor';
    }

    return `vendor-${match[1].replace('@', '').replace('/', '-')}`;
};

export default defineConfig({
    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    // Only transform relative assets, not absolute paths starting with /
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VueI18nPlugin({
            // Define the path to your locale files
            include: path.resolve(__dirname, 'lang/**')
        }),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        i18n(),
    ],
    resolve: {
        alias: {
            '@modules': path.resolve(__dirname, 'modules'),
        },
    },
    build: {
        chunkSizeWarningLimit: 550,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        const moduleId = normalizedId(id);

                        if (
                            moduleId.includes('vue3-apexcharts') ||
                            moduleId.includes('/apexcharts/')
                        ) {
                            return 'charting';
                        }

                        if (
                            moduleId.includes('vue-tel-input') ||
                            moduleId.includes('/libphonenumber-js/')
                        ) {
                            return 'phone-input';
                        }

                        if (moduleId.includes('/@vue-flow/')) {
                            return 'flow-builder-core';
                        }

                        if (
                            moduleId.includes('/@vueup/vue-quill/') ||
                            moduleId.includes('/quill/') ||
                            moduleId.includes('/quill-delta/') ||
                            moduleId.includes('/parchment/') ||
                            moduleId.includes('/monaco-editor/') ||
                            moduleId.includes('/@wdns/vue-code-block/')
                        ) {
                            return 'editor-libs';
                        }

                        if (
                            moduleId.includes('/pusher-js/') ||
                            moduleId.includes('/laravel-echo/') ||
                            moduleId.includes('/socket.io-client/') ||
                            moduleId.includes('/socket.io-parser/')
                        ) {
                            return 'realtime';
                        }

                        if (
                            moduleId.includes('/@ffmpeg/core/') ||
                            moduleId.includes('/lamejs/') ||
                            moduleId.includes('/@breezystack/lamejs/') ||
                            moduleId.includes('/mic-recorder-to-mp3-fixed/')
                        ) {
                            return 'media-tools';
                        }

                        if (
                            moduleId.includes('/lucide-vue-next/') ||
                            moduleId.includes('/@heroicons/vue/')
                        ) {
                            return 'icon-libs';
                        }

                        if (
                            moduleId.includes('/@headlessui/vue/') ||
                            moduleId.includes('/radix-vue/') ||
                            moduleId.includes('/@floating-ui/')
                        ) {
                            return 'overlay-libs';
                        }

                        if (
                            moduleId.includes('/vue3-toastify/') ||
                            moduleId.includes('/vue3-emoji-picker/') ||
                            moduleId.includes('/laravel-vue-pagination/') ||
                            moduleId.includes('/vuedraggable/') ||
                            moduleId.includes('/vue3-google-map/')
                        ) {
                            return 'ui-vue-libs';
                        }

                        if (
                            moduleId.includes('/lodash/') ||
                            moduleId.includes('/tailwind-merge/') ||
                            moduleId.includes('/class-variance-authority/')
                        ) {
                            return 'utility-libs';
                        }

                        if (
                            moduleId.includes('/vue/') ||
                            moduleId.includes('/@vue/') ||
                            moduleId.includes('/@inertiajs/') ||
                            moduleId.includes('/@intlify/') ||
                            moduleId.includes('/vue-i18n/') ||
                            moduleId.includes('/laravel-vue-i18n/')
                        ) {
                            return 'app-core';
                        }

                        return vendorChunkName(moduleId);
                    }
                },
            },
            onwarn(warning, warn) {
                // Suppress warnings/errors about unresolved public directory assets
                if (warning.code === 'UNRESOLVED_IMPORT' && 
                    warning.id && 
                    (warning.id.startsWith('/images/') || 
                     warning.id.startsWith('/fonts/') || 
                     warning.id.startsWith('/sounds/'))) {
                    return;
                }
                warn(warning);
            },
        },
    },
});
