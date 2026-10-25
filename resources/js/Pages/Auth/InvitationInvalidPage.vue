<script setup lang="ts">
// Frame: SHARED-01-04
// BR-IAM-06 — sebabnya sengaja tidak dirinci: kuota habis, kedaluwarsa, dicabut, atau
// organisasi nonaktif hanya berguna bagi Operator, sedangkan bagi penebak token ia
// memberi tahu seberapa dekat tebakannya. Pendaftar cukup tahu kepada siapa bertanya.
import { Head, Link } from '@inertiajs/vue3'
import { CircleAlert } from 'lucide-vue-next'

import { Button } from '@/Components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Components/ui/empty'
import AuthLayout from '@/Layouts/AuthLayout.vue'
</script>

<template>
    <Head title="Undangan Tidak Valid" />
    <AuthLayout>
        <Empty class="p-0 md:p-0">
            <EmptyHeader>
                <EmptyMedia variant="icon"><CircleAlert /></EmptyMedia>
                <EmptyTitle>Link undangan tidak valid</EmptyTitle>
                <EmptyDescription>
                    Link undangan sudah kedaluwarsa atau kuota penggunaannya habis. Hubungi Operator
                    LPPM untuk meminta undangan baru.
                </EmptyDescription>
            </EmptyHeader>
        </Empty>

        <Button as-child variant="outline" class="mt-6 w-full">
            <Link :href="route('login')">Kembali ke Halaman Login</Link>
        </Button>
        <p class="mt-4 text-center text-sm text-muted-foreground">Kontak: lppm@itk.ac.id</p>
    </AuthLayout>
</template>
