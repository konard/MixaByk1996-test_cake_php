<template>
  <div class="login-page">
    <div class="login-card">
      <h1 class="login-card__title">Sign In</h1>
      <p class="login-card__sub">Yandex Maps Reviews</p>

      <form class="login-form" @submit.prevent="handleSubmit">
        <div class="field">
          <label class="field__label" for="email">Email</label>
          <input
            id="email"
            v-model="email"
            class="field__input"
            type="email"
            placeholder="user@example.com"
            required
            autocomplete="email"
          />
        </div>

        <div class="field">
          <label class="field__label" for="password">Password</label>
          <input
            id="password"
            v-model="password"
            class="field__input"
            type="password"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          />
        </div>

        <p v-if="error" class="error-msg">{{ error }}</p>

        <button class="btn btn--primary btn--full" type="submit" :disabled="isLoading">
          <span v-if="isLoading">Signing in…</span>
          <span v-else>Sign In</span>
        </button>
      </form>

      <p class="login-card__hint">Demo: user@example.com / password</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuth } from '../composables/useAuth'

const { login, isLoading, error } = useAuth()

const email = ref('user@example.com')
const password = ref('password')

function handleSubmit() {
  login(email.value, password.value)
}
</script>

<style scoped>
.login-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: #f5f5f5;
}

.login-card {
  background: #fff;
  border-radius: 12px;
  padding: 40px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 2px 16px rgba(0,0,0,0.08);
}

.login-card__title {
  font-size: 1.6rem;
  font-weight: 700;
  color: #1a1a2e;
  margin-bottom: 4px;
}

.login-card__sub {
  color: #888;
  margin-bottom: 28px;
  font-size: 0.9rem;
}

.login-card__hint {
  margin-top: 16px;
  text-align: center;
  font-size: 0.8rem;
  color: #aaa;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
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

.error-msg {
  color: #dc2626;
  font-size: 0.85rem;
  padding: 8px 12px;
  background: #fef2f2;
  border-radius: 6px;
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 500;
  border: none;
  transition: background 0.15s, opacity 0.15s;
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
}

.btn--full {
  width: 100%;
}
</style>
