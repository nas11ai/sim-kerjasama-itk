<script setup lang="ts">
/**
 * BR-IAM-06, BR-IAM-12 — SHARED-01-03, registrasi lewat tautan undangan.
 *
 * Tidak ada isian NIDN dan tidak ada pemilih organisasi: keduanya sudah
 * ditetapkan token, dan menampilkannya sebagai isian mengundang pendaftar
 * mengira ia bisa memilih. Organisasi tujuan ditampilkan sebagai keterangan
 * supaya ia tahu sedang mendaftar ke mana.
 */
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Loader2 } from 'lucide-vue-next'

const props = defineProps<{
    token: string
    organization: string | null
}>()

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

function daftar() {
    form.post(route('register.invitation', props.token))
}
</script>

<template>
    <Head title="Registrasi Undangan" />

    <div class="flex min-h-screen items-center justify-center p-4">
        <Card class="w-full max-w-md">
            <CardHeader>
                <CardTitle>Registrasi Undangan</CardTitle>
                <p v-if="props.organization" class="text-muted-foreground text-sm">
                    Anda diundang bergabung sebagai mitra
                    <strong>{{ props.organization }}</strong
                    >. Akun langsung aktif tanpa verifikasi NIDN.
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-1">
                    <Label for="name">Nama</Label>
                    <Input id="name" v-model="form.name" autocomplete="name" />
                    <p v-if="form.errors.name" class="text-destructive text-xs">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-1">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" autocomplete="email" />
                    <p v-if="form.errors.email" class="text-destructive text-xs">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div class="space-y-1">
                    <Label for="password">Kata Sandi</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                    />
                    <p v-if="form.errors.password" class="text-destructive text-xs">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="space-y-1">
                    <Label for="password_confirmation">Ulangi Kata Sandi</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                    />
                </div>

                <Button class="w-full" :disabled="form.processing" @click="daftar">
                    <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                    Daftar
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
