<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTicket } from '@/composables/useTicket'
import { useAuthStore } from '@/stores/auth'
import { useEcho } from '@/composables/useEcho'
import type { TicketStatus, TicketPriority } from '@/types/ticket'

const route = useRoute()
const router = useRouter()
const { selectedTicket, isLoading, isSubmitting, error, fetchTicket, updateTicketStatus, addComment } = useTicket()
const authStore = useAuthStore()

const { getEcho } = useEcho()
let activeTicketChannel: string | null = null

function subscribeToTicket(ticketId: string) {
  const echo = getEcho()
  if (echo) {
    if (activeTicketChannel) {
      echo.leave(`ticket.${activeTicketChannel}`)
    }
    activeTicketChannel = ticketId
    echo.private(`ticket.${ticketId}`)
      .listen('.TicketStatusUpdated', async () => {
        await fetchTicket(ticketId)
      })
  }
}

function unsubscribeFromTicket() {
  const echo = getEcho()
  if (echo && activeTicketChannel) {
    echo.leave(`ticket.${activeTicketChannel}`)
    activeTicketChannel = null
  }
}

watch(() => route.params.id, (newId) => {
  if (newId) {
    subscribeToTicket(newId as string)
  } else {
    unsubscribeFromTicket()
  }
}, { immediate: true })

const newComment = ref('')
const commentError = ref('')

const isAgentOrAdmin = computed(() =>
  authStore.user?.roles?.some((role) => ['agent', 'admin'].includes(role)) ?? false
)

const canComment = computed(() => {
  if (!selectedTicket.value) return false
  const roles = authStore.user?.roles ?? []
  if (roles.includes('admin') || roles.includes('agent')) return true
  if (roles.includes('owner')) {
    // Backend policy checks owner_id of property, we have it in selectedTicket.property
    return true // let backend policy handle the exact ownership check, but allow UI interaction
  }
  if (roles.includes('tenant')) {
    return selectedTicket.value.submitted_by.id === authStore.user?.id
  }
  return false
})

onMounted(async () => {
  await fetchTicket(route.params.id as string)
})

onUnmounted(() => {
  unsubscribeFromTicket()
})

// Console state
const consoleStatus = ref<TicketStatus>('open')
const assignToMe = ref(false)

// Sync console values when ticket loads
onMounted(() => {
  // Simple check since selectedTicket is a computed property
  const checkInterval = setInterval(() => {
    if (selectedTicket.value) {
      consoleStatus.value = selectedTicket.value.status
      assignToMe.value = selectedTicket.value.assigned_to?.id === authStore.user?.id
      clearInterval(checkInterval)
    }
  }, 100)
})

async function handleUpdateStatus() {
  if (!selectedTicket.value) return

  const payload = {
    status: consoleStatus.value,
    assigned_to_id: assignToMe.value ? authStore.user?.id : (selectedTicket.value.assigned_to?.id || null),
  }

  const success = await updateTicketStatus(selectedTicket.value.id, payload)
  if (success) {
    // Re-fetch to sync all relationship states
    await fetchTicket(selectedTicket.value.id)
  }
}

async function handleAddComment() {
  if (!selectedTicket.value || !newComment.value.trim()) return

  commentError.value = ''
  const success = await addComment(selectedTicket.value.id, newComment.value.trim())
  if (success) {
    newComment.value = ''
  } else {
    commentError.value = error.value || 'Gagal mengirim komentar.'
  }
}

function getRoleBadgeClass(role: string) {
  switch (role) {
    case 'admin':
    case 'agent':
      return 'role-badge role-badge--agent'
    case 'owner':
      return 'role-badge role-badge--owner'
    case 'tenant':
      return 'role-badge role-badge--tenant'
    default:
      return 'role-badge'
  }
}

function getPriorityBadgeClass(priority: TicketPriority) {
  switch (priority) {
    case 'high': return 'priority-badge priority-badge--high'
    case 'medium': return 'priority-badge priority-badge--medium'
    case 'low': return 'priority-badge priority-badge--low'
    default: return 'priority-badge'
  }
}

function getStatusBadgeClass(status: TicketStatus) {
  switch (status) {
    case 'open': return 'status-tag status-tag--open'
    case 'in_progress': return 'status-tag status-tag--progress'
    case 'resolved': return 'status-tag status-tag--resolved'
    case 'closed': return 'status-tag status-tag--closed'
    default: return 'status-tag'
  }
}
</script>

