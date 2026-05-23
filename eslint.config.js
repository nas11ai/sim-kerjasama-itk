import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import ts from '@typescript-eslint/eslint-plugin'
import tsParser from '@typescript-eslint/parser'
import prettierConfig from 'eslint-config-prettier'

js.configs.recommended,
...vue.configs['flat/recommended'],
export default [
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
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
