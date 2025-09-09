export const API_BASE = '/api/v1';

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
};

