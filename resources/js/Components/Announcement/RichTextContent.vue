<script setup lang="ts">
// fe-v2-design §4.7 K6 — satu-satunya pemakai v-html di v2. `html` hanya boleh berasal dari
// `contentHtml` backend, yaitu keluaran AnnouncementHtml::sanitize (HTMLPurifier).
defineProps<{ html: string }>()
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html -- disaring AnnouncementHtml::sanitize di server -->
    <div class="announcement-content text-sm" v-html="html" />
</template>
