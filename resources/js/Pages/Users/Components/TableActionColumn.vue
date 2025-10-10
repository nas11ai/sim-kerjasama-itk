<script lang="ts" setup>
import { Button } from "@/Components/ui/button";
import { User } from "@/types";
import { Link } from "@inertiajs/vue3";
import { Row } from "@tanstack/vue-table";
import { Edit, Trash } from "lucide-vue-next";

const props = defineProps<{ row: Row<User>; canDelete: boolean }>();
const emit = defineEmits<{
    (e: "confirm-delete", userId: number): void;
}>();
</script>

<template>
    <div class="flex items-center gap-2">
        <Button
            :as="Link"
            :href="route('admin.users.edit', props.row.original.id)"
            variant="default"
            size="icon"
            class="cursor-pointer bg-yellow-400 hover:bg-yellow-400/80"
        >
            <Edit :size="16" class="text-black" />
        </Button>

        <Button
            as="button"
            variant="destructive"
            size="icon"
            class="cursor-pointer bg-red-500 hover:bg-red-500/80"
            @click="emit('confirm-delete', props.row.original.id)"
            :disabled="!props.canDelete"
        >
            <Trash :size="16" class="text-black" />
        </Button>
    </div>
</template>
