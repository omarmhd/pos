<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نقطة البيع — بدون اتصال</title>
    <style>
        body { font-family: sans-serif; background: #1a1a2e; color: #eee;
               display: flex; align-items: center; justify-content: center;
               min-height: 100vh; margin: 0; text-align: center; }
        .box { padding: 2rem; max-width: 400px; }
        .icon { font-size: 4rem; margin-bottom: 1rem; }
        h2 { margin-bottom: .5rem; }
        p  { color: #aaa; margin-bottom: 1.5rem; }
        button { background: #e74c3c; color: #fff; border: none; border-radius: 8px;
                 padding: .75rem 2rem; font-size: 1rem; cursor: pointer; }
        .queue { margin-top: 1.5rem; background: rgba(255,255,255,.05);
                 border-radius: 8px; padding: 1rem; font-size: .85rem; color: #aaa; }
    </style>
</head>
<body>
<div class="box">
    <div class="icon">📡</div>
    <h2>لا يوجد اتصال بالإنترنت</h2>
    <p>لا يزال بإمكانك تسجيل المبيعات — ستُحفَظ محلياً وتُرسَل تلقائياً عند استعادة الاتصال.</p>
    <button onclick="location.href='/pos'">العودة لنقطة البيع</button>
    <div class="queue" id="queueInfo">جاري التحقق من الفواتير المحفوظة...</div>
</div>
<script>
// Show pending offline sales count
(async function() {
    try {
        const db = await new Promise((r,j) => {
            const req = indexedDB.open('mizaan-offline',1);
            req.onsuccess = e => r(e.target.result);
            req.onerror   = e => j(e.target.error);
        });
        const tx    = db.transaction('offline-sales-queue','readonly');
        const store = tx.objectStore('offline-sales-queue');
        const count = await new Promise(r => { const q=store.count(); q.onsuccess=()=>r(q.result); });
        document.getElementById('queueInfo').textContent =
            count > 0
                ? `📦 ${count} فاتورة محفوظة — ستُرسَل عند استعادة الاتصال تلقائياً`
                : '✓ لا توجد فواتير معلقة';
    } catch { document.getElementById('queueInfo').textContent = ''; }
})();
</script>
</body>
</html>
