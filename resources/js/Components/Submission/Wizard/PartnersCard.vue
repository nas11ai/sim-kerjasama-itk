<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { reactive } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import type { WizardPartnerRow } from '@/types/submission-wizard'

// BR-SM-21 — mitra yang dijanjikan saat mengajukan; satu institusi sekali per pengajuan.
const props = defineProps<{ submissionId: number; partners: WizardPartnerRow[] }>()

const page = usePage()
const blank = () => ({ institution_name: '', contact_person: '', description: '' })
const partner = reactive(blank())

const add = () =>
    router.post(
        route('user.submissions.partners.store', String(props.submissionId)),
        {
            institution_name: partner.institution_name,
            contact_person: partner.contact_person || null,
            description: partner.description || null,
        },
        { preserveScroll: true, onSuccess: () => Object.assign(partner, blank()) }
    )

const revoke = (row: WizardPartnerRow) =>
    router.delete(
        route('user.submissions.partners.destroy', [String(props.submissionId), String(row.id)]),
        { preserveScroll: true }
    )
</script>

<template>
    <SectionCard title="Mitra">
        <ul v-if="partners.length" class="flex flex-col divide-y rounded-md border">
            <li v-for="row in partners" :key="row.id" class="flex items-center gap-3 p-3 text-sm">
                <span class="min-w-0 flex-1">
                    {{ row.institutionName }}
                    <span v-if="row.contactPerson" class="text-muted-foreground">
                        · {{ row.contactPerson }}
                    </span>
                </span>
                <Button variant="ghost" size="sm" data-action="revoke-partner" @click="revoke(row)">
                    Cabut
                </Button>
            </li>
        </ul>
        <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="add">
            <FormField
                id="partner-institution"
                label="Institusi"
                required
                :error="page.props.errors?.institution_name"
            >
                <Input id="partner-institution" v-model="partner.institution_name" />
            </FormField>
            <FormField
                id="partner-contact"
                label="Narahubung"
                :error="page.props.errors?.contact_person"
            >
                <Input id="partner-contact" v-model="partner.contact_person" />
            </FormField>
            <FormField
                id="partner-description"
                label="Keterangan"
                class="sm:col-span-2"
                :error="page.props.errors?.description"
            >
                <Textarea id="partner-description" v-model="partner.description" rows="2" />
            </FormField>
            <Button type="submit" variant="outline" class="sm:col-span-2 sm:justify-self-start">
                <Plus class="size-4" /> Tambah Mitra
            </Button>
        </form>
    </SectionCard>
</template>
