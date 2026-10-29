import js from '@eslint/js';
import { defineConfig } from 'eslint/config';
import prettier from 'eslint-config-prettier';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default defineConfig(
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'storage/**',
            'bootstrap/**',
            // Générés par Wayfinder (php artisan wayfinder:generate)
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            // Composants shadcn-vue : code de la bibliothèque
            'resources/js/components/ui/**',
        ],
    },
    js.configs.recommended,
    tseslint.configs.recommended,
    pluginVue.configs['flat/recommended'],
    {
        files: ['**/*.vue'],
        languageOptions: { parserOptions: { parser: tseslint.parser } },
    },
    {
        languageOptions: { globals: { ...globals.browser, ...globals.node } },
        rules: {
            // Pages Inertia nommées Index.vue / Form.vue : le dossier donne le sens.
            'vue/multi-word-component-names': 'off',
            '@typescript-eslint/no-explicit-any': 'warn',
            'vue/require-default-prop': 'off',
            // Mise en forme : déjà gérée par Prettier / code du starter kit.
            'vue/attributes-order': 'off',
            'vue/attribute-hyphenation': 'off',
            'vue/v-slot-style': 'off',
        },
    },
    prettier,
);
