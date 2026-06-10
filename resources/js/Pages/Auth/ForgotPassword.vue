<script setup lang="ts">
import InputError from '@/Components/InputError.vue'
import { route } from 'ziggy-js'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, useForm } from '@inertiajs/vue3'

defineProps<{
    status?: string
}>()

const form = useForm({
    email: '',
})

const submit = () => {
    form.post(route('password.email'))
}
</script>

<template>
    <div
        class="min-h-screen relative flex flex-col items-center justify-center bg-linear-to-b from-white to-blue-50 z-10 p-10"
    >
        <div class="mb-6">
            <div class="flex items-center gap-4 drop-shadow-md">
                <img
                    src="/images/Logo-ITK.png"
                    alt="Logo ITK"
                    class="h-20 w-auto object-contain mx-auto mb-2"
                />
                <div>
                    <h1 class="text-2xl font-bold text-blue-600">SIM Kerja Sama ITK</h1>
                    <p class="text-gray-500 text-xs uppercase">Institut Teknologi Kalimantan</p>
                </div>
            </div>
        </div>

        <div
            class="w-full max-w-md bg-white z-10 rounded-3xl shadow-lg px-8 py-8 justify-between flex flex-col gap-8 border border-blue-100"
        >
            <Head title="Forgot Password" />

            <div class="mb-4 text-sm text-gray-600">
                Lupa kata sandi? Tidak masalah. Cukup beri tahu kami alamat email Anda, dan kami
                akan mengirimkan tautan pengaturan ulang kata sandi melalui email yang memungkinkan
                Anda memilih kata sandi baru.
            </div>

            <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
                {{ status }}
            </div>

            <form @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email" />

                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="username"
                    />

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-4 flex items-center justify-end">
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Kirim Tautan Reset Kata Sandi
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
