<template>
  <div class="review-card">
    <div class="review-card__header">
      <div class="review-card__author-info">
        <span class="review-card__author">{{ review.author }}</span>
        <span class="review-card__date">{{ formatDate(review.published_at) }}</span>
      </div>
      <StarRating :rating="review.rating" />
    </div>
    <p v-if="review.text" class="review-card__text">{{ review.text }}</p>
    <p v-else class="review-card__no-text">No text</p>
  </div>
</template>

<script setup>
import StarRating from './StarRating.vue'

defineProps({
  review: {
    type: Object,
    required: true,
  },
})

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}
</script>

<style scoped>
.review-card {
  background: #fff;
  border-radius: 10px;
  padding: 18px 20px;
  box-shadow: 0 1px 6px rgba(0,0,0,0.06);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.review-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 8px;
}

.review-card__author-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.review-card__author {
  font-weight: 600;
  font-size: 0.95rem;
  color: #1a1a2e;
}

.review-card__date {
  font-size: 0.8rem;
  color: #9ca3af;
}

.review-card__text {
  font-size: 0.9rem;
  color: #444;
  line-height: 1.5;
  white-space: pre-wrap;
}

.review-card__no-text {
  font-size: 0.85rem;
  color: #c4c4c4;
  font-style: italic;
}
</style>
