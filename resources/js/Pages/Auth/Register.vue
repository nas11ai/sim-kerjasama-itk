<script setup lang="ts">
import GuestLayout from "@/Layouts/GuestLayout.vue";
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
    form.post(route("register"));
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

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

            <!-- Program Studi (select) -->
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
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900"
                >
                    Already registered?
                </Link>
                <PrimaryButton class="ms-4" :disabled="form.processing">
                    Register
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
