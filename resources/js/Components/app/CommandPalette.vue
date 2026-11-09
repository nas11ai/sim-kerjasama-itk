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
import { navGroups, searchNavItems, visibleNavGroups, type VisibleNavItem } from '@/lib/navigation'
import type { PageProps } from '@/types'
import type { GlobalSearchResult } from '@/types/search'

/**
 * K2 — command palette topbar (topbar.md). Dibangun dari Listbox reka-ui, bukan UiCommand:
 * UiCommand menyaring item di klien dan akan menyembunyikan hasil server yang cocok lewat
 * nama ketua atau NIDN. Navigasi disaring di klien, grup lain dari `GET /cari`.
 */
const open = defineModel<boolean>('open', { required: true })

const page = usePage<PageProps>()
const { term, results, loading, failed, tooShort } = useGlobalSearch()
// ListboxRoot generik; yang dipakai hanya satu metode yang di-expose-nya.
const listbox = useTemplateRef<{ highlightFirstItem: () => void }>('listbox')

const SERVER_GROUPS: { key: keyof GlobalSearchResult; label: string; icon: LucideIcon }[] = [
    { key: 'submissions', label: 'Pengajuan', icon: FileText },
    { key: 'users', label: 'User', icon: User },
    { key: 'reviewers', label: 'Reviewer', icon: Users },
]

const ITEM_CLASS =
    'relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground [&_svg]:size-4 [&_svg]:shrink-0'
const GROUP_LABEL_CLASS = 'text-muted-foreground px-2 py-1.5 text-xs font-medium'

const navItems = computed(() => {
    // navigation null hanya untuk tamu; palette hanya ada di layout aplikasi.
    const flags = page.props.navigation ?? {
        hasOutputSubmission: false,
        hasMonevSubmission: false,
        unreadAnnouncements: 0,
        unreadNotifications: 0,
    }
    const groups = visibleNavGroups(navGroups, page.props.auth.user?.permissions ?? [], flags)

    return searchNavItems(groups, term.value)
})

const serverGroups = computed(() =>
    SERVER_GROUPS.map((group) => ({ ...group, hits: results.value[group.key] })).filter(
        (group) => group.hits.length > 0
    )
)

// WCAG 4.1.3 — satu live region yang selalu ada, supaya perubahan pesannya terbaca.
const statusMessage = computed(() => {
    if (tooShort.value) return 'Ketik minimal 2 huruf untuk mencari pengajuan dan user.'
    if (loading.value) return 'Mencari…'
    if (failed.value) return 'Pencarian gagal. Coba lagi.'
    if (navItems.value.length === 0 && serverGroups.value.length === 0) return 'Tidak ada hasil.'

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

function goToNav(item: VisibleNavItem) {
    go(route(item.routeName))
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-[calc(100%-2rem)] gap-0 overflow-hidden p-0 sm:max-w-lg">
            <DialogTitle class="sr-only">Pencarian</DialogTitle>
            <DialogDescription class="sr-only">
                Cari halaman, pengajuan, user, dan reviewer. Panah atas dan bawah untuk memilih,
                Enter untuk membuka.
            </DialogDescription>

            <ListboxRoot ref="listbox" class="flex min-w-0 flex-col">
                <div class="flex items-center border-b pr-10 pl-3">
                    <Search class="mr-2 size-4 shrink-0 opacity-50" />
                    <ListboxFilter
                        v-model="term"
                        auto-focus
                        aria-label="Cari halaman, pengajuan, user"
                        placeholder="Cari halaman, pengajuan, user..."
                        class="placeholder:text-muted-foreground flex h-11 w-full bg-transparent py-3 text-sm outline-hidden"
                    />
                </div>

                <ListboxContent
                    class="max-h-[min(60vh,360px)] overflow-x-hidden overflow-y-auto p-1"
                >
                    <ListboxGroup v-if="navItems.length > 0">
                        <ListboxGroupLabel :class="GROUP_LABEL_CLASS" data-palette-group-label>
                            Navigasi
                        </ListboxGroupLabel>
                        <ListboxItem
                            v-for="item in navItems"
                            :key="item.routeName"
                            :value="`nav:${item.routeName}`"
                            :class="ITEM_CLASS"
                            @select="goToNav(item)"
                        >
                            <component :is="item.icon" />
                            <span class="truncate">{{ item.label }}</span>
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
