<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useProperty } from '@/composables/useProperty'
import { useTenant } from '@/composables/useTenant'
import { useContract } from '@/composables/useContract'
import { useInvoice } from '@/composables/useInvoice'
import { useTicket } from '@/composables/useTicket'
import { useReport } from '@/composables/useReport'
import NavbarNotificationBell from '@/components/layout/NavbarNotificationBell.vue'

const { authStore, logout, fetchProfile, loading: authLoading } = useAuth()
const router = useRouter()

const { fetchProperties, properties } = useProperty()
const { fetchTenants, tenants } = useTenant()
const { fetchContracts, contracts } = useContract()
const { fetchInvoices, invoices } = useInvoice()
const { fetchTickets, tickets } = useTicket()
const { fetchSummary, reportData } = useReport()

const dashboardLoading = ref(true)

// Helper to format IDR currency
const formatIDR = (value: number) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value)
}

// Check roles
const isOwnerOrAdmin = computed(() => {
  return ['owner', 'admin'].some(role => authStore.user?.roles?.includes(role))
})

const isAgent = computed(() => {
  return authStore.user?.roles?.includes('agent')
})

const isTenant = computed(() => {
  return authStore.user?.roles?.includes('tenant')
})

onMounted(async () => {
  try {
    // Refresh user profile first
    await fetchProfile()

    // Dynamically fetch stats based on user roles to avoid 403 Forbidden responses
    const fetches = []
    
    if (isOwnerOrAdmin.value) {
      fetches.push(
        fetchProperties(),
        fetchTenants(),
        fetchContracts(),
        fetchInvoices(),
        fetchTickets(),
        fetchSummary(new Date().getFullYear())
      )
    } else if (isAgent.value) {
      fetches.push(
        fetchProperties(),
        fetchTickets()
      )
    } else if (isTenant.value) {
      fetches.push(
        fetchInvoices(),
        fetchTickets()
      )
    }

    await Promise.allSettled(fetches)
  } catch (error) {
    console.error('Failed to load dashboard statistics:', error)
  } finally {
    dashboardLoading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-[#070b13] text-slate-100 relative overflow-hidden flex flex-col justify-between">
    <!-- Radiant background glows -->
    <div class="absolute top-[-10%] left-[-10%] w-[600px] h-[600px] rounded-full bg-indigo-600/10 blur-[130px] pointer-events-none"></div>
    <div class="absolute bottom-[-15%] right-[-5%] w-[600px] h-[600px] rounded-full bg-purple-600/10 blur-[130px] pointer-events-none"></div>

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
            <p class="text-xs text-slate-400 font-medium">
              <span v-if="isOwnerOrAdmin">Owner Hub</span>
              <span v-else-if="isAgent">Agent Console</span>
              <span v-else-if="isTenant">Tenant Space</span>
              <span v-else>Enterprise Management</span>
            </p>
          </div>
        </div>

        <!-- Role-Specific Navigation Links in Header -->
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-300">
          <template v-if="isOwnerOrAdmin">
            <RouterLink to="/properties" class="hover:text-white transition-colors">Properties</RouterLink>
            <RouterLink to="/tenants" class="hover:text-white transition-colors">Tenants</RouterLink>
            <RouterLink to="/contracts" class="hover:text-white transition-colors">Contracts</RouterLink>
            <RouterLink to="/invoices" class="hover:text-white transition-colors">Invoices</RouterLink>
            <RouterLink to="/reports/financial" class="hover:text-white transition-colors">Financials</RouterLink>
            <RouterLink to="/tickets" class="hover:text-white transition-colors">Tickets</RouterLink>
          </template>
          <template v-else-if="isAgent">
            <RouterLink to="/tickets" class="hover:text-white transition-colors">Tickets</RouterLink>
            <RouterLink to="/properties" class="hover:text-white transition-colors">Properties</RouterLink>
            <RouterLink to="/tenants" class="hover:text-white transition-colors">Tenants</RouterLink>
          </template>
          <template v-else-if="isTenant">
            <RouterLink to="/invoices" class="hover:text-white transition-colors">My Invoices</RouterLink>
            <RouterLink to="/tickets" class="hover:text-white transition-colors">My Tickets</RouterLink>
          </template>
        </nav>

        <div class="flex items-center gap-4">
          <!-- Notification Bell -->
          <NavbarNotificationBell />

          <div class="hidden sm:flex flex-col items-end">
            <span class="text-sm font-semibold text-slate-200">{{ authStore.user?.name || 'Loading...' }}</span>
            <span class="text-xs text-slate-400 capitalize">{{ authStore.user?.roles?.[0] || 'User' }}</span>
          </div>
          
          <button 
            @click="logout"
            :disabled="authLoading"
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
    <main class="max-w-7xl mx-auto px-6 py-8 flex-1 w-full">
      
      <!-- Loading spinner state -->
      <div v-if="dashboardLoading" class="flex flex-col items-center justify-center py-24 gap-4">
        <div class="w-12 h-12 rounded-full border-4 border-slate-800 border-t-indigo-500 animate-spin"></div>
        <p class="text-slate-400 font-medium text-sm animate-pulse">Initializing dashboard metrics...</p>
      </div>

      <div v-else class="space-y-8">
        
        <!-- Welcome Hero Banner -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950/15 to-slate-900 border border-slate-800/80 rounded-2xl p-8 relative overflow-hidden group hover:border-slate-700/50 transition-all duration-300">
          <div class="absolute top-0 right-0 w-80 h-80 rounded-full bg-gradient-to-bl from-indigo-500/10 to-purple-500/5 blur-3xl pointer-events-none"></div>
          
          <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="max-w-xl">
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 mb-4 uppercase tracking-wider">
                System Active
              </span>
              <h2 class="text-3xl font-extrabold text-white leading-tight mb-2">
                Welcome back, <span class="bg-gradient-to-r from-indigo-400 via-purple-400 to-indigo-300 bg-clip-text text-transparent">{{ authStore.user?.name }}</span>!
              </h2>
              <p class="text-slate-300 text-sm leading-relaxed">
                <span v-if="isOwnerOrAdmin">Access and manage properties, tenants, billing contracts, and platform financials in real-time.</span>
                <span v-else-if="isAgent">Oversee tenant complaints, resolve issues, and communicate progress instantly.</span>
                <span v-else-if="isTenant">Review your current active lease, process rent payments, or file complaint tickets.</span>
              </p>
            </div>
            
            <div class="flex flex-wrap gap-3">
              <button 
                v-if="isOwnerOrAdmin"
                @click="router.push('/properties/new')" 
                class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-indigo-600/20 transition-all duration-200 hover:-translate-y-0.5 flex items-center gap-1.5"
              >
                + Add Property
              </button>
              <button 
                v-if="isOwnerOrAdmin"
                @click="router.push('/tenants/new')" 
                class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 font-semibold text-xs rounded-xl transition-all duration-200 hover:-translate-y-0.5"
              >
                Register Tenant
              </button>
              <button 
                v-if="isTenant"
                @click="router.push('/tickets/create')" 
                class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-indigo-600/20 transition-all duration-200 hover:-translate-y-0.5"
              >
                File Complaint Ticket
              </button>
            </div>
          </div>
        </div>

        <!-- ─── METRICS CARDS GRID ─── -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          
          <!-- Owner/Admin Metrics -->
          <template v-if="isOwnerOrAdmin">
            <!-- Properties Card -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-indigo-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Properties</span>
                <span class="text-3xl font-extrabold text-white">{{ properties.length }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l8.25-6.75L18.75 9M18.75 9v12M12 9h.008v.008H12V9ZM12 12h.008v.008H12V12ZM12 15h.008v.008H12V15ZM9 12h.008v.008H9V12ZM9 15h.008v.008H9V15ZM15 12h.008v.008H15V12ZM15 15h.008v.008H15V15Z" />
                </svg>
              </div>
            </div>

            <!-- Tenants Card -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-emerald-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Active Tenants</span>
                <span class="text-3xl font-extrabold text-white">{{ tenants.length }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
              </div>
            </div>

            <!-- Active Leases -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-purple-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Active Contracts</span>
                <span class="text-3xl font-extrabold text-white">{{ contracts.filter(c => c.status === 'active').length }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
              </div>
            </div>

            <!-- Collected Revenue -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-amber-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Total Revenue</span>
                <span class="text-xl font-extrabold text-white truncate max-w-[170px] block">
                  {{ reportData ? formatIDR(reportData.total_collected) : 'Rp 0' }}
                </span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h.007m-.007 3h.007m-.007 3h.007m-.007 3h.007m-.007 3h.007m-.007 3h.007m15 0h.008v.008h-.008v-.008Zm0-3h.008v.008h-.008v-.008Zm0-3h.008v.008h-.008v-.008Zm0-3h.008v.008h-.008v-.008Zm0-3h.008v.008h-.008v-.008Zm0-3h.008v.008h-.008v-.008Zm-9 13.5h.008v.008H10.5v-.008Zm0-3h.008v.008H10.5v-.008Zm0-3h.008v.008H10.5v-.008Zm0-3h.008v.008H10.5v-.008Zm0-3h.008v.008H10.5v-.008Zm0-3h.008v.008H10.5v-.008Z" />
                </svg>
              </div>
            </div>
          </template>

          <!-- Support Agent Metrics -->
          <template v-else-if="isAgent">
            <!-- Open Tickets -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-red-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Open Tickets</span>
                <span class="text-3xl font-extrabold text-white">{{ tickets.filter(t => t.status === 'open').length }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
              </div>
            </div>

            <!-- In Progress -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-indigo-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">In Progress</span>
                <span class="text-3xl font-extrabold text-white">{{ tickets.filter(t => t.status === 'in_progress').length }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
              </div>
            </div>

            <!-- Assigned to Me -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-emerald-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">My Assignments</span>
                <span class="text-3xl font-extrabold text-white">
                  {{ tickets.filter(t => t.assigned_to?.id === authStore.user?.id).length }}
                </span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                </svg>
              </div>
            </div>

            <!-- Total Tickets Managed -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-purple-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Total Queue</span>
                <span class="text-3xl font-extrabold text-white">{{ tickets.length }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V6.375c0-.621-.504-1.125-1.125-1.125h-9.75ZM3.375 11.47c-.621 0-1.125.504-1.125 1.125v3.026c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125v-3.026c0-.621-.504-1.125-1.125-1.125h-9.75Z" />
                </svg>
              </div>
            </div>
          </template>

          <!-- Tenant User Metrics -->
          <template v-else-if="isTenant">
            <!-- Unpaid Invoices count -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-red-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Unpaid Invoices</span>
                <span class="text-3xl font-extrabold text-white">{{ invoices.filter(i => i.status === 'unpaid').length }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
              </div>
            </div>

            <!-- Total Invoices -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-indigo-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Total Invoices</span>
                <span class="text-3xl font-extrabold text-white">{{ invoices.length }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
              </div>
            </div>

            <!-- Submitted Tickets -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-purple-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Active Complaints</span>
                <span class="text-3xl font-extrabold text-white">
                  {{ tickets.filter(t => t.status !== 'resolved' && t.status !== 'closed').length }}
                </span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
              </div>
            </div>

            <!-- Total Submitted Tickets -->
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-amber-500/30 transition-all duration-300 group flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Total Tickets</span>
                <span class="text-3xl font-extrabold text-white">{{ tickets.length }}</span>
              </div>
              <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V6.375c0-.621-.504-1.125-1.125-1.125h-9.75ZM3.375 11.47c-.621 0-1.125.504-1.125 1.125v3.026c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125v-3.026c0-.621-.504-1.125-1.125-1.125h-9.75Z" />
                </svg>
              </div>
            </div>
          </template>
        </section>

        <!-- ─── CONTROL HUB & CARDS GRID ─── -->
        <section class="space-y-6">
          <h3 class="text-xl font-bold text-white tracking-tight flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
            PropTrack Feature Management Control Hub
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- Owner/Admin Feature Cards -->
            <template v-if="isOwnerOrAdmin">
              <!-- Properties Card -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-indigo-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-indigo-400 transition-colors">Properties Portfolio</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Manage your listing database, types (Kos, Apartment, Ruko), status (Available, Occupied, Maintenance), and exact coordinates with map integrations.
                  </p>
                </div>
                <div class="flex gap-2">
                  <button @click="router.push('/properties')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    View Portfolio
                  </button>
                  <button @click="router.push('/properties/new')" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-[11px] rounded-lg shadow-md shadow-indigo-600/10 transition-all">
                    + Add New
                  </button>
                </div>
              </div>

              <!-- Tenants Card -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-emerald-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-emerald-400 transition-colors">Tenant Directory</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Register tenant records, record emergency contact details, and input 16-digit KTP id numbers with live field validations.
                  </p>
                </div>
                <div class="flex gap-2">
                  <button @click="router.push('/tenants')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    View Directory
                  </button>
                  <button @click="router.push('/tenants/new')" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-[11px] rounded-lg shadow-md shadow-emerald-600/10 transition-all">
                    + Add Tenant
                  </button>
                </div>
              </div>

              <!-- Rental Contracts -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-purple-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-purple-400 transition-colors">Lease Agreements</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Draft legal rental contracts linking properties to specific tenants. Features bilingual PDF exports, automatic signature blocks, and terminations.
                  </p>
                </div>
                <div class="flex gap-2">
                  <button @click="router.push('/contracts')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    Manage Leases
                  </button>
                  <button @click="router.push('/contracts/new')" class="px-3.5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-semibold text-[11px] rounded-lg shadow-md shadow-purple-600/10 transition-all">
                    + Draft Contract
                  </button>
                </div>
              </div>

              <!-- Invoices & Billing -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-blue-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-blue-400 transition-colors">Invoices & Billing</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Monitor monthly recurring rent invoices. Trigger payment notifications (email + WhatsApp) and manage status configurations.
                  </p>
                </div>
                <div>
                  <button @click="router.push('/invoices')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    Monitor Invoices
                  </button>
                </div>
              </div>

              <!-- Financial Reports -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-amber-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-amber-400 transition-colors">Financial Analysis</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Access visual dashboard reports tracking outstanding dues, aggregate collected revenue, and download Spatie PDF financial records.
                  </p>
                </div>
                <div>
                  <button @click="router.push('/reports/financial')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    Open Financials
                  </button>
                </div>
              </div>

              <!-- Complaint Tickets -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-red-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-red-400 transition-colors">Complaint Ticketing</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Track resident complaint queries, allocate support agents, review category priorities, and coordinate repairs in visual chat boards.
                  </p>
                </div>
                <div>
                  <button @click="router.push('/tickets')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    Open Ticket Boards
                  </button>
                </div>
              </div>
            </template>

            <!-- Agent Feature Cards -->
            <template v-else-if="isAgent">
              <!-- Manage Tickets Card -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-indigo-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-indigo-400 transition-colors">Ticket Resolution Center</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Access and manage the resident complaint ticket board. Claim issues, update progress status (Open -> In Progress -> Resolved), and discuss details in thread chats.
                  </p>
                </div>
                <div>
                  <button @click="router.push('/tickets')" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-[11px] rounded-lg shadow-md shadow-indigo-600/10 transition-all">
                    Manage Tickets
                  </button>
                </div>
              </div>

              <!-- Browse Properties -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-emerald-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-emerald-400 transition-colors">Properties Directory</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Browse the listed property portfolio, mapping, coordinates, address details, and photos to support resident queries.
                  </p>
                </div>
                <div>
                  <button @click="router.push('/properties')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    Browse Properties
                  </button>
                </div>
              </div>

              <!-- Tenants details -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-purple-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-purple-400 transition-colors">Tenants Database</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    View active resident emergency contact information, active lease mapping, and support correspondence details.
                  </p>
                </div>
                <div>
                  <button @click="router.push('/tenants')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    View Residents List
                  </button>
                </div>
              </div>
            </template>

            <!-- Tenant Feature Cards -->
            <template v-else-if="isTenant">
              <!-- Active lease agreements -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-indigo-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-indigo-400 transition-colors">My Rental Lease</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Review your active rent lease period, contract terms, deposit information, billing date cycles, and download your official bilingual PDF agreement.
                  </p>
                </div>
                <div>
                  <!-- Tenant contracts automatically scope in list or detail -->
                  <button @click="router.push('/contracts')" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-[11px] rounded-lg shadow-md shadow-indigo-600/10 transition-all">
                    View Agreement
                  </button>
                </div>
              </div>

              <!-- Rent Invoices & Midtrans Snaps -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-emerald-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-emerald-400 transition-colors">Rent Invoices & Payments</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Review your monthly billing invoices. Process payments securely online via the Midtrans Snap gateway and access downloadable invoices.
                  </p>
                </div>
                <div>
                  <button @click="router.push('/invoices')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    Billing History
                  </button>
                </div>
              </div>

              <!-- Submit Ticketing complaints -->
              <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl hover:border-red-500/40 transition-all duration-300 flex flex-col justify-between group">
                <div>
                  <h4 class="text-base font-bold text-white mb-2 group-hover:text-red-400 transition-colors">Help & Repair Tickets</h4>
                  <p class="text-slate-400 text-xs leading-relaxed mb-6">
                    Encountered maintenance issues with air conditioning, plumbing, or structural problems? Submit a ticket and receive real-time updates from support agents.
                  </p>
                </div>
                <div class="flex gap-2">
                  <button @click="router.push('/tickets')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-200 hover:text-white font-semibold text-[11px] rounded-lg transition-all">
                    My Active Tickets
                  </button>
                  <button @click="router.push('/tickets/create')" class="px-3.5 py-2 bg-red-600 hover:bg-red-500 text-white font-semibold text-[11px] rounded-lg shadow-md shadow-red-600/10 transition-all">
                    File Complaint
                  </button>
                </div>
              </div>
            </template>

          </div>
        </section>

      </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 border-t border-slate-800/40 text-center text-xs text-slate-500 bg-[#070b13] mt-8">
      <p>&copy; 2026 PropTrack Enterprise. Decoupled Architecture powered by Laravel 12 & Vue 3 SPA.</p>
    </footer>
  </div>
</template>
