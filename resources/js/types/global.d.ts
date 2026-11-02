import { PageProps as InertiaPageProps } from '@inertiajs/core'
import { AxiosInstance } from 'axios'
import { route as ziggyRoute } from 'ziggy-js'
import { PageProps as AppPageProps } from './'

declare global {
    interface Window {
        axios: AxiosInstance
    }

    var route: typeof ziggyRoute
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof ziggyRoute
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, AppPageProps {}

    // Bentuk flash native dari HandleInertiaRequests::bridgeSessionFlash(); kunci kosong tidak dikirim.
    interface InertiaConfig {
        // `issuedInvitationUrl` — tautan undangan tampil sekali, tak terputar ulang saat Back (W51).
        flashDataType: {
            message?: string
            error?: string
            success?: string
            issuedInvitationUrl?: string
        }
    }
}
