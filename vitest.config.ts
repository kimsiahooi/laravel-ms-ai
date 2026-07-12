import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// Standalone Vitest config — deliberately NOT the app's vite.config.ts, which loads
// the laravel()/wayfinder() plugins that shell out to `php artisan`. Tests only need
// the React transform + the `@/` alias (mirrors tsconfig.json paths).
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: false,
        setupFiles: ['./vitest.setup.ts'],
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
        css: false,
    },
});
