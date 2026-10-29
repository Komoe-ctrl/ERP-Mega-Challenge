import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

// Configuration séparée de vite.config.ts : les tests n'ont besoin ni de
// Laravel, ni de Wayfinder, ni de Tailwind.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/*.test.ts'],
    },
});
