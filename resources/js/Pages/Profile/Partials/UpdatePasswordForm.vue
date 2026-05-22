<script setup lang="ts">
import InputError from "@/Components/InputError.vue";
import Button from "@/Components/ui/button/Button.vue";
import Input from "@/Components/ui/input/Input.vue";
import Label from "@/Components/ui/label/Label.vue";
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";

type InputElement = { focus: () => void };
const passwordInput = ref<InputElement | null>(null);
const currentPasswordInput = ref<InputElement | null>(null);

const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});
</script>

<template>
  <form
    class="space-y-6"
    @submit.prevent="form.put(route('password.update'))"
  >
    <div class="flex flex-col gap-2">
      <Label for="current_password">Kata Sandi Saat Ini</Label>
      <Input
        id="current_password"
        ref="currentPasswordInput"
        v-model="form.current_password"
        type="password"
        autocomplete="current-password"
      />
      <InputError
        :message="form.errors.current_password"
        class="mt-1"
      />
    </div>

    <div class="flex flex-col gap-2">
      <Label for="current_password">Kata Sandi Baru</Label>
      <Input
        id="password"
        ref="passwordInput"
        v-model="form.password"
        type="password"
        autocomplete="new-password"
      />
      <InputError
        :message="form.errors.password"
        class="mt-1"
      />
    </div>

    <div class="flex flex-col gap-2">
      <Label for="current_password">Konfirmasi Kata Sandi</Label>
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
      <Button
        type="submit"
        variant="default"
        :disabled="form.processing"
      >
        Simpan Kata Sandi Baru
      </Button>
    </div>
  </form>
</template>
