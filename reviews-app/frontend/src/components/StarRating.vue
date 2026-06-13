<template>
  <span class="stars" aria-label="`${rating} out of 5 stars`">
    <span
      v-for="i in 5"
      :key="i"
      class="star"
      :class="starClass(i)"
    >★</span>
  </span>
</template>

<script setup>
const props = defineProps({
  rating: {
    type: Number,
    required: true,
  },
})

function starClass(i) {
  if (i <= Math.floor(props.rating)) return 'star--full'
  if (i === Math.ceil(props.rating) && props.rating % 1 >= 0.5) return 'star--half'
  return 'star--empty'
}
</script>

<style scoped>
.stars {
  display: inline-flex;
  gap: 1px;
}

.star {
  font-size: 1rem;
  line-height: 1;
}

.star--full {
  color: #f59e0b;
}

.star--half {
  color: #fbbf24;
}

.star--empty {
  color: #d1d5db;
}
</style>
