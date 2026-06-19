/**
 * Service Worker — الميزان POS Offline Mode
 *
 * Strategy:
 *   - POS page (/pos): Cache-First (serve cached version when offline)
 *   - POS assets (CSS/JS/images): Cache-First
 *   - POST /pos (sale submission): Queue in IndexedDB when offline → sync when online
 *   - All other requests: Network-First
 */

const CACHE_NAME    = 'mizaan-pos-v2';
const SYNC_TAG      = 'mizaan-pos-sync';
const OFFLINE_QUEUE = 'offline-sales-queue';

// Resources to pre-cache for offline POS
const PRE_CACHE = [
    '/pos',
    '/offline',
];

// ── Install ───────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRE_CACHE).catch(() => {}))
            .then(() => self.skipWaiting())
    );
});

// ── Activate ──────────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys
                .filter(k => k !== CACHE_NAME)
                .map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ── Fetch ─────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Only handle same-origin requests
    if (url.origin !== location.origin) return;

    // POST /pos → sale submission: queue offline if needed
    if (event.request.method === 'POST' && url.pathname === '/pos') {
        event.respondWith(handleSalePost(event.request.clone()));
        return;
    }

    // GET /pos → Network-First (تحميل أحدث صفحة برمز CSRF طازج؛ الكاش للطوارئ فقط)
    // ملاحظة: الكاش-أولًا كان يقدّم صفحة قديمة برمز CSRF منتهٍ → خطأ 419 "Page Expired"
    // عند حفظ البيع. الشبكة-أولًا تضمن رمزًا صالحًا دائمًا عند توفّر الاتصال.
    if (event.request.method === 'GET' && url.pathname === '/pos') {
        event.respondWith(
            fetch(event.request)
                .then(resp => {
                    const clone = resp.clone();
                    caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                    return resp;
                })
                .catch(() => caches.match(event.request)
                    .then(cached => cached || caches.match('/offline')))
        );
        return;
    }

    // Static assets (CSS, JS, fonts, images) → Cache-First
    if (['style', 'script', 'font', 'image'].includes(
        event.request.destination
    )) {
        event.respondWith(
            caches.match(event.request).then(cached =>
                cached || fetch(event.request).then(resp => {
                    if (resp.ok) {
                        const clone = resp.clone();
                        caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                    }
                    return resp;
                })
            )
        );
        return;
    }

    // All other GET requests → Network-First
    event.respondWith(
        fetch(event.request).catch(() =>
            caches.match(event.request)
                .then(c => c || new Response('{"error":"offline"}', {
                    status: 503,
                    headers: { 'Content-Type': 'application/json' }
                }))
        )
    );
});

// ── Background Sync ───────────────────────────────────────────────────────────
self.addEventListener('sync', (event) => {
    if (event.tag === SYNC_TAG) {
        event.waitUntil(syncOfflineSales());
    }
});

// ── Helpers ───────────────────────────────────────────────────────────────────

async function handleSalePost(request) {
    try {
        // Try online first
        const resp = await fetch(request);
        return resp;
    } catch {
        // Offline: save to IndexedDB queue
        const body = await request.text();
        await enqueueOfflineSale({
            url:     request.url,
            method:  'POST',
            headers: Object.fromEntries(request.headers.entries()),
            body:    body,
            queued_at: Date.now(),
        });

        // Return a synthetic "queued" response so POS JS knows what happened
        return new Response(
            JSON.stringify({ offline: true, message: 'تم حفظ الفاتورة في الذاكرة المحلية — ستُرسَل عند استعادة الاتصال' }),
            { status: 202, headers: { 'Content-Type': 'application/json' } }
        );
    }
}

async function syncOfflineSales() {
    const db    = await openOfflineDb();
    const tx    = db.transaction(OFFLINE_QUEUE, 'readwrite');
    const store = tx.objectStore(OFFLINE_QUEUE);
    const all   = await idbGetAll(store);

    for (const item of all) {
        try {
            const resp = await fetch(item.url, {
                method:  item.method,
                headers: item.headers,
                body:    item.body,
            });
            if (resp.ok || resp.status === 422) {
                // Success or validation error (don't retry) → remove from queue
                await idbDelete(store, item.id);
            }
        } catch {
            // Still offline — leave in queue
        }
    }
}

function openOfflineDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('mizaan-offline', 1);
        req.onupgradeneeded = (e) => {
            e.target.result.createObjectStore(OFFLINE_QUEUE, { keyPath: 'id', autoIncrement: true });
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror   = (e) => reject(e.target.error);
    });
}

async function enqueueOfflineSale(data) {
    const db    = await openOfflineDb();
    const tx    = db.transaction(OFFLINE_QUEUE, 'readwrite');
    tx.objectStore(OFFLINE_QUEUE).add(data);
    return new Promise((r, j) => { tx.oncomplete = r; tx.onerror = j; });
}

function idbGetAll(store) {
    return new Promise((r, j) => {
        const req = store.getAll();
        req.onsuccess = () => r(req.result);
        req.onerror   = () => j(req.error);
    });
}

function idbDelete(store, id) {
    return new Promise((r, j) => {
        const req = store.delete(id);
        req.onsuccess = r;
        req.onerror   = j;
    });
}
