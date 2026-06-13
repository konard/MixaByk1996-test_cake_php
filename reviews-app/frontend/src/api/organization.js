import http from './http'

export function getOrganization() {
  return http.get('/organization')
}

export function saveOrganization(url) {
  return http.post('/organization', { url })
}

export function getReviews(page = 1) {
  return http.get('/reviews', { params: { page } })
}
