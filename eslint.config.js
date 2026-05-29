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
