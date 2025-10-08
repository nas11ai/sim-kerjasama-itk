<!-- resources/js/Pages/UserManagements/Index.vue -->
<script setup lang="ts">
import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { User } from "@/types";
import { Head, Link } from "@inertiajs/vue3";
import { Plus } from "lucide-vue-next";
import UserTableList from "./Components/UserTableList.vue";

const props = defineProps<{
    users: User[];
}>();
</script>

<template>
    <Head title="User Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    User Management
                </h2>
                <Link :href="route('admin.users.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Create User
                    </Button>
                </Link>
            </div>
        </template>
        <div class="space-y-6">
            <UserTableList :users="users" />
        </div>

    </AuthenticatedLayout>
</template>
