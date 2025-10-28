<script lang="ts" setup>

import { EllipsisVertical } from 'lucide-vue-next';
import Button from './ui/button/Button.vue';
import DropdownMenu from './ui/dropdown-menu/DropdownMenu.vue';
import DropdownMenuTrigger from './ui/dropdown-menu/DropdownMenuTrigger.vue';
import DropdownMenuContent from './ui/dropdown-menu/DropdownMenuContent.vue';
import DropdownMenuItem from './ui/dropdown-menu/DropdownMenuItem.vue';

defineProps<{
   label: string;
   icon: any;
   href?: string;
   disabled?: boolean;
}>();

const emit = defineEmits<{
   (e: 'click'): void;
}>();
</script>

<template>
   <Button
      :as="href ? 'a' : 'button'"
      :href="href"
      @click="!href && emit('click')"
      variant="outline"
      size="default"
      class="hidden cursor-pointer items-center gap-2 bg-black text-white sm:flex"
      :disabled="disabled"
   >
      <component :is="icon" :size="4" />
      <span>{{ label }}</span>
   </Button>

   <div class="sm:hidden">
      <DropdownMenu>
         <DropdownMenuTrigger as-child>
            <EllipsisVertical :size="16" class="text-muted-foreground cursor-pointer" />
         </DropdownMenuTrigger>
         <DropdownMenuContent class="w-40" align="start">
            <DropdownMenuItem>
               <Button
                  :as="href ? 'a' : 'button'"
                  :href="href"
                  @click="!href && emit('click')"
                  variant="outline"
                  size="default"
                  class="hidden cursor-pointer items-center gap-2 bg-black text-white sm:flex"
               >
                  <component :is="icon" :size="4" />
                  <span>{{ label }}</span>
               </Button>
            </DropdownMenuItem>
         </DropdownMenuContent>
      </DropdownMenu>
   </div>
</template>
