import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
  base: './',
  build: {
    manifest: true,
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      // Section/component assets are package-local and declared by block.json.
      // Vite owns global theme assets only, so adding a section never edits this file.
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
