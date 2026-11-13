<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { FileText, Search, User, Users, type LucideIcon } from 'lucide-vue-next'
import {
    ListboxContent,
    ListboxFilter,
    ListboxGroup,
    ListboxGroupLabel,
    ListboxItem,
    ListboxRoot,
} from 'reka-ui'
import { computed, useTemplateRef, watch } from 'vue'

import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog'
import { useGlobalSearch } from '@/composables/useGlobalSearch'
import { navGroups, visibleNavGroups } from '@/lib/navigation'
import { paletteEntries, searchPalette, type PaletteEntry } from '@/lib/paletteEntries'
import type { PageProps } from '@/types'
import type { GlobalSearchResult } from '@/types/search'

/**
 * K2 — command palette topbar (topbar.md). Dibangun dari Listbox reka-ui, bukan UiCommand:
 * UiCommand menyaring item di klien dan akan menyembunyikan hasil server yang cocok lewat
 * nama ketua atau NIDN. Halaman dan fitur disaring di klien (K10), grup lain dari `GET /cari`.
 */
const open = defineModel<boolean>('open', { required: true })

const page = usePage<PageProps>()
const { term, results, loading, failed, tooShort } = useGlobalSearch()
// ListboxRoot generik; yang dipakai hanya satu metode yang di-expose-nya.
const listbox = useTemplateRef<{ highlightFirstItem: () => void }>('listbox')

const SERVER_GROUPS: { key: keyof GlobalSearchResult; label: string; icon: LucideIcon }[] = [
    { key: 'submissions', label: 'Pengajuan', icon: FileText },
    { key: 'users', label: 'Pengguna', icon: User },
    { key: 'reviewers', label: 'Penilai', icon: Users },
]

const ITEM_CLASS =
    'relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground [&_svg]:size-4 [&_svg]:shrink-0'
const GROUP_LABEL_CLASS = 'text-muted-foreground px-2 py-1.5 text-xs font-medium'

const entries = computed(() => {
    // navigation null hanya untuk tamu; palette hanya ada di layout aplikasi.
    const flags = page.props.navigation ?? {
        hasOutputSubmission: false,
        hasMonevSubmission: false,
        unreadAnnouncements: 0,
        unreadNotifications: 0,
    }
    const permissions = page.props.auth.user?.permissions ?? []
    const groups = visibleNavGroups(navGroups, permissions, flags)

    return searchPalette(paletteEntries(groups, permissions, flags), term.value)
})

const LOCAL_GROUPS = [
    { kind: 'page', label: 'Halaman' },
    { kind: 'feature', label: 'Fitur' },
] as const

const localGroups = computed(() =>
    LOCAL_GROUPS.map((group) => ({
        ...group,
        entries: entries.value.filter((entry) => entry.kind === group.kind),
    })).filter((group) => group.entries.length > 0)
)

const serverGroups = computed(() =>
    SERVER_GROUPS.map((group) => ({ ...group, hits: results.value[group.key] })).filter(
        (group) => group.hits.length > 0
    )
)

// WCAG 4.1.3 — satu live region yang selalu ada, supaya perubahan pesannya terbaca.
const statusMessage = computed(() => {
    if (tooShort.value) return 'Ketik minimal 2 huruf untuk mencari pengajuan dan pengguna.'
    if (loading.value) return 'Mencari…'
    if (failed.value) return 'Pencarian gagal. Coba lagi.'
    if (entries.value.length === 0 && serverGroups.value.length === 0) return 'Tidak ada hasil.'

    return ''
})

// Hasil server datang belakangan; sorotan dipindah ke atas supaya Enter tetap mengena.
watch(results, () => listbox.value?.highlightFirstItem())

watch(open, (isOpen) => {
    if (!isOpen) term.value = ''
})

function go(url: string) {
    open.value = false
    router.visit(url)
}

function goToEntry(entry: PaletteEntry) {
    go(route(entry.routeName, entry.routeParams))
}

const entryKey = (entry: PaletteEntry) =>
    `${entry.kind}:${entry.routeName}:${JSON.stringify(entry.routeParams ?? {})}:${entry.label}`
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-[calc(100%-2rem)] gap-0 overflow-hidden p-0 sm:max-w-lg">
            <DialogTitle class="sr-only">Pencarian</DialogTitle>
            <DialogDescription class="sr-only">
                Cari halaman, fitur, pengajuan, pengguna, dan penilai. Panah atas dan bawah untuk
                memilih, Enter untuk membuka.
            </DialogDescription>

            <ListboxRoot ref="listbox" class="flex min-w-0 flex-col">
                <div class="flex items-center border-b pr-10 pl-3">
                    <Search class="mr-2 size-4 shrink-0 opacity-50" />
                    <ListboxFilter
                        v-model="term"
                        auto-focus
                        aria-label="Cari halaman, fitur, pengajuan, pengguna"
                        placeholder="Cari halaman, fitur, pengajuan, pengguna..."
                        class="placeholder:text-muted-foreground flex h-11 w-full border-0 bg-transparent px-0 py-3 text-sm outline-hidden focus:ring-0"
                    />
                </div>

                <ListboxContent
                    class="max-h-[min(60vh,360px)] overflow-x-hidden overflow-y-auto p-1"
                >
                    <ListboxGroup v-for="group in localGroups" :key="group.kind">
                        <ListboxGroupLabel :class="GROUP_LABEL_CLASS" data-palette-group-label>
                            {{ group.label }}
                        </ListboxGroupLabel>
                        <ListboxItem
                            v-for="entry in group.entries"
                            :key="entryKey(entry)"
                            :value="entryKey(entry)"
                            :class="ITEM_CLASS"
                            @select="goToEntry(entry)"
                        >
                            <component :is="entry.icon" />
                            <span class="min-w-0 flex-1 truncate">{{ entry.label }}</span>
                            <span
                                v-if="entry.location"
                                class="text-muted-foreground shrink-0 text-xs"
                                data-palette-location
                                >{{ entry.location }}</span
                            >
                        </ListboxItem>
                    </ListboxGroup>

                    <ListboxGroup v-for="group in serverGroups" :key="group.key">
                        <ListboxGroupLabel :class="GROUP_LABEL_CLASS" data-palette-group-label>
                            {{ group.label }}
                        </ListboxGroupLabel>
                        <ListboxItem
                            v-for="hit in group.hits"
                            :key="hit.id"
                            :value="`${group.key}:${hit.id}`"
                            :class="ITEM_CLASS"
                            @select="go(hit.url)"
                        >
                            <component :is="group.icon" />
                            <span class="flex min-w-0 flex-col">
                                <span class="truncate">{{ hit.label }}</span>
                                <span class="text-muted-foreground truncate text-xs">
                                    {{ hit.sublabel }}
                                </span>
                            </span>
                        </ListboxItem>
                    </ListboxGroup>
                </ListboxContent>

                <p
                    role="status"
                    aria-live="polite"
                    class="text-muted-foreground text-center text-sm"
                    :class="statusMessage ? 'border-t px-4 py-3' : ''"
                >
                    {{ statusMessage }}
                </p>
            </ListboxRoot>
        </DialogContent>
    </Dialog>
</template>
