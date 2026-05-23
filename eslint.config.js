import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import ts from '@typescript-eslint/eslint-plugin'
import tsParser from '@typescript-eslint/parser'

js.configs.recommended,
...vue.configs['flat/recommended'],
export default [
    {
        files: ['**/*.ts', '**/*.vue'],
        languageOptions: {
            parser: tsParser,
            parserOptions: { extraFileExtensions: ['.vue'] },
        },
        plugins: { '@typescript-eslint': ts },
        rules: {
            '@typescript-eslint/no-explicit-any': 'error',
            'vue/component-api-style': ['error', ['script-setup']],
            'vue/no-unused-vars': 'error',
            'no-console': 'warn',
        },
        "env": {
            "node": true
        }
    },

]
