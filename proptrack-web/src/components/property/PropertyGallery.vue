<script setup lang="ts">
import { ref } from 'vue'
import type { PropertyPhoto } from '@/types/property'

const props = defineProps<{
  photos: PropertyPhoto[]
  propertyId: string
  canManage?: boolean
  isUploading?: boolean
}>()

const emit = defineEmits<{
  upload: [file: File]
  delete: [mediaId: number]
}>()

const lightboxIndex = ref<number | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)

function openLightbox(index: number) {
  lightboxIndex.value = index
}

function closeLightbox() {
  lightboxIndex.value = null
}

function prevPhoto() {
  if (lightboxIndex.value === null) return
  lightboxIndex.value = (lightboxIndex.value - 1 + props.photos.length) % props.photos.length
}

function nextPhoto() {
  if (lightboxIndex.value === null) return
  lightboxIndex.value = (lightboxIndex.value + 1) % props.photos.length
}

function triggerFileInput() {
  fileInputRef.value?.click()
}

function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  if (!input.files?.length) return
  emit('upload', input.files[0])
  // Reset so the same file can be re-uploaded if needed
  input.value = ''
}

function onDeletePhoto(mediaId: number) {
  if (confirm('Delete this photo?')) {
    emit('delete', mediaId)
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (lightboxIndex.value === null) return
  if (event.key === 'ArrowLeft') prevPhoto()
  if (event.key === 'ArrowRight') nextPhoto()
  if (event.key === 'Escape') closeLightbox()
}
</script>

<template>
  <div class="gallery" @keydown="handleKeydown" tabindex="-1">

    <!-- Empty state (0 photos) -->
    <div v-if="photos.length === 0" class="gallery__empty-grid">
      <!-- Active Empty Card (Slot 1) -->
      <div class="gallery__empty-tile gallery__empty-tile--active">
        <div class="gallery__empty-content">
          <div class="gallery__empty-icon-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="gallery__empty-icon" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15a2.25 2.25 0 0 0 2.25-2.25V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316A2.192 2.192 0 0 0 15.18 3.75H8.82c-.65 0-1.248.288-1.641.808l-.352.54Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
          </div>
          <h3 class="gallery__empty-title">No property photos yet</h3>
          <p class="gallery__empty-subtitle">Add photos to make your listing more appealing</p>
          
          <button
            v-if="canManage"
            class="gallery__empty-btn"
            :disabled="isUploading"
            @click="triggerFileInput"
          >
            <span v-if="isUploading" class="gallery__spinner" />
            <template v-else>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="btn-icon" aria-hidden="true" style="width: 12px; height: 12px; margin-right: 3px;">
                <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
              </svg>
              Upload First Photo
            </template>
          </button>
        </div>
      </div>

      <!-- Dashed Placeholder Cards (Slots 2, 3) -->
      <div v-for="i in 2" :key="i" class="gallery__empty-tile gallery__empty-tile--placeholder">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" class="gallery__placeholder-icon" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375 0 1 1-.75 0 .375 0 0 1 .75 0Z" />
        </svg>
      </div>
    </div>

    <!-- Grid -->
    <div v-else class="gallery__grid">
      <div
        v-for="(photo, index) in photos"
        :key="photo.id"
        class="gallery__item"
      >
        <img
          :src="photo.thumbnail_url"
          :alt="`Photo ${index + 1}`"
          class="gallery__thumb"
          @click="openLightbox(index)"
        />
        <button
          v-if="canManage"
          class="gallery__delete-btn"
          title="Delete photo"
          @click.stop="onDeletePhoto(photo.id)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>

      <!-- Upload slot in grid -->
      <button
        v-if="canManage"
        class="gallery__upload-tile"
        :disabled="isUploading"
        @click="triggerFileInput"
      >
        <span v-if="isUploading" class="gallery__spinner" />
        <template v-else>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="gallery__upload-icon">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Add Photo</span>
        </template>
      </button>
    </div>

    <!-- Hidden file input -->
    <input
      ref="fileInputRef"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      style="display: none"
      @change="onFileSelected"
    />

    <!-- Lightbox -->
    <Teleport to="body">
      <div
        v-if="lightboxIndex !== null"
        class="lightbox"
        @click.self="closeLightbox"
      >
        <button class="lightbox__close" @click="closeLightbox">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 011.06 0L12 10.94l5.47-5.47a.75.75 0 111.06 1.06L13.06 12l5.47 5.47a.75.75 0 11-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 01-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 010-1.06z" clip-rule="evenodd" />
          </svg>
        </button>

        <button v-if="photos.length > 1" class="lightbox__nav lightbox__nav--prev" @click="prevPhoto">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M7.72 12.53a.75.75 0 010-1.06l7.5-7.5a.75.75 0 111.06 1.06L9.31 12l6.97 6.97a.75.75 0 11-1.06 1.06l-7.5-7.5z" clip-rule="evenodd" />
          </svg>
        </button>

        <img
          :src="photos[lightboxIndex].url"
          :alt="`Photo ${lightboxIndex + 1}`"
          class="lightbox__img"
        />

        <button v-if="photos.length > 1" class="lightbox__nav lightbox__nav--next" @click="nextPhoto">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M16.28 11.47a.75.75 0 010 1.06l-7.5 7.5a.75.75 0 01-1.06-1.06L14.69 12 7.72 5.03a.75.75 0 011.06-1.06l7.5 7.5z" clip-rule="evenodd" />
          </svg>
        </button>

        <div class="lightbox__counter">
          {{ lightboxIndex + 1 }} / {{ photos.length }}
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* Grid */
.gallery__grid,
.gallery__empty-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 280px));
  gap: 16px;
}

