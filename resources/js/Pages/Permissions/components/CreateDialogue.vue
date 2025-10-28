<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
   open: boolean;
}>();

const emit = defineEmits<{
   (e: 'close'): void;
}>();

const form = useForm({
   name: '',
});

function submit() {
   form.post(route('admin.permissions.store'), {
      onSuccess: () => {
         form.reset();
         emit('close');
      },
   });
}
</script>

<template>
   <Dialog :open="props.open" @update:open="(v) => !v && emit('close')">
      <DialogContent class="transition-all duration-250 sm:max-w-md">
         <DialogHeader>
            <DialogTitle>Add New Permission</DialogTitle>
         </DialogHeader>

         <p class="text-muted-foreground text-sm">Create a new permission to control user access in the system.</p>

         <form @submit.prevent="submit" class="flex flex-col gap-4">
            <div class="grid gap-2">
               <Label for="name">Permission Name</Label>
               <Input id="name" type="text" required autofocus :tabindex="1" autocomplete="name" v-model="form.name" placeholder="e.g., View Data" />
               <InputError :message="form.errors.name" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
               <Button type="button" class="cursor-pointer" variant="ghost" @click="emit('close')">Cancel</Button>
               <Button type="submit" class="cursor-pointer">Create</Button>
            </div>
         </form>
      </DialogContent>
   </Dialog>
</template>
