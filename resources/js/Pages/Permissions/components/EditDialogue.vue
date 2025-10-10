<!-- components/EditDialog.vue -->
<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Permission } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
   open: boolean;
   permission: Permission | null;
}>();

const emit = defineEmits<{
   (e: 'close'): void;
}>();

const form = useForm({
   name: '',
});

// Trigger pertama kali ketika permission berubah
watch(
   () => props.permission,
   (permission) => {
      if (props.open && permission) {
         form.reset();
         form.clearErrors();
         form.name = permission.name;
      }
   },
   { immediate: true },
);

watch(
   () => props.open,
   (isOpen) => {
      if (isOpen && props.permission) {
         form.reset();
         form.clearErrors();
         form.name = props.permission.name;
      }
   },
);

function submit() {
   if (!props.permission) return;

   form.put(route('admin.permissions.update', props.permission.id), {
      onSuccess: () => {
         emit('close');
      },
   });
}
</script>

<template>
   <Dialog :open="props.open" @update:open="(v) => !v && emit('close')">
      <DialogContent class="transition-all duration-300 sm:max-w-md">
         <DialogHeader>
            <DialogTitle>Edit Permission</DialogTitle>
         </DialogHeader>

         <p class="text-muted-foreground text-sm">Fill in the form below to give the permission a new name.</p>

         <form @submit.prevent="submit" class="flex flex-col gap-4">
            <div class="grid gap-2">
               <Label for="name">Permission Name</Label>
               <Input
                  id="name"
                  type="text"
                  required
                  autofocus
                  :tabindex="1"
                  autocomplete="name"
                  v-model="form.name"
                  placeholder="Enter a new permission name"
               />
               <InputError :message="form.errors.name" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
               <Button type="button" class="cursor-pointer" variant="ghost" @click="emit('close')">Cancel</Button>
               <Button type="submit" class="cursor-pointer">Save</Button>
            </div>
         </form>
      </DialogContent>
   </Dialog>
</template>
