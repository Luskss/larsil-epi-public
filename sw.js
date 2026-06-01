const CACHE_NAME = 'app-lider-v7';

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

// Instala e faz cache dos assets estáticos. Não chama skipWaiting automaticamente:
// a página decide quando ativar a nova versão (evita quebrar abas abertas).
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
});

// Remove caches antigas ao ativar
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

// Permite que a página force a ativação da nova versão
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

// Estratégia de fetch
self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Só intercepta GET same-origin
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Chamadas PHP → sempre rede (auth/api nunca em cache)
  if (url.pathname.endsWith('.php')) return;

  // Navegação (HTML) → network-first com fallback para cache (evita app preso em versão antiga)
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((response) => {
          if (response && response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put('./index.html', clone));
          }
          return response;
        })
        .catch(() =>
          caches.match(req).then((c) => c || caches.match('./index.html'))
        )
    );
    return;
  }

  // Assets estáticos → cache primeiro, rede como fallback
  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) return cached;
      return fetch(req)
        .then((response) => {
          if (response && response.ok && response.type === 'basic') {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
          }
          return response;
        })
        .catch(() => new Response('', { status: 504, statusText: 'Offline' }));
    })
  );
});
