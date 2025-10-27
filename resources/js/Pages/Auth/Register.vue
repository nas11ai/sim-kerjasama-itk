<script setup lang="ts">
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

interface Role {
    id: number;
    name: string;
}

interface StudyProgram {
    id: number;
    name: string;
}

const props = defineProps<{
    roles: Role[];
    studyPrograms: StudyProgram[];
}>();

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    role: "",
    study_program: "",
});

const submit = () => {
    console.log('form data:', form.data());

    form.post(route("register"));
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
            class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8 border border-blue-1000"
        >
            <Head title="Daftar Akun" />

            <h2 class="text-center text-xl font-semibold text-gray-800 mb-6">
                Daftarkan Akun Anda
            </h2>


        <form @submit.prevent="form.post(route('register'))">
            <!-- Name -->
            <div>
                <InputLabel for="name" value="Name" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <!-- Email -->
            <div class="mt-4">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <!-- Password -->
            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <!-- Confirm Password -->
            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirm Password"
                />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                />
                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <!-- Role (select) -->
            <div class="mt-4">
                <InputLabel for="role" value="Register as" />
                <select
                    id="role"
                    v-model="form.role"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required
                >
                    <option value="">-- Select Role --</option>
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="role.id"
                    >
                        {{ role.name }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.role" />
            </div>

            <!-- Prodi (select) -->
            <div class="mt-4">
                <InputLabel for="program_study" value="Study Program" />
                <select
                    id="program_study"
                    v-model="form.study_program"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required
                >
                    <option value="">-- Select Study Program --</option>
                    <option
                        v-for="program in props.studyPrograms"
                        :key="program.id"
                        :value="program.id"
                    >
                        {{ program.name }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.study_program" />
            </div>

            <!-- Button -->
            <div class="mt-4 flex items-center justify-end">
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm underline text-blue-700 hover:text-blue-700 font-medium"
                >
                    Already registered?
                </Link>
                <PrimaryButton class="ms-4 bg-blue-700 hover:bg-blue-700 focus:ring-blue-700 justify-center flex items-center" :disabled="form.processing">
                    Register
                </PrimaryButton>
            </div>
        </form>
        </div>
    </div>
</template>
