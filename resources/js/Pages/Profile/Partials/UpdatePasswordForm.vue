<script setup lang="ts">
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import Button from "@/Components/ui/button/Button.vue";
import Input from "@/Components/ui/input/Input.vue";
import Label from "@/Components/ui/label/Label.vue";
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const updatePassword = () => {
    form.put(route("password.update"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            if (form.errors.password) {
                form.reset("password", "password_confirmation");
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset("current_password");
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <form @submit.prevent="updatePassword" class="space-y-6">
        <div class="flex flex-col gap-2">
            <Label for="current_password">Current Password</Label>
            <Input
                id="current_password"
                ref="currentPasswordInput"
                v-model="form.current_password"
                type="password"
                autocomplete="current-password"
            />
            <InputError :message="form.errors.current_password" class="mt-1" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="current_password">New Password</Label>
            <Input
                id="password"
                ref="passwordInput"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
            />
            <InputError :message="form.errors.password" class="mt-1" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="current_password">Confirm Password</Label>
            <Input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
            />
            <InputError
                :message="form.errors.password_confirmation"
                class="mt-1"
            />
        </div>

        <div class="flex items-center gap-4">
            <Button type="submit" variant="default" :disabled="form.processing"
                >Save New Password</Button
            >

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">
                    Saved.
                </p>
            </Transition>
        </div>
    </form>
</template>
