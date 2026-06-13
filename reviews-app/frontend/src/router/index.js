import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import SettingsView from '../views/SettingsView.vue'
import ReviewsView from '../views/ReviewsView.vue'

const routes = [
  { path: '/', redirect: '/settings' },
  { path: '/login', component: LoginView, meta: { guest: true } },
  { path: '/settings', component: SettingsView, meta: { requiresAuth: true } },
  { path: '/reviews', component: ReviewsView, meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const token = localStorage.getItem('token')

  if (to.meta.requiresAuth && !token) {
    return '/login'
  }

  if (to.meta.guest && token) {
    return '/settings'
  }
})

export default router
