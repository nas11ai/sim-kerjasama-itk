declare module 'ziggy-js' {
    import { Plugin } from 'vue'

    export type RouteParams = Record<string, unknown> | string | number | string[]

    export interface Router {
        (name?: string, params?: RouteParams, absolute?: boolean, config?: Record<string, unknown>): string
    }

    export const route: Router
    export const ZiggyVue: Plugin
    export function useRoute(config?: Record<string, unknown>): Router
}
