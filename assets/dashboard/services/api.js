export const API_BASE = '/api/v1';
export const DASHBOARD_API_BASE = '/dashboard/api';

function authHeaders() {
  const t = localStorage.getItem('jwt_token');
  return t ? { Authorization: `Bearer ${t}` } : {};
}

async function request(path, options = {}) {
  const res = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...authHeaders(),
      ...(options.headers || {})
    }
  });
  const ct = res.headers.get('content-type') || '';
  const data = ct.includes('application/json') ? await res.json().catch(() => null) : await res.text();
  if (!res.ok) throw Object.assign(new Error('API error'), { status: res.status, data });
  return { status: res.status, data };
}

export const api = {
  get: (path) => request(path, { method: 'GET' }),
  post: (path, body) => request(path, { method: 'POST', body: JSON.stringify(body) }),
  put: (path, body) => request(path, { method: 'PUT', body: JSON.stringify(body) }),
  delete: (path) => request(path, { method: 'DELETE' }),
};

// Dashboard-specific API helper
export const dashboardApi = {
  get: (path) => request(`${DASHBOARD_API_BASE}${path}`, { method: 'GET' }),
  post: (path, body) => request(`${DASHBOARD_API_BASE}${path}`, { method: 'POST', body: JSON.stringify(body) }),
  put: (path, body) => request(`${DASHBOARD_API_BASE}${path}`, { method: 'PUT', body: JSON.stringify(body) }),
  delete: (path) => request(`${DASHBOARD_API_BASE}${path}`, { method: 'DELETE' }),
};

// Convenience functions for common dashboard operations
export const dashboardService = {
  getUserInfo: () => dashboardApi.get('/user-info'),
  getStats: () => dashboardApi.get('/stats'),
  getRecentActivity: () => dashboardApi.get('/recent-activity'),
};

