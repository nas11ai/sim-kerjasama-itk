<script setup lang="ts">
import { computed } from 'vue'
import { route } from 'ziggy-js'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import { ArrowLeft, Building2, Edit, GraduationCap, ListPlus, Pencil } from 'lucide-vue-next'

interface StudyProgram {
    id: number
    name: string
}

interface Faculty {
    id: number
    name: string
    study_programs: StudyProgram[]
    created_at: string | null
}

const props = defineProps<{ faculty: Faculty }>()

const studyPrograms = computed(() => props.faculty.study_programs ?? [])

const createdAt = computed(() => {
    if (props.faculty.created_at === null) {
        return '—'
    }

    return new Date(props.faculty.created_at).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
})
</script>

<template>
    <Head :title="faculty.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    class="p-0 mr-2"
                    size="sm"
                    @click="$inertia.visit(route('admin.faculties.index'))"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ faculty.name }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <Card>
                    <CardHeader class="flex flex-row items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <Building2 class="mt-1 h-5 w-5 text-muted-foreground" />
                            <div>
                                <CardTitle>{{ faculty.name }}</CardTitle>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Dibuat {{ createdAt }}
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="$inertia.visit(route('admin.faculties.edit', faculty.id))"
                        >
                            <Edit class="mr-2 h-4 w-4" />
                            Edit Fakultas
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2 text-sm">
                            <GraduationCap class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Program studi</span>
                            <Badge variant="secondary">{{ studyPrograms.length }}</Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-4">
                        <CardTitle class="text-base">Program Studi</CardTitle>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="$inertia.visit(route('admin.faculties.study-programs.create'))"
                        >
                            <ListPlus class="mr-2 h-4 w-4" />
                            Tambah Program Studi
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="studyPrograms.length === 0"
                            class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground"
                        >
                            Fakultas ini belum punya program studi.
                        </div>

                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead class="w-24 text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="studyProgram in studyPrograms"
                                    :key="studyProgram.id"
                                >
                                    <TableCell class="font-medium">
                                        {{ studyProgram.name }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Link
                                            :href="
                                                route(
                                                    'admin.faculties.study-programs.edit',
                                                    studyProgram.id
                                                )
                                            "
                                        >
                                            <Button variant="ghost" size="sm">
                                                <Pencil class="h-4 w-4" />
                                                <span class="sr-only">Edit</span>
                                            </Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
