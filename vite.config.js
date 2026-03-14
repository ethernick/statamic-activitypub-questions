import { defineConfig } from 'vite';
import vue2 from '@vitejs/plugin-vue2';
import vue3 from '@vitejs/plugin-vue';
import { resolve } from 'path';

const vueVersion = process.env.VUE_VERSION || '2';
const isVue2 = vueVersion === '2';
const distSubdir = isVue2 ? 'v5' : 'v6';

export default defineConfig({
    plugins: [
        isVue2 ? vue2() : vue3(),
    ],
    build: {
        outDir: `dist/${distSubdir}`,
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: {
                cp: resolve(__dirname, 'resources/js/cp.js'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name.endsWith('.css')) {
                        return 'css/cp.css';
                    }
                    return 'assets/[name]-[hash][extname]';
                },
                format: 'iife',
                globals: {
                    vue: 'Vue',
                },
            },
            external: ['vue'],
        },
    },
});
