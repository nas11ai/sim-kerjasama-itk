<script setup lang="ts">
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { OrganizationUnitData } from '@/types/system-config'

const props = defineProps<{ unit: OrganizationUnitData | null }>()

const directUsers = (unit: OrganizationUnitData) => {
    const { directProfiles, directActiveUsers } = unit.census
    if (directProfiles === 0) {
        return '0'
    }

    return directActiveUsers === directProfiles
        ? `${directProfiles} — seluruhnya aktif`
        : `${directProfiles} — ${directActiveUsers} aktif`
}

const facts = computed(() =>
    props.unit === null
        ? []
        : [
              { label: 'Unit', value: props.unit.name },
              { label: 'Tipe', value: props.unit.typeLabel },
              { label: 'Status', value: props.unit.isActive ? 'Aktif' : 'Nonaktif' },
              { label: 'User di Unit Ini', value: directUsers(props.unit) },
              {
                  label: 'User di Seluruh Subtree',
                  value: String(props.unit.census.subtreeProfiles),
              },
          ]
)
</script>

<template>
    <div class="flex flex-col gap-4">
        <SectionCard title="Unit Terpilih">
            <EmptyState
                v-if="unit === null"
                title="Pilih unit di pohon untuk melihat rinciannya."
            />
            <template v-else>
                <InfoGrid :items="facts" />
                <slot name="actions" />
            </template>
        </SectionCard>
        <AppBanner tone="info">
            Nonaktifkan dan Hapus adalah dua operasi dengan ambang berbeda: nonaktif melihat user
            aktif, hapus melihat profil apa pun. Unit yang seluruh user-nya sudah nonaktif boleh
            dinonaktifkan, tapi belum boleh dihapus.
        </AppBanner>
    </div>
</template>