<template>
  <div class="page">
    <!-- Back Button -->
    <button class="back-btn" @click="router.push({ name: 'tickets' })">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
      </svg>
      Kembali ke Daftar Tiket
    </button>

    <div v-if="isLoading && !selectedTicket" class="loading-state">
      <span class="spinner" /> Memuat detail tiket keluhan...
    </div>

    <div v-if="error && !selectedTicket" class="alert alert--error">{{ error }}</div>

    <div v-if="selectedTicket" class="detail-grid">
      <!-- Left Column: Details & Console -->
      <div class="left-col">
        <!-- Ticket Information Card -->
        <div class="detail-card">
          <div class="detail-card__header">
            <span class="text-mono text-primary font-bold text-sm">{{ selectedTicket.ticket_number }}</span>
            <span :class="getStatusBadgeClass(selectedTicket.status)">{{ selectedTicket.status }}</span>
          </div>

          <h1 class="detail-card__title">{{ selectedTicket.title }}</h1>
          <p class="detail-card__description">{{ selectedTicket.description }}</p>

          <div class="detail-info-list">
            <div class="info-item">
              <span class="info-item__label">Kategori</span>
              <span class="category-badge">{{ selectedTicket.category }}</span>
            </div>

            <div class="info-item">
              <span class="info-item__label">Prioritas</span>
              <span :class="getPriorityBadgeClass(selectedTicket.priority)">{{ selectedTicket.priority }}</span>
            </div>

            <div class="info-item">
              <span class="info-item__label">Properti</span>
              <span class="info-item__value font-semibold">{{ selectedTicket.property.name }}</span>
            </div>

            <div class="info-item">
              <span class="info-item__label">Pelapor (Tenant)</span>
              <span class="info-item__value">{{ selectedTicket.submitted_by.name }}</span>
            </div>

            <div class="info-item">
              <span class="info-item__label">Ditugaskan Kepada</span>
              <span class="info-item__value">
                {{ selectedTicket.assigned_to ? selectedTicket.assigned_to.name : 'Belum Ditugaskan' }}
              </span>
            </div>

            <div class="info-item">
              <span class="info-item__label">Tanggal Dibuat</span>
              <span class="info-item__value text-sm text-muted">
                {{ new Date(selectedTicket.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Admin / Agent Control Console -->
        <div v-if="isAgentOrAdmin" class="console-card">
          <h2 class="console-card__title">Agent Console & Pengendali</h2>
          <p class="console-card__subtitle">Gunakan pengendali di bawah ini untuk memperbarui status penanganan keluhan dan penugasan.</p>
          
          <form @submit.prevent="handleUpdateStatus" class="console-form">
            <!-- Status Update -->
            <div class="field">
              <label class="field__label" for="console_status">Perbarui Status</label>
              <select id="console_status" v-model="consoleStatus" class="field__input">
                <option value="open">Open (Terbuka/Baru)</option>
                <option value="in_progress">In Progress (Sedang Ditangani)</option>
                <option value="resolved">Resolved (Telah Diselesaikan)</option>
                <option value="closed">Closed (Ditutup)</option>
              </select>
            </div>

            <!-- Self Assignment -->
            <div class="checkbox-field">
              <label class="checkbox-container">
                <input type="checkbox" v-model="assignToMe" />
                <span class="checkbox-checkmark"></span>
                <span class="checkbox-label">Tugaskan tiket keluhan ini ke saya</span>
              </label>
            </div>

            <button type="submit" class="btn btn--primary w-full justify-center" :disabled="isSubmitting">
              <span v-if="isSubmitting" class="spinner" />
              Simpan Perubahan
            </button>
          </form>
        </div>
      </div>

      <!-- Right Column: Chronological Discussion Thread -->
      <div class="right-col">
        <div class="thread-card">
          <h2 class="thread-card__title">Diskusi Keluhan</h2>
          <p class="thread-card__subtitle">Thread komunikasi tertulis mengenai penanganan keluhan ini.</p>

          <!-- Chat-like Thread Area -->
          <div class="comment-thread">
            <div v-if="!selectedTicket.comments || selectedTicket.comments.length === 0" class="empty-thread">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-1.074-.765 6 6 0 0 0 1.943-3.807C2.934 15.082 1 12.686 1 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
              </svg>
              <p>Belum ada komentar diskusi.</p>
              <span class="text-xs text-slate-500">Tulis tanggapan atau instruksi pertama Anda di bawah.</span>
            </div>

            <div v-else class="comments-list">
              <div 
                v-for="comment in selectedTicket.comments" 
                :key="comment.id" 
                :class="['comment-item', { 'comment-item--self': comment.user.id === authStore.user?.id }]"
              >
                <!-- Avatar -->
                <div class="comment-avatar">
                  {{ comment.user.name.charAt(0).toUpperCase() }}
                </div>

                <!-- Comment Content -->
                <div class="comment-bubble">
                  <div class="comment-bubble__meta">
                    <span class="comment-user-name">{{ comment.user.name }}</span>
                    <span :class="getRoleBadgeClass(comment.user.role)">{{ comment.user.role }}</span>
                  </div>

                  <p class="comment-bubble__text">{{ comment.content }}</p>

                  <span class="comment-time">
                    {{ new Date(comment.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- New Comment Input Area -->
          <div v-if="canComment" class="comment-input-area">
            <div v-if="commentError" class="alert alert--error text-xs mb-2">{{ commentError }}</div>
            
            <form @submit.prevent="handleAddComment" class="comment-form">
              <textarea 
                v-model="newComment"
                rows="2"
                placeholder="Ketik tanggapan atau perkembangan keluhan di sini..."
                class="field__input comment-textarea"
                :disabled="isSubmitting"
              ></textarea>
              
              <button type="submit" class="btn btn--primary" :disabled="isSubmitting || !newComment.trim()">
                <span v-if="isSubmitting" class="spinner" />
                Kirim Komentar
              </button>
            </form>
          </div>
          <div v-else class="comment-forbidden-notice">
            Anda tidak memiliki otorisasi untuk menambahkan komentar pada tiket keluhan ini.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 32px; max-width: 1280px; margin: 0 auto; }
.back-btn { display: inline-flex; align-items: center; gap: 6px; background: none; border: none; color: var(--color-text-muted); font-size: 0.875rem; cursor: pointer; padding: 0; margin-bottom: 24px; transition: color 0.2s; }
.back-btn:hover { color: var(--color-primary); }
.back-btn svg { width: 18px; height: 18px; }

.loading-state { display: flex; align-items: center; gap: 12px; color: var(--color-text-muted); padding: 64px 0; justify-content: center; }
.detail-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 28px; }
@media (max-width: 900px) { .detail-grid { grid-template-columns: 1fr; } }

/* Cards Styling */
.detail-card, .console-card, .thread-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 20px; padding: 28px; margin-bottom: 24px; }
.detail-card__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.detail-card__title { font-size: 1.5rem; font-weight: 700; color: var(--color-text); margin: 0 0 12px; line-height: 1.3; }
.detail-card__description { font-size: 0.95rem; color: var(--color-text); line-height: 1.6; margin: 0 0 24px; white-space: pre-wrap; background: var(--color-surface-alt); padding: 16px; border-radius: 12px; border: 1px solid var(--color-border); }

.detail-info-list { display: flex; flex-direction: column; gap: 14px; }
.info-item { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; border-bottom: 1px solid rgba(255, 255, 255, 0.04); padding-bottom: 10px; }
.info-item:last-child { border-bottom: none; padding-bottom: 0; }
.info-item__label { color: var(--color-text-muted); font-weight: 500; }
.info-item__value { color: var(--color-text); }
.font-semibold { font-weight: 600; }

/* Console Card */
.console-card__title, .thread-card__title { font-size: 1.2rem; font-weight: 700; color: var(--color-text); margin: 0 0 4px; }
.console-card__subtitle, .thread-card__subtitle { font-size: 0.82rem; color: var(--color-text-muted); margin: 0 0 20px; line-height: 1.4; }

.console-form { display: flex; flex-direction: column; gap: 16px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 0.76rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.field__input { padding: 10px 14px; border: 1px solid var(--color-border); border-radius: 10px; background: var(--color-surface-alt); color: var(--color-text); font-size: 0.9rem; outline: none; transition: border-color 0.2s; font-family: inherit; width: 100%; box-sizing: border-box; }
.field__input:focus { border-color: var(--color-primary); }

.w-full { width: 100%; }
.justify-center { justify-content: center; }

/* Checkbox Styling */
.checkbox-field { margin: 4px 0 12px; }
.checkbox-container { display: flex; align-items: center; position: relative; padding-left: 28px; cursor: pointer; font-size: 0.85rem; color: var(--color-text); user-select: none; }
.checkbox-container input { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
.checkbox-checkmark { position: absolute; top: 50%; transform: translateY(-50%); left: 0; height: 18px; width: 18px; background-color: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 4px; transition: all 0.2s; }
.checkbox-container:hover input ~ .checkbox-checkmark { border-color: var(--color-primary); }
.checkbox-container input:checked ~ .checkbox-checkmark { background-color: var(--color-primary); border-color: var(--color-primary); }
.checkbox-checkmark:after { content: ""; position: absolute; display: none; }
.checkbox-container input:checked ~ .checkbox-checkmark:after { display: block; }
.checkbox-container .checkbox-checkmark:after { left: 6px; top: 2px; width: 4px; height: 8px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); }
.checkbox-label { line-height: 1; }

/* Badges */
.category-badge { font-size: 0.72rem; font-weight: 600; text-transform: capitalize; padding: 3px 8px; border-radius: 6px; background: rgba(99, 102, 241, 0.08); color: var(--color-primary); border: 1px solid rgba(99, 102, 241, 0.15); }
.priority-badge { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 20px; }
.priority-badge--high { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.priority-badge--medium { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.priority-badge--low { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }

.status-tag { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 20px; }
.status-tag--open { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.status-tag--progress { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
.status-tag--resolved { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.status-tag--closed { background: rgba(100, 116, 139, 0.15); color: #64748b; }

/* Discussion Thread Styling */
.thread-card { display: flex; flex-direction: column; min-height: 520px; }
.comment-thread { flex: 1; min-height: 280px; max-height: 480px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: 12px; background: rgba(0,0,0,0.15); padding: 16px; margin-bottom: 20px; }

.empty-thread { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; color: var(--color-text-muted); gap: 8px; padding: 32px; }
.empty-thread svg { opacity: 0.4; color: var(--color-primary); }
.empty-thread p { font-size: 0.9rem; font-weight: 600; margin: 0; }

.comments-list { display: flex; flex-direction: column; gap: 16px; }
.comment-item { display: flex; gap: 12px; align-items: flex-start; }
.comment-item--self { flex-direction: row-reverse; }

.comment-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary), #818cf8); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; color: #fff; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
.comment-item--self .comment-avatar { background: linear-gradient(135deg, #10b981, #34d399); }

.comment-bubble { flex: 1; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: 14px; padding: 12px 14px; max-width: 85%; }
.comment-item--self .comment-bubble { background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.2); }

.comment-bubble__meta { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
.comment-user-name { font-size: 0.8rem; font-weight: 700; color: var(--color-text); }

.role-badge { font-size: 0.58rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px; border-radius: 4px; font-family: sans-serif; }
.role-badge--agent { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
.role-badge--owner { background: rgba(99, 102, 241, 0.15); color: var(--color-primary); border: 1px solid rgba(99, 102, 241, 0.2); }
.role-badge--tenant { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.2); }

.comment-bubble__text { font-size: 0.875rem; color: var(--color-text); line-height: 1.5; margin: 0 0 6px; white-space: pre-wrap; }
.comment-time { font-size: 0.68rem; color: var(--color-text-muted); display: block; text-align: right; }

/* Comment input */
.comment-input-area { border-top: 1px solid var(--color-border); padding-top: 16px; }
.comment-form { display: flex; flex-direction: column; gap: 10px; align-items: flex-end; }
.comment-textarea { resize: vertical; min-height: 60px; font-size: 0.875rem; }
.comment-forbidden-notice { text-align: center; color: var(--color-text-muted); font-size: 0.8rem; padding: 12px; background: rgba(0,0,0,0.1); border-radius: 8px; border: 1px solid var(--color-border); }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; }
.btn--primary { background: var(--color-primary); color: #fff; }
.btn--primary:hover:not(:disabled) { background: var(--color-primary-hover); }
.btn--ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner { width: 16px; height: 16px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
</style>
