const CACHE_NAME = 'app-lider-v6';

const STATIC_ASSETS = [
  './index.html',
  './login.html',
  './style.css',
  './manifest.json',
  './img/logo.png',
  './img/favicon.png',
  './img/iconandroid.png',
  './img/logo512.png',
];

// Instala e faz cache dos assets estáticos
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

// Remove caches antigas ao ativar
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

// Estratégia de fetch
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Chamadas PHP e Supabase → sempre rede (nunca cachear auth/api)
  if (url.pathname.endsWith('.php') || url.hostname.includes('supabase.co')) return;

  // Assets estáticos → cache primeiro, rede como fallback
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached;

      return fetch(event.request).then((response) => {
        // Armazena apenas assets estáticos no cache
        if (response.ok) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        }
        return response;
      }).catch(() => {
        // Offline e não tem cache → retorna index.html como fallback
        if (event.request.mode === 'navigate') {
          return caches.match('./index.html');
        }
      });
    })
  );
});
