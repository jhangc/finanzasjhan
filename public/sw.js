/*
 * Service worker mínimo de FinControl.
 * Cachea solo el "shell" (JS, iconos, librerías de CDN). Nunca cachea respuestas de datos:
 * las páginas y el JSON siempre van a la red; sin conexión se muestra offline.html.
 */
const CACHE = 'fincontrol-shell-v1';

const SHELL = [
    '/offline.html',
    '/js/app.js',
    '/manifest.json',
    '/icons/icon.svg',
    '/icons/icon-192.png',
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => Promise.allSettled(SHELL.map((url) => cache.add(new Request(url, { mode: url.startsWith('http') ? 'no-cors' : 'same-origin' })))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;

    // Navegación: siempre red; sin conexión, la página offline.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    // Solo el shell se sirve desde caché; todo lo demás (datos) va directo a la red.
    const url = new URL(request.url);
    const isShell = SHELL.some((item) => item === request.url || item === url.pathname);
    if (isShell) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
    }
});
