import js from '@eslint/js';
import globals from 'globals';
import vue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';

export default [
    {
        ignores: ['public/build/**', 'node_modules/**', 'vendor/**'],
    },
    js.configs.recommended,
    ...vue.configs['flat/base'],
    {
        files: ['resources/js/**/*.js', 'resources/js/**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        rules: {
            'no-unused-vars': 'off',
            'no-undef': 'off',
            'vue/multi-word-component-names': 'off',
        },
    },
];
