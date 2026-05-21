<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import NavbarNotificationBell from '@/components/layout/NavbarNotificationBell.vue'

const { authStore, logout, fetchProfile, loading } = useAuth()

onMounted(async () => {
  // Freshly fetch the profile to ensure latest roles and info
  try {
    await fetchProfile()
  } catch (error) {
    console.error('Failed to load profile on mount:', error)
  }
})
</script>

<template>
  <div class="min-h-screen bg-[#070b13] relative overflow-hidden flex flex-col justify-between">
    <!-- Radiant background elements -->
    <div class="absolute top-[-20%] left-[-10%] w-[500px] h-[500px] rounded-full bg-indigo-500/10 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[500px] h-[500px] rounded-full bg-purple-500/10 blur-[120px] pointer-events-none"></div>

    <!-- Header Navigation -->
    <header class="border-b border-slate-800/60 bg-slate-900/40 backdrop-blur-md sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <!-- Logo -->
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
            <span class="text-white font-extrabold text-xl">P</span>
          </div>
          <div>
            <h1 class="text-lg font-bold tracking-tight text-white">PropTrack</h1>
            <p class="text-xs text-slate-400 font-medium">Enterprise Management</p>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <!-- Notification Bell -->
          <NavbarNotificationBell />

          <div class="hidden sm:flex flex-col items-end">
            <span class="text-sm font-semibold text-slate-200">{{ authStore.user?.name || 'Loading...' }}</span>
            <span class="text-xs text-slate-400 capitalize">{{ authStore.user?.roles?.[0] || 'User' }}</span>
          </div>
          
          <button 
            @click="logout"
            :disabled="loading"
            class="px-4 py-2 text-xs font-semibold text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/50 rounded-lg transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 disabled:pointer-events-none flex items-center gap-2"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
            </svg>
            Logout
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-6 py-12 flex-1 w-full flex flex-col justify-center">
      <div v-if="loading && !authStore.user" class="flex flex-col items-center justify-center py-20 gap-4">
        <!-- Spinner -->
        <div class="w-12 h-12 rounded-full border-4 border-slate-800 border-t-indigo-500 animate-spin"></div>
        <p class="text-slate-400 font-medium text-sm animate-pulse">Loading dashboard session...</p>
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Sidebar / User Info card -->
        <div class="lg:col-span-1">
          <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl relative overflow-hidden group hover:border-slate-700/60 transition-all duration-300">
            <!-- Decorative light shine -->
            <div class="absolute -top-10 -right-10 w-32 h-32 rounded-full bg-indigo-500/5 blur-xl group-hover:bg-indigo-500/10 transition-all duration-500"></div>

            <div class="flex flex-col items-center text-center">
              <!-- Avatar placeholder -->
              <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-indigo-500/20 to-purple-500/20 border border-indigo-500/30 flex items-center justify-center mb-4 relative shadow-inner">
                <span class="text-3xl font-extrabold text-indigo-400">{{ authStore.user?.name?.charAt(0).toUpperCase() || 'U' }}</span>
                <div class="absolute bottom-0 right-0 w-6 h-6 bg-emerald-500 border-4 border-slate-900 rounded-full" title="Online"></div>
              </div>

              <h2 class="text-xl font-bold text-white mb-1">{{ authStore.user?.name }}</h2>
              <p class="text-sm text-slate-400 mb-4">{{ authStore.user?.email }}</p>

              <!-- Role badges -->
              <div class="flex flex-wrap gap-1.5 justify-center mb-6">
                <span 
                  v-for="role in authStore.user?.roles" 
                  :key="role"
                  class="px-3 py-1 text-xs font-semibold uppercase tracking-wider rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"
                >
                  {{ role }}
                </span>
              </div>

              <!-- Quick account details list -->
              <div class="w-full border-t border-slate-800/60 pt-4 space-y-3">
                <div class="flex items-center justify-between text-xs font-medium">
                  <span class="text-slate-400">Account Type</span>
                  <span class="text-slate-200 capitalize">{{ authStore.user?.roles?.[0] || 'Standard' }}</span>
                </div>
                <div class="flex items-center justify-between text-xs font-medium">
                  <span class="text-slate-400">Security Status</span>
                  <span class="text-emerald-400 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                    </svg>
                    Sanctum Verified
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Welcome & Phase 1.2 success message -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Main Welcome banner -->
          <div class="bg-gradient-to-r from-slate-900 via-indigo-950/20 to-slate-900 border border-slate-800/80 rounded-2xl p-8 relative overflow-hidden group hover:border-slate-700/60 transition-all duration-300">
            <!-- Decorative backdrop shine -->
            <div class="absolute top-0 right-0 w-64 h-64 rounded-full bg-gradient-to-bl from-indigo-500/10 to-purple-500/5 blur-3xl pointer-events-none"></div>

            <div class="relative z-10 max-w-lg">
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-4 animate-pulse">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Phase 1.2 Authentication Success
              </span>
              <h2 class="text-3xl font-extrabold text-white leading-tight mb-3">
                Welcome back, <br class="sm:hidden"><span class="bg-gradient-to-r from-indigo-400 via-purple-400 to-indigo-300 bg-clip-text text-transparent">{{ authStore.user?.name }}</span>!
              </h2>
              <p class="text-slate-300 text-sm leading-relaxed mb-6">
                You have successfully registered, logged in, and established a secure, token-protected session. The API authentication middleware is fully operational.
              </p>
              <div class="flex flex-wrap gap-4">
                <a 
                  href="#check-api" 
                  class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-indigo-600/20 transition-all duration-200 hover:-translate-y-0.5"
                >
                  Explore Features
                </a>
                <button 
                  @click="fetchProfile"
                  class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 font-semibold text-xs rounded-xl transition-all duration-200 hover:-translate-y-0.5 flex items-center gap-2"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3.5" :class="{ 'animate-spin': loading }">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                  </svg>
                  Sync Profile
                </button>
              </div>
            </div>
          </div>

          <!-- Feature Checklist Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Token card -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl relative overflow-hidden group hover:border-slate-700/60 transition-all duration-300 flex flex-col justify-between">
              <div>
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center mb-4 text-indigo-400">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                  </svg>
                </div>
                <h3 class="text-sm font-bold text-white mb-2">Bearer Token Storage</h3>
                <p class="text-slate-400 text-xs leading-relaxed mb-4">
                  The Sanctum authentication token is securely persisted in your browser's <code class="px-1 py-0.5 rounded bg-slate-800 text-indigo-400 font-mono text-[10px]">localStorage</code> and dynamic state.
                </p>
              </div>
              <div class="text-[10px] font-mono text-slate-500 bg-slate-950/40 p-2.5 rounded-lg border border-slate-800/60 overflow-x-auto truncate">
                Bearer {{ authStore.token }}
              </div>
            </div>

            <!-- Next Phases card -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl relative overflow-hidden group hover:border-slate-700/60 transition-all duration-300 flex flex-col justify-between">
              <div>
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center mb-4 text-purple-400">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5" />
                  </svg>
                </div>
                <h3 class="text-sm font-bold text-white mb-2">Next Phase: Property Domain</h3>
                <p class="text-slate-400 text-xs leading-relaxed mb-4">
                  Now that Phase 1.2 is completed, we will soon implement Phase 2.1: Property Management featuring image galleries and Leaflet interactive map integrations.
                </p>
              </div>
              <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-purple-400 hover:text-purple-300 transition-colors">
                Ready for Phase 2.1
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
              </span>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 border-t border-slate-800/40 text-center text-xs text-slate-500 bg-[#070b13]">
      <p>&copy; 2026 PropTrack. Developed using Laravel 12 & Vue 3 SPA Decoupled Architecture.</p>
    </footer>
  </div>
</template>
