import { defineConfig } from 'vite';
import statamic from '@statamic/cms/vite-plugin';

/**
 * Control panel assets only: the two editors. The front end ships zero
 * JavaScript and is not built.
 *
 * Statamic's own Vite plugin marks the control panel's Vue and UI kit as
 * externals, so this bundle uses the ones already on the page rather than
 * shipping a second copy of Vue into somebody's admin.
 *
 * The output path is what AddonServiceProvider::registerVite expects:
 * `publicDirectory` + `buildDirectory`, published to public/vendor/<package>.
 */
export default defineConfig({
    plugins: [statamic()],
    build: {
        outDir: 'resources/dist/build',
        emptyOutDir: true,
        // Named, because Vite 6 would otherwise write .vite/manifest.json
        // while Laravel looks for <buildDirectory>/manifest.json.
        manifest: 'manifest.json',
        rollupOptions: {
            input: { cp: 'resources/js/cp.js' },
        },
    },
});
