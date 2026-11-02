<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { OwnProfile } from '@/types/profile'

// W65 — foto di disk privat, dibaca lewat profile.photo.show; unggah langsung saat dipilih.
const props = defineProps<{ profile: OwnProfile }>()

const form = useForm<{ photo: File | null }>({ photo: null })

const initials = computed(() =>
    props.profile.name
        .split(/\s+/)
        .filter((word) => word !== '')
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('')
)

const upload = (event: Event) => {
    const input = event.target

    if (!(input instanceof HTMLInputElement)) return

    form.photo = input.files?.[0] ?? null

    if (form.photo === null) return

    form.post(route('profile.photo.update'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            input.value = ''
            form.reset()
        },
    })
}
</script>

<template>
    <SectionCard title="Foto Profil">
        <div class="flex items-center gap-4">
            <span
                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-secondary text-xl font-medium"
            >
                <img
                    v-if="profile.photoUrl"
                    :src="profile.photoUrl"
                    :alt="`Foto ${profile.name}`"
                    class="size-full object-cover"
                />
                <template v-else>{{ initials }}</template>
            </span>
            <div class="min-w-0">
                <strong class="block truncate">{{ profile.name }}</strong>
                <span class="text-sm text-muted-foreground">{{ profile.organization ?? '—' }}</span>
            </div>
        </div>
        <FormField
            id="profile-photo"
            label="Ganti Foto"
            hint="JPG/PNG maks 2MB."
            :error="form.errors.photo"
        >
            <template #default="{ describedBy }">
                <input
                    id="profile-photo"
                    type="file"
                    accept="image/jpeg,image/png"
                    class="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1.5 file:text-sm"
                    :disabled="form.processing"
                    :aria-describedby="describedBy"
                    @change="upload"
                />
            </template>
        </FormField>
    </SectionCard>
</template>
