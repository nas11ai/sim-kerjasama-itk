<script setup lang="ts">
import Checkbox from "@/Components/Checkbox.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post(route("login"), {
        onFinish: () => {
            form.reset("password");
        },
    });
};
</script>

<template>
    <div
        class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-b from-blue-100 to-white"
    >
        <div class="mb-6">
            <div class="flex items-center gap-4">
                <img src="/images/Logo-ITK.png" alt="Logo ITK" class="h-20 w-auto object-contain mx-auto mb-2" />
                <div>
                    <h1 class="text-2xl font-bold text-blue-700">SIM Kerja Sama ITK</h1>
                    <p class="text-gray-600 text-sm">Institut Teknologi Kalimantan</p>
                </div>
            </div>
        </div>

        <div
            class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8 border border-blue-100"
        >
            <Head title="Masuk ke Akun" />

            <h2 class="text-center text-xl font-semibold text-gray-800 mb-6">
                Masuk ke Akun Anda
            </h2>

            <form @submit.prevent="submit">
                <div class="mb-4">
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mb-4">
                    <InputLabel for="password" value="Kata Sandi" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center text-sm text-gray-600">
                        <Checkbox
                            name="remember"
                            v-model:checked="form.remember"
                        />
                        <span class="ml-2">Ingat saya</span>
                    </label>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-blue-600 hover:text-blue-800 text-sm"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>

                <PrimaryButton
                    class="w-full bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 justify-center flex items-center"
                >
                    Masuk
                </PrimaryButton>

                <p class="text-center text-sm text-gray-600 mt-4">
                    Belum punya akun?
                    <Link
                        :href="route('register')"
                        class="text-blue-600 hover:text-blue-800 font-medium"
                        >Daftar Sekarang</Link
                    >
                </p>
            </form>
        </div>
    </div>
</template>
