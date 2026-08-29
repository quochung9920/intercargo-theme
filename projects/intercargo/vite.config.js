import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
  base: './',
  build: {
    manifest: true,
    outDir: resolve(import.meta.dirname, '../../themes/intercargo/dist'),
    emptyOutDir: true,
    rollupOptions: {
      // Design/package assets remain project-local. Vite bundles global assets only.
      input: [
        resolve(import.meta.dirname, 'src/css/global.css'),
        resolve(import.meta.dirname, 'src/css/editor.css'),
        resolve(import.meta.dirname, 'src/js/site.js'),
        resolve(import.meta.dirname, 'src/js/editor.js'),
      ],
      output: {
        banner: '(() => {',
        footer: '})();',
        assetFileNames: 'assets/[name]-[hash][extname]',
        entryFileNames: 'assets/[name]-[hash].js',
      },
    },
  },
});