.gallery__item {
  position: relative;
  width: 280px;
  max-width: 100%;
  height: 200px;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
}

.gallery__thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.25s ease;
}

.gallery__item:hover .gallery__thumb {
  transform: scale(1.06);
}

.gallery__delete-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.85);
  border: none;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s;
  z-index: 2;
}

.gallery__item:hover .gallery__delete-btn {
  opacity: 1;
}

.gallery__delete-btn svg {
  width: 14px;
  height: 14px;
}

/* Upload tile */
.gallery__upload-tile {
  width: 280px;
  max-width: 100%;
  height: 200px;
  border: 1px dashed var(--g300);
  border-radius: 12px;
  background: var(--g50);
  color: var(--g500);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 600;
  transition: all 0.2s ease;
  font-family: 'Outfit', var(--font-sans);
}

.gallery__upload-tile:hover:not(:disabled) {
  border-color: var(--g900);
  color: var(--g900);
  background: var(--g100);
  box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}

.gallery__upload-icon {
  width: 22px;
  height: 22px;
  color: var(--g400);
  transition: color 0.2s ease;
}

.gallery__upload-tile:hover .gallery__upload-icon {
  color: var(--g900);
}

/* Empty states */
.gallery__empty-tile {
  width: 280px;
  max-width: 100%;
  height: 200px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.gallery__empty-tile--active {
  background: var(--g50);
  border: 1px solid var(--g200);
  padding: 16px;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gallery__empty-tile--active:hover {
  border-color: var(--g300);
  box-shadow: 0 4px 12px rgba(26, 23, 18, 0.03);
}

.gallery__empty-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  max-width: 240px;
}

.gallery__empty-icon-wrap {
  width: 32px;
  height: 32px;
  background: var(--g100);
  color: var(--g500);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 8px;
}

.gallery__empty-icon {
  width: 16px;
  height: 16px;
}

.gallery__empty-title {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--g900);
  margin: 0 0 2px;
  letter-spacing: -0.01em;
}

.gallery__empty-subtitle {
  font-size: 0.65rem;
  color: var(--g500);
  margin: 0 0 10px;
  line-height: 1.3;
  text-wrap: pretty;
}

.gallery__empty-btn {
  font-size: 0.68rem;
  padding: 5px 10px;
  border-radius: 6px;
  background: var(--g900);
  color: #fff;
  border: none;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  display: inline-flex;
  align-items: center;
  gap: 3px;
  transition: all 0.15s ease;
}

.gallery__empty-btn:hover {
  background: var(--g700);
}

.gallery__empty-btn:active {
  transform: scale(0.97);
}

.gallery__empty-tile--placeholder {
  border: 1px dashed var(--g200);
  background: transparent;
  color: var(--g200);
}

.gallery__placeholder-icon {
  width: 24px;
  height: 24px;
  opacity: 0.45;
}

/* Spinner */
.gallery__spinner {
  width: 18px;
  height: 18px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Lightbox */
.lightbox {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fade-in 0.2s ease;
}

@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

.lightbox__img {
  max-width: 90vw;
  max-height: 85vh;
  object-fit: contain;
  border-radius: 8px;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.6);
}

.lightbox__close {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.lightbox__close:hover { background: rgba(255, 255, 255, 0.2); }
.lightbox__close svg { width: 20px; height: 20px; }

.lightbox__nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.lightbox__nav:hover { background: rgba(255, 255, 255, 0.2); }
.lightbox__nav svg { width: 24px; height: 24px; }
.lightbox__nav--prev { left: 20px; }
.lightbox__nav--next { right: 20px; }

.lightbox__counter {
  position: absolute;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.875rem;
  font-weight: 500;
  background: rgba(0, 0, 0, 0.4);
  padding: 6px 16px;
  border-radius: 20px;
}
</style>
