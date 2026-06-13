<template>
  <div class="reviews">
    <div v-if="orgLoading" class="state-center">
      <Spinner />
    </div>

    <template v-else-if="organization">
      <div class="reviews__header">
        <div>
          <h1 class="page-title">{{ organization.name }}</h1>
          <p class="page-sub">{{ organization.yandex_url }}</p>
        </div>
        <router-link class="btn btn--secondary" to="/settings">← Settings</router-link>
      </div>

      <div class="stats-row">
        <div class="stat-card">
          <span class="stat-card__label">Average rating</span>
          <div class="stat-card__value">
            <StarRating :rating="organization.average_rating" />
            <strong>{{ organization.average_rating.toFixed(1) }}</strong>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Total ratings</span>
          <strong class="stat-card__value">{{ organization.rating_count.toLocaleString() }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Total reviews</span>
          <strong class="stat-card__value">{{ organization.review_count.toLocaleString() }}</strong>
        </div>
      </div>

      <div v-if="reviewsError" class="error-msg">{{ reviewsError }}</div>

      <div v-if="reviewsLoading" class="state-center">
        <Spinner />
        <span>Loading reviews…</span>
      </div>

      <template v-else>
        <div class="reviews__list">
          <ReviewCard v-for="review in reviews" :key="review.external_id" :review="review" />
        </div>

        <div v-if="reviews.length === 0 && !reviewsLoading" class="state-center">
          <p>No reviews found.</p>
        </div>

        <div v-if="meta" class="pagination">
          <button
            class="btn btn--sm"
            :disabled="meta.current_page <= 1"
            @click="changePage(meta.current_page - 1)"
          >
            ← Prev
          </button>
          <span class="pagination__info">
            Page {{ meta.current_page }} of {{ meta.last_page }}
            <span class="pagination__total">({{ meta.total }} reviews)</span>
          </span>
          <button
            class="btn btn--sm"
            :disabled="meta.current_page >= meta.last_page"
            @click="changePage(meta.current_page + 1)"
          >
            Next →
          </button>
        </div>
      </template>
    </template>

    <div v-else class="state-center">
      <p>No organization configured. <router-link to="/settings">Set one up →</router-link></p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { getOrganization, getReviews } from '../api/organization'
import ReviewCard from '../components/ReviewCard.vue'
import StarRating from '../components/StarRating.vue'
import Spinner from '../components/Spinner.vue'

const organization = ref(null)
const reviews = ref([])
const meta = ref(null)
const orgLoading = ref(true)
const reviewsLoading = ref(false)
const reviewsError = ref(null)
const currentPage = ref(1)

async function loadReviews(page) {
  reviewsLoading.value = true
  reviewsError.value = null
  try {
    const { data } = await getReviews(page)
    reviews.value = data.data
    meta.value = data.meta
  } catch (e) {
    reviewsError.value = e.response?.data?.message || 'Failed to load reviews'
  } finally {
    reviewsLoading.value = false
  }
}

function changePage(page) {
  currentPage.value = page
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

watch(currentPage, (page) => loadReviews(page))

onMounted(async () => {
  try {
    const { data } = await getOrganization()
    organization.value = data
    if (data) {
      await loadReviews(1)
    }
  } catch {
    // no organization
  } finally {
    orgLoading.value = false
  }
})
</script>

<style scoped>
.reviews {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.reviews__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1a1a2e;
}

.page-sub {
  color: #888;
  font-size: 0.85rem;
  margin-top: 4px;
  word-break: break-all;
}

.stats-row {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.stat-card {
  background: #fff;
  border-radius: 10px;
  padding: 16px 20px;
  box-shadow: 0 1px 6px rgba(0,0,0,0.07);
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 160px;
}

.stat-card__label {
  font-size: 0.8rem;
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stat-card__value {
  font-size: 1.4rem;
  color: #1a1a2e;
  display: flex;
  align-items: center;
  gap: 6px;
}

.reviews__list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.state-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 48px 0;
  color: #888;
}

.error-msg {
  color: #dc2626;
  font-size: 0.9rem;
  padding: 10px 14px;
  background: #fef2f2;
  border-radius: 8px;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px 0;
}

.pagination__info {
  font-size: 0.9rem;
  color: #555;
}

.pagination__total {
  color: #9ca3af;
  margin-left: 4px;
}

.btn {
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 500;
  border: 1px solid #d1d5db;
  background: #fff;
  color: #374151;
  cursor: pointer;
  transition: background 0.15s;
}

.btn:hover:not(:disabled) {
  background: #f3f4f6;
}

.btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.btn--secondary {
  background: #f3f4f6;
  border-color: transparent;
  font-size: 0.85rem;
}
</style>
