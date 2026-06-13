<template>
  <div class="app">
    <nav v-if="isAuthenticated()" class="navbar">
      <span class="navbar__brand">Reviews App</span>
      <div class="navbar__links">
        <router-link to="/settings">Settings</router-link>
        <router-link to="/reviews">Reviews</router-link>
        <button class="btn btn--sm btn--outline" @click="logout">Logout</button>
      </div>
    </nav>
    <main class="main">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { useAuth } from './composables/useAuth'

const { isAuthenticated, logout } = useAuth()
</script>

<style scoped>
.app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.navbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  height: 56px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
}

.navbar__brand {
  font-weight: 700;
  font-size: 1.1rem;
  color: #1a1a2e;
}

.navbar__links {
  display: flex;
  align-items: center;
  gap: 20px;
}

.navbar__links a {
  color: #555;
  font-size: 0.95rem;
  transition: color 0.15s;
}

.navbar__links a:hover,
.navbar__links a.router-link-active {
  color: #4f46e5;
}

.main {
  flex: 1;
  padding: 32px 24px;
  max-width: 900px;
  width: 100%;
  margin: 0 auto;
}
</style>
