<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed, reactive } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import type { WizardStudentRow } from '@/types/submission-wizard'

// BR-SM-20 — mahasiswa dicatat sebagai data, bukan akun; batasnya dari skema bila ada.
const props = defineProps<{
    submissionId: number
    students: WizardStudentRow[]
    limit: number | null
}>()

const page = usePage()
const student = reactive({ name: '', student_id: '', study_program: '' })
const full = computed(() => props.limit !== null && props.students.length >= props.limit)
const error = (key: string) => page.props.errors?.[key]

const add = () =>
    router.post(
        route('user.submissions.student-members.store', String(props.submissionId)),
        { ...student, study_program: student.study_program || null },
        {
            preserveScroll: true,
            onSuccess: () =>
                Object.assign(student, { name: '', student_id: '', study_program: '' }),
        }
    )

const remove = (row: WizardStudentRow) =>
    router.delete(
        route('user.submissions.student-members.destroy', [
            String(props.submissionId),
            String(row.id),
        ]),
        { preserveScroll: true }
    )
</script>

<template>
    <SectionCard
        title="Anggota Mahasiswa"
        :description="limit === null ? undefined : `${students.length} dari ${limit} mahasiswa.`"
    >
        <ul v-if="students.length" class="flex flex-col divide-y rounded-md border">
            <li v-for="row in students" :key="row.id" class="flex items-center gap-3 p-3 text-sm">
                <span class="min-w-0 flex-1">
                    {{ row.name }} · {{ row.studentId }}
                    <span v-if="row.studyProgram" class="text-muted-foreground">
                        · {{ row.studyProgram }}
                    </span>
                </span>
                <Button variant="ghost" size="sm" data-action="remove-student" @click="remove(row)">
                    Hapus
                </Button>
            </li>
        </ul>
        <form v-if="!full" class="grid gap-3 sm:grid-cols-3" @submit.prevent="add">
            <FormField id="student-name" label="Nama" required :error="error('name')">
                <Input id="student-name" v-model="student.name" />
            </FormField>
            <FormField id="student-nim" label="NIM" required :error="error('student_id')">
                <Input id="student-nim" v-model="student.student_id" />
            </FormField>
            <FormField id="student-program" label="Program Studi" :error="error('study_program')">
                <Input id="student-program" v-model="student.study_program" />
            </FormField>
            <Button type="submit" variant="outline" class="sm:col-span-3 sm:justify-self-start">
                <Plus class="size-4" /> Tambah Mahasiswa
            </Button>
        </form>
        <p v-else class="text-xs text-muted-foreground">
            Jumlah mahasiswa sudah mencapai batas skema. Keluarkan salah satu sebelum menambah.
        </p>
    </SectionCard>
</template>
