import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['tests/**/*.test.ts', 'tests/**/*.spec.ts'],
        exclude: [
            'node_modules',
            'dist',
            'build',
            'tests/e2e', 
        ],
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
})
