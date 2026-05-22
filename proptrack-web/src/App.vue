<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'

const route = useRoute()

// Routes that render inside the AppLayout (sidebar + topbar)
const useAppLayout = computed(() =>
  route.meta.requiresAuth === true
)
</script>

<template>
  <a href="#main-content" class="skip-to-content">Skip to content</a>
  <AppLayout v-if="useAppLayout">
    <RouterView />
  </AppLayout>
  <RouterView v-else />
</template>
