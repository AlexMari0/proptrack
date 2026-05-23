<script setup lang="ts">
defineProps<{
  size?: number | string
  showText?: boolean
  light?: boolean
  hideSlogan?: boolean
}>()
</script>

<template>
  <div class="app-logo" :class="{ 'app-logo--light': light }">
    <svg 
      class="logo-mark-svg" 
      viewBox="0 0 100 100" 
      fill="none" 
      xmlns="http://www.w3.org/2000/svg" 
      :style="{ width: size ? `${size}px` : '36px', height: size ? `${size}px` : '36px' }"
    >
      <!-- Hexagonal Outer Border -->
      <path 
        d="M50 12 L85 32 L85 68 L50 88" 
        :stroke="light ? '#FFFFFF' : '#0B1D3A'" 
        stroke-width="7" 
        stroke-linecap="round" 
        stroke-linejoin="round" 
      />
      <path 
        d="M50 88 L15 68 L15 32 L50 12" 
        :stroke="light ? '#FFFFFF' : '#0B1D3A'" 
        stroke-width="7" 
        stroke-linecap="round" 
        stroke-linejoin="round" 
      />
      
      <!-- Three Sloped Columns (Modern Buildings) -->
      <path d="M32 60 L32 40 L42 32 L42 60 Z" fill="#2563EB" />
      <path d="M46 60 L46 22 L56 14 L56 60 Z" fill="#2563EB" />
      <path d="M60 60 L60 32 L70 24 L70 60 Z" fill="#2563EB" />
      
      <!-- Active Connect Dot -->
      <circle 
        cx="50" 
        cy="88" 
        r="8" 
        fill="#2563EB" 
        :stroke="light ? '#0B1D3A' : '#ffffff'" 
        stroke-width="3" 
      />
    </svg>
    
    <div v-if="showText" class="logo-text-wrap">
      <span class="logo-title" :style="{ color: light ? '#FFFFFF' : '#0B1D3A' }">PropTrack</span>
      <span v-if="!hideSlogan" class="logo-slogan">MANAGE. TRACK. GROW.</span>
    </div>
  </div>
</template>

<style scoped>
.app-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.logo-mark-svg {
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.logo-mark-svg:hover {
  transform: scale(1.05);
}

.logo-text-wrap {
  display: flex;
  flex-direction: column;
  line-height: 1.1;
}

.logo-title {
  font-family: 'Outfit', var(--font-sans);
  font-weight: 800;
  font-size: 1.35rem;
  letter-spacing: -0.03em;
}

.logo-slogan {
  font-family: 'Outfit', var(--font-sans);
  font-weight: 700;
  font-size: 0.5rem;
  color: #2563EB;
  letter-spacing: 0.14em;
  margin-top: 1px;
}
</style>
