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
    if (activeTicketChannel) echo.leave(`ticket.${activeTicketChannel}`)
    activeTicketChannel = ticketId
    echo.private(`ticket.${ticketId}`).listen('.TicketStatusUpdated', async () => { await fetchTicket(ticketId) })
  }
}

function unsubscribeFromTicket() {
  const echo = getEcho()
  if (echo && activeTicketChannel) { echo.leave(`ticket.${activeTicketChannel}`); activeTicketChannel = null }
}

watch(() => route.params.id, (newId) => {
  if (newId) subscribeToTicket(newId as string)
  else unsubscribeFromTicket()
}, { immediate: true })

const newComment = ref('')
const commentError = ref('')

const isAgentOrAdmin = computed(() =>
  authStore.user?.roles?.some((role) => ['agent', 'admin'].includes(role)) ?? false
)

const canComment = computed(() => {
  if (!selectedTicket.value) return false
  const roles = authStore.user?.roles ?? []
  if (roles.includes('admin') || roles.includes('agent') || roles.includes('owner')) return true
  if (roles.includes('tenant')) return selectedTicket.value.submitted_by.id === authStore.user?.id
  return false
})

onMounted(async () => { await fetchTicket(route.params.id as string) })
onUnmounted(() => { unsubscribeFromTicket() })

const consoleStatus = ref<TicketStatus>('open')
const assignToMe = ref(false)

onMounted(() => {
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
  if (success) await fetchTicket(selectedTicket.value.id)
}

async function handleAddComment() {
  if (!selectedTicket.value || !newComment.value.trim()) return
  commentError.value = ''
  const success = await addComment(selectedTicket.value.id, newComment.value.trim())
  if (success) { newComment.value = '' }
  else { commentError.value = error.value || 'Failed to send comment.' }
}

function priorityBadge(p: TicketPriority): string {
  return p === 'high' ? 'badge badge--red' : p === 'medium' ? 'badge badge--amber' : 'badge badge--gray'
}

function statusBadge(s: TicketStatus): string {
  return s === 'resolved' ? 'badge badge--green' : s === 'in_progress' ? 'badge badge--indigo' : s === 'open' ? 'badge badge--amber' : 'badge badge--gray'
}

function roleBadgeColor(role: string): string {
  return role === 'tenant' ? 'role-chip role-chip--tenant' : role === 'owner' ? 'role-chip role-chip--owner' : 'role-chip role-chip--agent'
}
</script>

<template>
  <div class="page-content">
    <button class="back-link" @click="router.push({ name: 'tickets' })">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      Back to tickets
    </button>

    <div v-if="isLoading && !selectedTicket" class="shimmer" style="height:300px;border-radius:16px" />
    <div v-if="error && !selectedTicket" class="alert alert--error">{{ error }}</div>

    <div v-if="selectedTicket" class="detail-grid">
      <!-- Left column -->
      <div class="left-col">
        <!-- Ticket info card -->
        <div class="card" style="margin-bottom:16px">
          <div class="ticket-header">
            <span style="font-family:monospace;font-size:0.78rem;font-weight:700;color:var(--color-primary)">{{ selectedTicket.ticket_number }}</span>
            <span :class="statusBadge(selectedTicket.status)" style="text-transform:capitalize">{{ selectedTicket.status.replace('_', ' ') }}</span>
          </div>
          <h1 class="ticket-title">{{ selectedTicket.title }}</h1>
          <p class="ticket-description">{{ selectedTicket.description }}</p>

          <div class="info-list">
            <div class="info-row">
              <span class="info-row__label">Category</span>
              <span class="badge badge--indigo" style="text-transform:capitalize">{{ selectedTicket.category }}</span>
            </div>
            <div class="info-row">
              <span class="info-row__label">Priority</span>
              <span :class="priorityBadge(selectedTicket.priority)" style="text-transform:capitalize">{{ selectedTicket.priority }}</span>
            </div>
            <div class="info-row">
              <span class="info-row__label">Property</span>
              <span style="font-weight:600;color:var(--g700)">{{ selectedTicket.property.name }}</span>
            </div>
            <div class="info-row">
              <span class="info-row__label">Submitted by</span>
              <span style="color:var(--g600)">{{ selectedTicket.submitted_by.name }}</span>
            </div>
            <div class="info-row">
              <span class="info-row__label">Assigned to</span>
              <span style="color:var(--g600)">{{ selectedTicket.assigned_to ? selectedTicket.assigned_to.name : 'Unassigned' }}</span>
            </div>
            <div class="info-row">
              <span class="info-row__label">Created</span>
              <span style="color:var(--g400);font-size:0.78rem">{{ new Date(selectedTicket.created_at).toLocaleString('id-ID', { dateStyle:'medium', timeStyle:'short' }) }}</span>
            </div>
          </div>
        </div>

        <!-- Agent console -->
        <div v-if="isAgentOrAdmin" class="card">
          <p class="section-label">Agent console</p>
          <p style="font-size:0.78rem;color:var(--g400);margin-bottom:16px;margin-top:4px">Update ticket status and assignment below.</p>
          <form @submit.prevent="handleUpdateStatus" style="display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="form-label" for="console_status">Update status</label>
              <select id="console_status" v-model="consoleStatus" class="form-select">
                <option value="open">Open</option>
                <option value="in_progress">In progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <label class="assign-check">
              <input type="checkbox" v-model="assignToMe" />
              <span>Assign this ticket to me</span>
            </label>
            <button type="submit" class="btn-primary" :disabled="isSubmitting" style="justify-content:center">
              {{ isSubmitting ? 'Saving…' : 'Save changes' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Right column — Discussion thread -->
      <div class="right-col">
        <div class="card" style="height:100%">
          <p class="section-label">Discussion</p>
          <p style="font-size:0.78rem;color:var(--g400);margin-bottom:16px;margin-top:4px">Written communication thread for this ticket.</p>

          <div class="thread">
            <div v-if="!selectedTicket.comments || selectedTicket.comments.length === 0" class="thread-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:28px;height:28px;color:var(--g300)" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
              <p style="font-size:0.8rem;color:var(--g400);margin:0">No comments yet. Be the first to respond.</p>
            </div>

            <div v-else class="comments">
              <div v-for="comment in selectedTicket.comments" :key="comment.id"
                :class="['comment', { 'comment--self': comment.user.id === authStore.user?.id }]">
                <div class="comment__avatar" aria-hidden="true">{{ comment.user.name.charAt(0).toUpperCase() }}</div>
                <div class="comment__body">
                  <div class="comment__meta">
                    <span style="font-weight:600;font-size:0.8rem;color:var(--g800)">{{ comment.user.name }}</span>
                    <span :class="roleBadgeColor(comment.user.role)">{{ comment.user.role }}</span>
                    <span style="font-size:0.7rem;color:var(--g400);margin-left:auto">{{ new Date(comment.created_at).toLocaleString('id-ID', { dateStyle:'short', timeStyle:'short' }) }}</span>
                  </div>
                  <p class="comment__text">{{ comment.content }}</p>
                </div>
              </div>
            </div>
          </div>

          <div v-if="canComment" class="comment-input">
            <div v-if="commentError" class="alert alert--error" style="margin-bottom:8px">{{ commentError }}</div>
            <form @submit.prevent="handleAddComment" style="display:flex;gap:10px;align-items:flex-start">
              <textarea v-model="newComment" rows="2" class="form-textarea" placeholder="Type a response or update…" :disabled="isSubmitting" style="flex:1" />
              <button type="submit" class="btn-send" :disabled="isSubmitting">
                Send
              </button>
            </form>
          </div>
          <p v-else style="font-size:0.78rem;color:var(--g400);margin-top:12px">You are not authorized to add comments to this ticket.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1.2fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 900px) { .detail-grid { grid-template-columns: 1fr; } }

.left-col { display: flex; flex-direction: column; gap: 0; }
.right-col { display: flex; flex-direction: column; }

.ticket-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.ticket-title { font-size: 1.25rem; font-weight: 700; color: var(--g900); margin: 0 0 12px; line-height: 1.35; }
.ticket-description {
  font-size: 0.875rem; color: var(--g700); line-height: 1.65; margin: 0 0 20px;
  white-space: pre-wrap; background: var(--g50); padding: 14px; border-radius: 10px;
  border: 1px solid var(--g100);
}

.info-list { display: flex; flex-direction: column; gap: 10px; }
.info-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; padding-bottom: 8px; border-bottom: 1px solid var(--g100); }
.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-row__label { color: var(--g400); font-weight: 500; font-size: 0.8rem; }

.assign-check { display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--g700); cursor: pointer; }
.assign-check input { accent-color: var(--amber); width: 15px; height: 15px; cursor: pointer; }

