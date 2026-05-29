import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import ts from '@typescript-eslint/eslint-plugin'
import tsParser from '@typescript-eslint/parser'
import vueParser from 'vue-eslint-parser'
import prettierConfig from 'eslint-config-prettier'
import globals from 'globals'

export default [
    {
        ignores: ['resources/js/ziggy.js'],
    },

    js.configs.recommended,
    ...vue.configs['flat/recommended'],

    {
        files: ['**/*.ts'],
        languageOptions: {
            parser: tsParser,
            globals: {
                ...globals.node,
                ...globals.browser,
            },
        },
        plugins: { '@typescript-eslint': ts },
        rules: {
            '@typescript-eslint/no-explicit-any': 'error',
            'no-console': 'warn',
        },
    },

    {
        files: ['**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: tsParser,
                extraFileExtensions: ['.vue'],
            },
            globals: {
                ...globals.node,
                ...globals.browser,
            },
        },
        plugins: { '@typescript-eslint': ts },
        rules: {
            '@typescript-eslint/no-explicit-any': 'error',
            'vue/component-api-style': ['error', ['script-setup']],
            'vue/no-unused-vars': 'error',
            'no-console': 'warn',
        },
    },

    prettierConfig,
]
