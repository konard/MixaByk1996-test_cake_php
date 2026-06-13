<template>
  <div class="settings">
    <h1 class="page-title">Settings</h1>
    <p class="page-sub">Connect a Yandex Maps organization to fetch its reviews.</p>

    <div class="card">
      <h2 class="card__title">Organization URL</h2>
      <form @submit.prevent="handleSave">
        <div class="field">
          <label class="field__label" for="url">Yandex Maps link</label>
          <input
            id="url"
            v-model="url"
            class="field__input"
            type="url"
            placeholder="https://yandex.ru/maps/org/name/123456789/"
            required
          />
          <span class="field__hint">Paste the full URL of the organization card on Yandex Maps.</span>
        </div>

        <p v-if="validationError" class="error-msg">{{ validationError }}</p>
        <p v-if="saveError" class="error-msg">{{ saveError }}</p>
        <p v-if="saveSuccess" class="success-msg">Organization saved and reviews fetched successfully!</p>

        <button class="btn btn--primary" type="submit" :disabled="isSaving">
          <span v-if="isSaving">Fetching reviews…</span>
          <span v-else>Save &amp; Fetch Reviews</span>
        </button>
      </form>
    </div>

    <div v-if="organization" class="card card--info">
      <h2 class="card__title">Current Organization</h2>
      <div class="org-info">
        <div class="org-info__row">
          <span class="org-info__label">Name</span>
          <span class="org-info__value">{{ organization.name }}</span>
        </div>
        <div class="org-info__row">
          <span class="org-info__label">Average Rating</span>
          <span class="org-info__value">
            <StarRating :rating="organization.average_rating" />
            {{ organization.average_rating.toFixed(1) }}
          </span>
        </div>
        <div class="org-info__row">
          <span class="org-info__label">Ratings</span>
          <span class="org-info__value">{{ organization.rating_count.toLocaleString() }}</span>
        </div>
        <div class="org-info__row">
          <span class="org-info__label">Reviews</span>
          <span class="org-info__value">{{ organization.review_count.toLocaleString() }}</span>
        </div>
        <div class="org-info__row">
          <span class="org-info__label">Last fetched</span>
          <span class="org-info__value">{{ formatDate(organization.last_parsed_at) }}</span>
        </div>
      </div>
      <router-link class="btn btn--secondary" to="/reviews">View Reviews →</router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getOrganization, saveOrganization } from '../api/organization'
import StarRating from '../components/StarRating.vue'

const url = ref('')
const organization = ref(null)
const isSaving = ref(false)
const saveError = ref(null)
const saveSuccess = ref(false)
const validationError = ref(null)

const YANDEX_URL_PATTERN = /^https?:\/\/(yandex\.ru|maps\.yandex\.ru|yandex\.com)\/maps\/.*org\/\d+/

function validateUrl(value) {
  if (!YANDEX_URL_PATTERN.test(value)) {
    return 'Please enter a valid Yandex Maps organization URL (e.g. https://yandex.ru/maps/org/name/123456789/)'
  }
  return null
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleString()
}

async function handleSave() {
  validationError.value = validateUrl(url.value)
  if (validationError.value) return

  isSaving.value = true
  saveError.value = null
  saveSuccess.value = false

  try {
    const { data } = await saveOrganization(url.value)
    organization.value = data
    saveSuccess.value = true
  } catch (e) {
    saveError.value = e.response?.data?.message || e.response?.data?.errors?.url?.[0] || 'Failed to save organization'
  } finally {
    isSaving.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await getOrganization()
    if (data) {
      organization.value = data
      url.value = data.yandex_url
    }
  } catch {
    // no organization yet
  }
})
</script>

<style scoped>
.settings {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1a1a2e;
}

.page-sub {
  color: #888;
  margin-top: -16px;
}

.card {
  background: #fff;
  border-radius: 12px;
  padding: 28px;
  box-shadow: 0 1px 8px rgba(0,0,0,0.07);
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.card--info {
  border-left: 4px solid #4f46e5;
}

.card__title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1a1a2e;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}

.field__label {
  font-size: 0.85rem;
  font-weight: 500;
  color: #444;
}

.field__input {
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.95rem;
  outline: none;
  transition: border-color 0.15s;
}

.field__input:focus {
  border-color: #4f46e5;
}

.field__hint {
  font-size: 0.8rem;
  color: #9ca3af;
}

.error-msg {
  color: #dc2626;
  font-size: 0.85rem;
  padding: 8px 12px;
  background: #fef2f2;
  border-radius: 6px;
  margin-bottom: 12px;
}

.success-msg {
  color: #16a34a;
  font-size: 0.85rem;
  padding: 8px 12px;
  background: #f0fdf4;
  border-radius: 6px;
  margin-bottom: 12px;
}

.org-info {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.org-info__row {
  display: flex;
  gap: 12px;
}

.org-info__label {
  font-size: 0.85rem;
  color: #888;
  width: 130px;
  flex-shrink: 0;
}

.org-info__value {
  font-size: 0.95rem;
  color: #1a1a2e;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
  display: inline-block;
  text-align: center;
  width: fit-content;
}

.btn--primary {
  background: #4f46e5;
  color: #fff;
}

.btn--primary:hover:not(:disabled) {
  background: #4338ca;
}

.btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn--secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn--secondary:hover {
  background: #e5e7eb;
}
</style>
