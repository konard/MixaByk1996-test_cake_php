import { ref } from 'vue'
import { login as apiLogin, logout as apiLogout } from '../api/auth'
import router from '../router'

const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))

export function useAuth() {
  const isLoading = ref(false)
  const error = ref(null)

  async function login(email, password) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiLogin(email, password)
      localStorage.setItem('token', data.token)
      localStorage.setItem('user', JSON.stringify(data.user))
      user.value = data.user
      await router.push('/settings')
    } catch (e) {
      error.value = e.response?.data?.message || 'Login failed'
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await apiLogout()
    } finally {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      user.value = null
      await router.push('/login')
    }
  }

  function isAuthenticated() {
    return !!localStorage.getItem('token')
  }

  return { user, isLoading, error, login, logout, isAuthenticated }
}
