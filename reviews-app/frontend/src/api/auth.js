import http from './http'

export function login(email, password) {
  return http.post('/login', { email, password })
}

export function logout() {
  return http.post('/logout')
}

export function getUser() {
  return http.get('/user')
}
