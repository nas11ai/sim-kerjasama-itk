import type { VariantProps } from 'class-variance-authority'
import { cva } from 'class-variance-authority'

export { default as Alert } from './UiAlert.vue'
export { default as AlertDescription } from './AlertDescription.vue'
export { default as AlertTitle } from './AlertTitle.vue'

export const alertVariants = cva(
    'relative w-full rounded-lg border px-4 py-3 text-sm [&>svg+div]:translate-y-[-3px] [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg]:text-foreground [&>svg~*]:pl-7',
    {
        variants: {
            variant: {
                default: 'bg-background text-foreground',
                // Paket 2 P1 — tiap nada AppBanner terbedakan oleh warna, ikon, dan teks.
                destructive:
                    'border-destructive-border bg-destructive-subtle text-destructive [&>svg]:text-destructive',
                success:
                    'border-success-border bg-success-subtle text-success [&>svg]:text-success',
                warning:
                    'border-warning-border bg-warning-subtle text-warning [&>svg]:text-warning',
                info: 'border-info-border bg-info-subtle text-info [&>svg]:text-info',
                // Gembok: aturan yang sengaja menutup tindakan, bukan galat; tetap netral.
                lock: 'bg-muted text-foreground [&>svg]:text-muted-foreground',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    }
)

export type AlertVariants = VariantProps<typeof alertVariants>
