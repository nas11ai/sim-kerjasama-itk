<script setup lang="ts">
import AlertContainer from '@/Components/AlertContainer.vue'
import Checkbox from '@/Components/BaseCheckbox.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import Button from '@/Components/ui/button/Button.vue'
import Input from '@/Components/ui/input/Input.vue'
import Label from '@/Components/ui/label/Label.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps<{
    canResetPassword?: boolean
    status?: string
}>()

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password')
        },
    })
}
</script>

<template>
    <div
        class="relative min-h-screen flex flex-col items-center justify-center bg-linear-to-b from-white to-blue-50 p-10"
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
            <Head title="Masuk ke Akun" />

            <div class="flex justify-center items-center">
                <Label class="text-center text-2xl font-bold tracking-tight text-gray-800">
                    Masuk ke Akun Anda
                </Label>
            </div>

            <form class="flex flex-col gap-2" @submit.prevent="submit">
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-1.5">
                        <Label for="email"> Email </Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="flex w-full"
                            required
                            autofocus
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="password"> Kata Sandi </Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="flex w-full"
                            required
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                </div>

                <div class="flex items-center justify-between mb-6">
                    <Label class="flex items-center text-sm text-gray-600 font-normal">
                        <Checkbox v-model:checked="form.remember" name="remember" />
                        <span class="ml-2">Ingat saya</span>
                    </Label>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-blue-600 hover:text-blue-800 text-sm"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>

                <Button
                    class="w-full bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 justify-center flex items-center"
                >
                    Masuk
                </Button>

                <p class="text-center text-sm text-gray-600 mt-1">
                    Belum punya akun?
                    <Link
                        :href="route('register')"
                        class="text-blue-600 hover:text-blue-800 font-medium"
                    >
                        Daftar Sekarang
                    </Link>
                </p>
            </form>
        </div>
        <img
            class="absolute left-6 top-0 h-36 z-0 rotate-180 opacity-50"
            src="images/Gear_Blue.webp"
            alt="Gambar Profil Pengajar dari Program Pascasarjana ITK"
        />
        <img
            class="absolute right-0 bottom-0 h-72 z-0 opacity-100"
            src="images/Gear_Yellow.webp"
            alt="Gambar Profil Pengajar dari Program Pascasarjana ITK"
        />
    </div>
    <AlertContainer />
</template>
