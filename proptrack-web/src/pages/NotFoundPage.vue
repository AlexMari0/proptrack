<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="min-h-dvh bg-[#eaece7] flex flex-col items-center justify-center px-6 relative overflow-hidden">
    <!-- Subtle warm ambient glow -->
    <div class="absolute top-[-20%] left-[-10%] w-[500px] h-[500px] rounded-full bg-amber-soft/30 blur-[130px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[400px] h-[400px] rounded-full bg-[#f8f6f2]/50 blur-[120px] pointer-events-none"></div>

    <div class="relative z-10 text-center max-w-md">
      <!-- Logo -->
      <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-g900 to-g700 items-center justify-center shadow-md mb-8">
        <span class="text-[#f8f6f2] font-extrabold text-2xl">P</span>
      </div>

      <!-- Error code -->
      <p class="text-8xl font-extrabold text-g200 leading-none mb-4 tabular-nums" style="color: var(--g200);">404</p>

      <!-- Heading — sentence case, no exclamation mark -->
      <h1 class="text-2xl font-bold text-g900 mb-3 text-balance">Page not found</h1>

      <p class="text-g500 text-sm leading-relaxed mb-8">
        The page you're looking for doesn't exist or has been moved. Check the URL or go back to the dashboard.
      </p>

      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <button
          @click="router.push('/dashboard')"
          class="btn-primary"
          style="padding: 10px 22px; justify-content: center;"
        >
          Go to dashboard
        </button>
        <button
          @click="router.back()"
          class="btn-ghost"
          style="padding: 10px 22px; justify-content: center;"
        >
          Go back
        </button>
      </div>
    </div>
  </div>
</template>
