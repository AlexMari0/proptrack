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

    <!-- Empty state -->
    <div v-if="photos.length === 0 && !canManage" class="gallery__empty">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
        <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z" clip-rule="evenodd" />
      </svg>
      <p>No photos yet</p>
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
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
          </svg>
          <span>Add Photo</span>
        </template>
      </button>
    </div>

    <!-- Upload button when no photos yet but can manage -->
    <div v-if="photos.length === 0 && canManage" class="gallery__empty-upload">
      <button class="gallery__upload-btn" :disabled="isUploading" @click="triggerFileInput">
        <span v-if="isUploading" class="gallery__spinner" />
        <template v-else>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M11.47 2.47a.75.75 0 011.06 0l4.5 4.5a.75.75 0 01-1.06 1.06l-3.22-3.22V16.5a.75.75 0 01-1.5 0V4.81L8.03 8.03a.75.75 0 01-1.06-1.06l4.5-4.5zM3 15.75a.75.75 0 01.75.75v2.25a1.5 1.5 0 001.5 1.5h13.5a1.5 1.5 0 001.5-1.5V16.5a.75.75 0 011.5 0v2.25a3 3 0 01-3 3H5.25a3 3 0 01-3-3V16.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
          </svg>
          Upload First Photo
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
.gallery__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}

.gallery__item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 10px;
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
  top: 6px;
  right: 6px;
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
  aspect-ratio: 1;
  border: 2px dashed var(--color-border);
  border-radius: 10px;
  background: var(--color-surface-alt);
  color: var(--color-text-muted);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 0.75rem;
  font-weight: 500;
  transition: border-color 0.2s, color 0.2s, background 0.2s;
}

.gallery__upload-tile:hover:not(:disabled) {
  border-color: var(--color-primary);
  color: var(--color-primary);
  background: rgba(99, 102, 241, 0.06);
}

.gallery__upload-tile svg {
  width: 24px;
  height: 24px;
}

/* Empty states */
.gallery__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px 20px;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.gallery__empty svg {
  width: 48px;
  height: 48px;
  opacity: 0.35;
}

.gallery__empty-upload {
  display: flex;
  justify-content: center;
  padding: 24px 0 8px;
}

.gallery__upload-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 10px;
  border: 2px dashed var(--color-border);
  background: var(--color-surface-alt);
  color: var(--color-text-muted);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.2s, color 0.2s;
}

.gallery__upload-btn:hover:not(:disabled) {
  border-color: var(--color-primary);
  color: var(--color-primary);
}

.gallery__upload-btn svg {
  width: 18px;
  height: 18px;
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
