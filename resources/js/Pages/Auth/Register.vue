<script setup lang="ts">
import InputError from "@/Components/InputError.vue";
import Button from "@/Components/ui/button/Button.vue";
import Input from "@/Components/ui/input/Input.vue";
import Label from "@/Components/ui/label/Label.vue";
import Select from "@/Components/ui/select/Select.vue";
import SelectContent from "@/Components/ui/select/SelectContent.vue";
import SelectGroup from "@/Components/ui/select/SelectGroup.vue";
import SelectItem from "@/Components/ui/select/SelectItem.vue";
import SelectLabel from "@/Components/ui/select/SelectLabel.vue";
import SelectTrigger from "@/Components/ui/select/SelectTrigger.vue";
import SelectValue from "@/Components/ui/select/SelectValue.vue";
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
        class="min-h-screen relative flex flex-col items-center justify-center bg-gradient-to-b from-white to-blue-50 z-10 p-10"
    >
        <div class="mb-6">
            <div class="flex items-center gap-4 drop-shadow-md">
                <img src="/images/Logo-ITK.png" alt="Logo ITK" class="h-20 w-auto object-contain mx-auto mb-2" />
                <div>
                    <h1 class="text-2xl font-bold text-blue-600">SIM Kerja Sama ITK</h1>
                    <p class="text-gray-500 text-xs uppercase">Institut Teknologi Kalimantan</p>
                </div>
            </div>
        </div>

        <div
            class="w-full max-w-md bg-white z-10 rounded-3xl shadow-lg px-8 py-8 justify-between flex flex-col gap-8 border border-blue-100"
        >
            <Head title="Daftar Akun" />

            <div class="flex justify-center items-center">
                <Label class="text-center text-2xl font-bold tracking-tight text-gray-800">
                    Daftarkan Akun Anda
                </Label>
            </div>


            <form @submit.prevent="submit" class="flex flex-col gap-8">
                <!-- Name -->
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-1.5">
                        <Label for="name">
                            Nama
                        </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="flex w-full"
                            required
                            autofocus
                            autocomplete="name"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <!-- Email -->
                    <div class="flex flex-col gap-1.5">
                        <Label for="email">
                            Email
                        </Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="flex w-full"
                            required
                            autocomplete="email"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div class="flex flex-col gap-1.5">
                        <Label for="password">
                            Kata Sandi
                        </Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="flex w-full"
                            required
                            autocomplete="new-password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="flex flex-col gap-1.5">
                        <Label for="password_confirmation">
                            Konfirmasi Kata Sandi
                        </Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="flex w-full"
                            required
                            autocomplete="new-password"
                        />
                        <InputError
                            :message="form.errors.password_confirmation"
                        />
                    </div>

                    <!-- Role (select) -->
                    <div class="flex flex-col gap-1.5">
                        <Label for="role">
                            Mendaftar Sebagai
                        </Label>
                        <Select v-model="form.role" id="role">
                            <SelectTrigger class="focus:border-indigo-500 focus:ring-indigo-500">
                                <SelectValue placeholder="Pilih Role" />
                            </SelectTrigger>
                            <SelectContent class="max-h-[360px]">
                                <SelectGroup>
                                    <SelectLabel>Role yang Tersedia</SelectLabel>
                                    <SelectItem v-for="role in props.roles"
                                        :key="role.id"
                                        :value="String(role.id)"
                                    >
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.role" />
                    </div>

                    <!-- Prodi (select) -->
                    <div class="flex flex-col gap-1.5">
                        <Label for="program_study">
                            Program Studi
                        </Label>
                        <Select v-model="form.study_program" id="program_study">
                            <SelectTrigger class="focus:border-indigo-500 focus:ring-indigo-500">
                                <SelectValue placeholder="Pilih Program Studi" />
                            </SelectTrigger>
                            <SelectContent class="max-h-[360px]">
                                <SelectLabel>Program Studi yang Tersedia</SelectLabel>
                                <SelectItem v-for="program in props.studyPrograms"
                                    :key="program.id"
                                    :value="program.id"
                                >
                                    {{ program.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.study_program" />
                    </div>
                    <!-- Button -->
                </div>
                <div class="flex flex-col items-center gap-2 justify-end">
                    <Button class="w-full bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 justify-center flex items-center" :disabled="form.processing">
                        Daftar Sekarang
                    </Button>
                    <p class="text-center text-sm text-gray-600 mt-1">
                        Telah memiliki akun?
                        <Link
                            :href="route('login')"
                            class="text-blue-600 hover:text-blue-800 font-medium"
                        >
                            Masuk Sekarang
                        </Link>
                    </p>
                </div>
            </form>
        </div>
        <img class="absolute right-0 top-0 h-36 z-0 rotate-180 opacity-50" src="images/Gear_Blue.webp" alt="Gambar Profil Pengajar dari Program Pascasarjana ITK">
        <img class="absolute left-0 bottom-0 h-72 z-0 opacity-100 transform scale-x-[-1]" src="images/Yellow_Wave.webp" alt="Gambar Profil Pengajar dari Program Pascasarjana ITK">
    </div>
</template>
