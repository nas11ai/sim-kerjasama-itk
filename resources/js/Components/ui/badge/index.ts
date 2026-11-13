import { cva, type VariantProps } from 'class-variance-authority'

export { default as Badge } from './UiBadge.vue'

export const badgeVariants = cva(
    'inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
    {
        variants: {
            variant: {
                default:
                    'border-transparent bg-primary text-primary-foreground shadow hover:bg-primary/80',
                secondary:
                    'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80',
                // Paket 2 P1 — nada status berupa tint (teks nada di atas latar pucat, >=5.6:1),
                // bukan blok solid: badge tidak bersaing dengan tombol aksi utama.
                destructive: 'border-destructive-border bg-destructive-subtle text-destructive',
                outline: 'text-foreground',
                success: 'border-success-border bg-success-subtle text-success',
                warning: 'border-warning-border bg-warning-subtle text-warning',
                info: 'border-info-border bg-info-subtle text-info',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    }
)

export type BadgeVariants = VariantProps<typeof badgeVariants>
