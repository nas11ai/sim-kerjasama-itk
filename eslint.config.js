import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import ts from '@typescript-eslint/eslint-plugin'
import tsParser from '@typescript-eslint/parser'
import vueParser from 'vue-eslint-parser'
import prettierConfig from 'eslint-config-prettier'
import globals from 'globals'

/**
 * Aturan yang berlaku sama untuk .ts maupun .vue.
 *
 * Catatan penting soal no-unused-vars: rule bawaan JS tidak memahami posisi
 * tipe TypeScript maupun makro <script setup>, sehingga menghasilkan false
 * positive (mis. nama parameter di dalam anotasi tipe fungsi, atau `props`
 * yang jelas dipakai). Rule bawaan dimatikan, versi TS-aware yang dipakai.
 */
const sharedRules = {
    'no-unused-vars': 'off',
    '@typescript-eslint/no-unused-vars': [
        'error',
        {
            argsIgnorePattern: '^_',
            varsIgnorePattern: '^_',
            caughtErrorsIgnorePattern: '^_',
            destructuredArrayIgnorePattern: '^_',
        },
    ],

    // `any` mematikan seluruh manfaat TypeScript. Tidak ada pengecualian per
    // folder — per 2026-08-16 tidak ada satupun `any` hidup di codebase, jadi
    // aturan ini tidak menagih utang siapa pun.
    '@typescript-eslint/no-explicit-any': 'error',

    // console.error dan console.warn adalah pelaporan error yang disengaja dan
    // tetap diizinkan. Yang dilarang adalah sisa debugging — persis seperti
    // yang sudah tertulis di CODING_STANDARDS.md.
    'no-console': ['error', { allow: ['warn', 'error'] }],
    'no-debugger': 'error',

    // no-alert sengaja TIDAK diaktifkan. Codebase masih memakai confirm()
    // native di 9 tempat; menggantinya dengan komponen dialog adalah pekerjaan
    // UI, bukan lint. Lihat "Utang yang diketahui" di CODING_STANDARDS.md.

    eqeqeq: ['error', 'smart'],
    'prefer-const': 'error',
    'no-var': 'error',

    'vue/component-api-style': ['error', ['script-setup']],
    'vue/no-unused-vars': ['error', { ignorePattern: '^_' }],
}

const sharedGlobals = {
    ...globals.node,
    ...globals.browser,
    route: 'readonly',
}

export default [
    {
        ignores: [
            'resources/js/ziggy.js',
            '**/*.d.ts',
            'public/build/**',
            'dist/**',
            'vendor/**',
        ],
    },

    js.configs.recommended,
    ...vue.configs['flat/recommended'],

    {
        files: ['**/*.ts'],
        languageOptions: {
            parser: tsParser,
            globals: sharedGlobals,
        },
        plugins: { '@typescript-eslint': ts },
        rules: sharedRules,
    },

    {
        files: ['**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: tsParser,
                extraFileExtensions: ['.vue'],
            },
            globals: sharedGlobals,
        },
        plugins: { '@typescript-eslint': ts },
        rules: sharedRules,
    },

    {
        // Page Inertia dinamai mengikuti route (IndexPage, CreatePage), bukan
        // konvensi multi-kata Vue. Ini disengaja dan konsisten se-codebase.
        files: ['resources/js/Pages/**/*.vue', 'resources/js/Components/ui/**/*.vue'],
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },

    {
        // Test boleh memakai console bebas dan punya global-nya sendiri.
        files: ['tests/**/*.{ts,js}', '**/*.spec.ts', '**/*.test.ts'],
        rules: {
            'no-console': 'off',
        },
    },

    prettierConfig,
]
