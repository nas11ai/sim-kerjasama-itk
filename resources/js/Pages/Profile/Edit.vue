<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import Button from "@/Components/ui/button/Button.vue";
import { ArrowLeft, UserPenIcon } from "lucide-vue-next";
import { computed, unref } from "vue";
import Card from "@/Components/ui/card/Card.vue";
import CardHeader from "@/Components/ui/card/CardHeader.vue";
import CardContent from "@/Components/ui/card/CardContent.vue";
import CardTitle from "@/Components/ui/card/CardTitle.vue";
import CardDescription from "@/Components/ui/card/CardDescription.vue";

const props = defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const backToDashboard = () => {
    const roles = unref(user.value.roles) as Array<{ name: string }>;

    const isAdmin = roles.some((role) =>
        ["Admin", "Super Admin"].includes(role.name),
    );

    router.visit(route(isAdmin ? "admin.dashboard" : "user.dashboard"));
};
</script>

<template>
  <Head title="Profile" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Button
          variant="ghost"
          size="sm"
          @click="backToDashboard"
        >
          <ArrowLeft class="h-4 w-4 mr-2" />
          Kembali
        </Button>
        <div class="flex items-center gap-2">
          <UserPenIcon class="h-6 w-6 text-black" />
          <h2
            class="text-xl font-semibold leading-tight text-gray-800"
          >
            Perbarui Profil Akun Anda
          </h2>
        </div>
      </div>
    </template>

    <div class="flex flex-col max-w-4xl mx-auto gap-6 pb-4">
      <Card>
        <CardHeader>
          <CardTitle class="text-xl">
            Informasi Profil
          </CardTitle>
          <CardDescription>
            Perbarui informasi profil dan alamat email akun Anda.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <UpdateProfileInformationForm
            :must-verify-email="mustVerifyEmail"
            :status="status"
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-xl">
            Perbarui Kata Sandi
          </CardTitle>
          <CardDescription>
            Pastikan akun Anda menggunakan kata sandi yang panjang
            dan acak untuk tetap aman.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <UpdatePasswordForm />
        </CardContent>
      </Card>
    </div>
  </AuthenticatedLayout>
</template>
