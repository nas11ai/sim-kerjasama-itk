<script setup lang="ts">
import InputError from "@/Components/InputError.vue";
import Button from "@/Components/ui/button/Button.vue";
import Input from "@/Components/ui/input/Input.vue";
import Label from "@/Components/ui/label/Label.vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";

defineProps<{
    mustVerifyEmail?: Boolean;
    status?: String;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <form
        @submit.prevent="form.patch(route('profile.update'))"
        class="space-y-6"
    >
        <div class="flex flex-col gap-2">
            <Label for="name">Nama Saat Ini</Label>
            <Input
                id="name"
                type="text"
                v-model="form.name"
                required
                autofocus
                autocomplete="name"
            />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="name">Email Saat Ini</Label>
            <Input
                id="email"
                type="email"
                v-model="form.email"
                required
                autocomplete="username"
            />
            <InputError class="mt-1" :message="form.errors.email" />
        </div>

        <div v-if="mustVerifyEmail && user.email_verified_at === null">
            <p class="mt-2 text-sm text-muted-foreground">
                Alamat email Anda belum terverifikasi.
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                >
                    Klik di sini untuk mengirim ulang email verifikasi.
                </Link>
            </p>

            <div
                v-show="status === 'verification-link-sent'"
                class="mt-2 text-sm font-medium text-green-600"
            >
                Tautan verifikasi baru telah dikirim ke alamat email Anda.
            </div>
        </div>

        <div class="flex items-center gap-4">
            <Button type="submit" variant="default" :disabled="form.processing"
                >Simpan Profil</Button>
        </div>
    </form>
</template>
