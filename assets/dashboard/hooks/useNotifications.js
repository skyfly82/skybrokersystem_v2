// Lightweight notification hook with SSE fallback to polling and demo mode
import { useEffect, useRef } from 'react';

export function useNotifications({ token, onMessage, onError, demo = false }) {
  const timerRef = useRef(null);

  useEffect(() => {
    if (!token) return;

    let es;
    let demoTimer;

    const startSSE = () => {
      try {
        es = new EventSource('/api/v1/notifications/stream', { withCredentials: false });
        es.onmessage = (ev) => {
          try { const data = JSON.parse(ev.data); onMessage?.(data); } catch { /* ignore */ }
        };
        es.onerror = () => { es.close(); startPolling(); onError?.(); };
      } catch (_) {
        startPolling();
      }
    };

    const startPolling = () => {
      clearInterval(timerRef.current);
      timerRef.current = setInterval(async () => {
        try {
          const res = await fetch('/api/v1/notifications', { headers: { Authorization: `Bearer ${token}` } });
          if (!res.ok) return;
          const list = await res.json();
          if (Array.isArray(list)) list.forEach((n) => onMessage?.(n));
        } catch (_) { /* ignore */ }
      }, 20000);
    };

    startSSE();

    // Demo welcome toast & heartbeat
    if (demo) {
      onMessage?.({ title: 'Witaj w panelu Sky', body: 'Powiadomienia w czasie rzeczywistym są aktywne.', level: 'info' });
      demoTimer = setInterval(() => {
        onMessage?.({ title: 'Heartbeat', body: 'Połączenie aktywne', level: 'info' });
      }, 60000);
    }

    return () => {
      if (es) es.close();
      clearInterval(timerRef.current);
      if (demoTimer) clearInterval(demoTimer);
    };
  }, [token]);
}