.thread { max-height: 340px; overflow-y: auto; margin-bottom: 16px; }
.thread-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 32px; background: var(--g50); border-radius: 10px; text-align: center; }

.comments { display: flex; flex-direction: column; gap: 12px; }
.comment { display: flex; gap: 10px; }
.comment--self { flex-direction: row-reverse; }
.comment__avatar {
  width: 30px; height: 30px; border-radius: 50%; background: var(--g900); color: #fff;
  display: flex; align-items: center; justify-content: center; font-size: 0.68rem; font-weight: 700;
  flex-shrink: 0;
}
.comment__body { flex: 1; }
.comment__meta { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; flex-wrap: wrap; }
.comment--self .comment__meta { flex-direction: row-reverse; }
.comment__text {
  font-size: 0.85rem; color: var(--g700); line-height: 1.55; margin: 0;
  background: var(--g50); border: 1px solid var(--g100); border-radius: 10px;
  padding: 10px 12px; white-space: pre-wrap;
}
.comment--self .comment__text { background: var(--g900); color: #f5f1ea; border-color: var(--g800); }

.comment-input { border-top: 1px solid var(--g100); padding-top: 16px; margin-top: 4px; }

.role-chip { font-size: 0.62rem; font-weight: 700; padding: 2px 7px; border-radius: 10px; text-transform: capitalize; }
.role-chip--tenant  { background: rgba(34,197,94,0.12);   color: #16a34a; }
.role-chip--owner   { background: rgba(234,179,8,0.15);   color: #a16207; }
.role-chip--agent   { background: rgba(99,102,241,0.12);  color: #4338ca; }

.btn-send {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 9px 18px;
  background: var(--color-primary);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  font-family: 'Outfit', var(--font-sans);
  letter-spacing: -0.01em;
  transition: background 0.15s, transform 0.12s, box-shadow 0.15s;
  text-decoration: none;
  white-space: nowrap;
}
.btn-send:hover {
  background: var(--color-primary-hover, #4f46e5);
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.25);
}
.btn-send:active {
  transform: scale(0.97);
}
.btn-send:disabled {
  opacity: 0.45;
  pointer-events: none;
}
</style>
