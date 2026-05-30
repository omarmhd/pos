@extends('layouts.pos')

@section('styles')
<style>
/* ── Shell ─────────────────────────────────────────────── */
.pos-shell {
    display: flex;
    flex-direction: column;
    height: 100dvh;
    overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────── */
.pos-header {
    height: 52px;
    background: #1a2535;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 14px;
    flex-shrink: 0;
    z-index: 10;
}
.pos-header .search-wrap {
    flex: 1;
    max-width: 380px;
}
.pos-header .search-wrap input {
    background: rgba(255,255,255,.10);
    border: 1px solid rgba(255,255,255,.18);
    color: #fff;
    border-radius: 8px;
    padding: 6px 14px;
    width: 100%;
    font-family: inherit;
    font-size: .9rem;
    outline: none;
    transition: background .15s;
}
.pos-header .search-wrap input::placeholder { color: rgba(255,255,255,.45); }
.pos-header .search-wrap input:focus { background: rgba(255,255,255,.18); }

/* ── Customer search dropdown ── */
#customerDropdown > div {
    padding: 8px 12px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.1s;
}
#customerDropdown > div:hover {
    background: #f9f9f9;
}
#customerDropdown > div:last-child {
    border-bottom: none;
}

/* ── Main split ─────────────────────────────────────────────── */
.pos-main {
    flex: 1;
    display: flex;
    overflow: hidden;
    gap: 0;
}

/* ── Products panel ─────────────────────────────────────────────── */
.pos-products {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

/* Category tabs */
.cat-bar {
    display: flex;
    gap: 6px;
    padding: 10px 12px 8px;
    overflow-x: auto;
    flex-shrink: 0;
    scrollbar-width: none;
}
.cat-bar::-webkit-scrollbar { display: none; }
.cat-btn {
    white-space: nowrap;
    padding: 5px 14px;
    border-radius: 20px;
    border: 1.5px solid #dee2e6;
    background: #fff;
    cursor: pointer;
    font-size: .8rem;
    font-family: inherit;
    font-weight: 600;
    color: #555;
    transition: all .15s;
    flex-shrink: 0;
}
.cat-btn:hover { border-color: #3498db; color: #3498db; }
.cat-btn.active { background: #3498db; border-color: #3498db; color: #fff; }

/* Product grid */
.product-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 4px 12px 12px;
    overscroll-behavior: contain;
}
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
}
.p-card {
    background: #fff;
    border-radius: 10px;
    padding: 10px 8px 8px;
    cursor: pointer;
    border: 2px solid transparent;
    text-align: center;
    transition: border-color .12s, transform .1s, box-shadow .12s;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}
.p-card:hover:not(.oos) { border-color: #3498db; box-shadow: 0 3px 10px rgba(52,152,219,.2); }
.p-card:active:not(.oos) { transform: scale(.94); }
.p-card.oos { opacity: .4; cursor: not-allowed; }
.p-card.flash { border-color: #27ae60; background: #f0fdf4; }
.p-img { width: 64px; height: 64px; object-fit: cover; border-radius: 8px; }
.p-img-ph {
    width: 64px; height: 64px; border-radius: 8px;
    background: #f0f2f5; margin: 0 auto;
    display: flex; align-items: center; justify-content: center;
    color: #bbb; font-size: 1.6rem;
}
.p-name { font-size: .72rem; font-weight: 700; line-height: 1.25; margin: 6px 0 3px; color: #333;
    display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.p-price { font-size: .85rem; font-weight: 800; color: #2563eb; }
.p-stock { font-size: .65rem; color: #999; margin-top: 2px; }
#noResults { display: none; }

/* ── Cart panel ─────────────────────────────────────────────── */
.pos-cart {
    width: 360px;
    flex-shrink: 0;
    background: #fff;
    border-inline-start: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: transform .28s cubic-bezier(.4,0,.2,1);
}
.cart-head {
    background: #1a2535;
    color: #fff;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}
.cart-items-wrap {
    flex: 1;
    overflow-y: auto;
    padding: 6px 10px;
    overscroll-behavior: contain;
}
.ci-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 0;
    border-bottom: 1px solid #f0f0f0;
    animation: slideIn .15s ease;
}
@keyframes slideIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:none; } }
.ci-name { flex: 1; min-width: 0; }
.ci-name strong { font-size: .8rem; display: block;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ci-name small { font-size: .68rem; color: #888; }
.ci-controls { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.ci-btn {
    width: 26px; height: 26px; border-radius: 6px;
    border: 1.5px solid #dee2e6; background: #f8f9fa;
    font-size: .85rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .1s;
}
.ci-btn:hover { background: #e9ecef; }
.ci-qty {
    min-width: 28px; text-align: center;
    font-size: .82rem; font-weight: 700;
}
.ci-total { font-size: .8rem; font-weight: 700; min-width: 52px; text-align: end; color: #1a2535; }
.ci-del {
    width: 22px; height: 22px; border-radius: 50%;
    border: none; background: none; color: #dc3545;
    cursor: pointer; font-size: .8rem; padding: 0;
    display: flex; align-items: center; justify-content: center;
    opacity: .6; transition: opacity .1s;
}
.ci-del:hover { opacity: 1; }
.empty-cart {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    height: 100%; color: #bbb; padding: 30px 0;
}

/* Cart footer */
.cart-footer {
    border-top: 2px solid #f0f0f0;
    padding: 10px 12px 12px;
    flex-shrink: 0;
    background: #fafafa;
}
.cf-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; font-size: .85rem; }
.btn-checkout {
    width: 100%; padding: 12px;
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    border: none; border-radius: 10px;
    color: #fff; font-size: 1rem; font-weight: 800;
    font-family: inherit; cursor: pointer;
    transition: opacity .15s, transform .1s;
    margin-top: 8px;
}
.btn-checkout:hover { opacity: .92; }
.btn-checkout:active { transform: scale(.98); }
.btn-checkout:disabled { opacity: .55; cursor: not-allowed; }
.btn-clear {
    width: 100%; padding: 7px;
    background: none; border: 1.5px solid #dee2e6;
    border-radius: 8px; color: #dc3545;
    font-family: inherit; font-size: .82rem;
    font-weight: 600; cursor: pointer; margin-top: 5px;
    transition: background .1s;
}
.btn-clear:hover { background: #fff0f0; }

/* ── Mobile overlay & FAB ─────────────────────────────────────────────── */
.cart-overlay {
    display: none;
    position: fixed; inset: 0; background: rgba(0,0,0,.45);
    z-index: 1040; backdrop-filter: blur(2px);
}
.cart-fab {
    display: none;
    position: fixed; bottom: 20px; inset-inline-end: 20px;
    z-index: 1030; width: 58px; height: 58px;
    background: #27ae60; color: #fff;
    border: none; border-radius: 50%;
    font-size: 1.4rem; cursor: pointer;
    box-shadow: 0 4px 16px rgba(39,174,96,.45);
    transition: transform .1s;
    align-items: center; justify-content: center;
}
.cart-fab:active { transform: scale(.92); }
.fab-badge {
    position: absolute; top: -4px; inset-inline-end: -4px;
    background: #e74c3c; color: #fff;
    border-radius: 50%; min-width: 20px; height: 20px;
    font-size: .65rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    padding: 0 3px;
}

/* ── Success bar ─────────────────────────────────────────────── */
.sale-success-bar {
    display: none;
    background: #d1fae5; border-bottom: 2px solid #10b981;
    padding: 8px 14px; align-items: center; gap: 10px;
    font-size: .85rem; color: #065f46; flex-shrink: 0;
}

/* ── Payment modal ─────────────────────────────────────────────── */
.pm-total-box { text-align: center; background: #f0fdf4; border-radius: 12px; padding: 14px; margin-bottom: 14px; }
.pm-total-box .amount { font-size: 2rem; font-weight: 800; color: #1a2535; }
.quick-btns { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.quick-btn {
    padding: 5px 12px; border-radius: 20px;
    border: 1.5px solid #3498db; background: #fff;
    color: #3498db; font-size: .8rem; font-weight: 700;
    font-family: inherit; cursor: pointer; transition: all .12s;
}
.quick-btn.exact { background: #3498db; color: #fff; }
.quick-btn:hover { background: #3498db; color: #fff; }

/* ── Mobile receipt overlay ─────────────────────────────────── */
#_rcptOverlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: #fff;
    flex-direction: column;
    overflow: hidden;
}
#_rcptOverlay.show { display: flex; }
#_rcptContent {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}
#_rcptActions {
    padding: 12px 16px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

@media print {
    /* Mobile print: hide POS, show only receipt */
    .pos-shell, .cart-fab, .cart-overlay,
    .toast-container, #_rcptActions { display: none !important; }
    #_rcptContent {
        display: block !important;
        position: static !important;
        padding: 0 !important;
        overflow: visible !important;
    }
}

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 991px) {
    .pos-cart {
        position: fixed;
        top: 0; bottom: 0; inset-inline-end: 0;
        z-index: 1050;
        width: min(360px, 92vw);
        transform: translateX(110%);
    }
    [dir="rtl"] .pos-cart { transform: translateX(-110%); }
    .pos-cart.cart-open {
        transform: translateX(0) !important;
    }
    .cart-overlay { display: block; }
    .cart-fab { display: flex; }
    .product-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); }
    .pos-header .search-wrap { max-width: none; }
}
@media (max-width: 480px) {
    .product-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>
@endsection

@section('content')

{{-- Mobile receipt overlay (iOS-safe printing) --}}
<div id="_rcptOverlay">
    <div id="_rcptContent"></div>
    <div id="_rcptActions">
        <button class="btn btn-success flex-grow-1" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> طباعة الفاتورة
        </button>
        <button class="btn btn-outline-secondary" onclick="closeMobileReceipt()">
            <i class="bi bi-x-lg"></i> إغلاق
        </button>
    </div>
</div>

<div class="pos-shell">

    {{-- ── Header ─────────────────────────────── --}}
    <header class="pos-header">
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-light py-1 px-2 flex-shrink-0">
            <i class="bi bi-arrow-right"></i>
        </a>
        <span class="fw-bold text-white d-none d-md-inline flex-shrink-0" style="font-size:.9rem;">
            <i class="bi bi-shop me-1 opacity-75"></i>نقطة البيع
        </span>

        <div class="search-wrap">
            <input type="text" id="searchInput"
                   placeholder="ابحث بالاسم أو امسح الباركود…"
                   autocomplete="off" spellcheck="false">
        </div>

        <span class="text-white-50 small d-none d-md-flex align-items-center gap-1 flex-shrink-0">
            <i class="bi bi-person-circle opacity-75"></i>{{ auth()->user()->name }}
        </span>
        <span id="clock" class="text-white-50 small d-none d-lg-inline flex-shrink-0"></span>
    </header>

    {{-- ── Sale success bar (hidden by default) ─── --}}
    <div class="sale-success-bar" id="saleSuccessBar">
        <i class="bi bi-check-circle-fill text-success fs-5"></i>
        <div class="flex-grow-1">
            <strong id="sSuccInvoice"></strong>
            <span id="sSuccChange" class="ms-2"></span>
        </div>
        <button class="btn btn-sm btn-success" onclick="newSale()">
            <i class="bi bi-plus-circle me-1"></i>بيع جديد
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="rePrint()">
            <i class="bi bi-printer me-1"></i>إعادة طباعة
        </button>
    </div>

    {{-- ── Main ─────────────────────────────── --}}
    <div class="pos-main">

        {{-- Products panel --}}
        <div class="pos-products">

            {{-- Category tabs --}}
            <div class="cat-bar" id="catBar">
                <button class="cat-btn active" data-cat="" onclick="filterCat(this,'')">
                    <i class="bi bi-grid-3x3-gap-fill me-1"></i>الكل
                </button>
                @foreach($categories as $cat)
                <button class="cat-btn" data-cat="{{ $cat->id }}" onclick="filterCat(this, +this.dataset.cat)">
                    {{ $cat->name }}
                </button>
                @endforeach
            </div>

            {{-- Product grid --}}
            <div class="product-scroll">
                <div class="product-grid" id="pgrid">
                    @foreach($products as $p)
                    @php $pIsOos = !$allowNegStock && $p->quantity <= 0; @endphp
                    <div class="p-card {{ $pIsOos ? 'oos' : '' }}"
                         data-id="{{ $p->id }}"
                         data-cat="{{ $p->category_id }}"
                         data-name="{{ mb_strtolower($p->name) }}"
                         data-barcode="{{ $p->barcode ?? '' }}"
                         onclick="addToCart(+this.dataset.id)">
                        @if($p->image)
                        <img src="{{ asset('storage/'.$p->image) }}" class="p-img" alt="" loading="lazy">
                        @else
                        <div class="p-img-ph"><i class="bi bi-box-seam"></i></div>
                        @endif
                        <div class="p-name">{{ $p->name }}</div>
                        <div class="p-price">{{ number_format($p->selling_price, 2) }}</div>
                        <div class="p-stock">
                            @if($p->quantity <= 0)
                                <span class="text-danger">نفذ</span>
                            @elseif($p->quantity <= ($p->min_quantity ?? 5))
                                <span class="text-warning">{{ $p->quantity }} متبقي</span>
                            @else
                                {{ $p->quantity }} قطعة
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                <div id="noResults" class="text-center text-muted py-5">
                    <i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>
                    لا توجد نتائج
                </div>
            </div>
        </div>

        {{-- ── Cart panel ─────────────────────────── --}}
        <aside class="pos-cart" id="posCart">
            <div class="cart-head">
                <i class="bi bi-cart3 fs-5"></i>
                <span class="fw-bold flex-grow-1">السلة</span>
                <span class="badge bg-warning text-dark" id="cartCountBadge">0</span>
                <button class="btn btn-sm btn-link text-white p-0 ms-2 d-lg-none" onclick="closeCart()">
                    <i class="bi bi-x-lg fs-5"></i>
                </button>
            </div>

            {{-- Customer & Credit --}}
            <div class="px-2 pt-2 pb-1" style="border-bottom:1px solid #f0f0f0; flex-shrink:0;">
                <div class="position-relative mb-1">
                    <input type="text" id="customerSearch" class="form-control form-control-sm"
                           placeholder="ابحث عن زبون..." autocomplete="off">
                    <select id="customerSel" class="form-select form-select-sm d-none">
                        <option value="">— بيع نقدي —</option>
                    </select>
                    <div id="customerDropdown" class="position-absolute w-100 bg-white border shadow-sm rounded"
                         style="display:none; top:100%; z-index:1000; max-height:250px; overflow-y:auto;">
                    </div>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="creditToggle" onchange="onCreditToggle()">
                    <label class="form-check-label small" for="creditToggle">بيع آجل (على الحساب)</label>
                </div>
            </div>

            {{-- Cart items --}}
            <div class="cart-items-wrap" id="cartItemsWrap">
                <div class="empty-cart" id="emptyCart">
                    <i class="bi bi-cart3 fs-1 mb-2"></i>
                    <span>السلة فارغة</span>
                </div>
            </div>

            {{-- Cart footer --}}
            <div class="cart-footer">
                <div class="cf-row">
                    <span class="text-muted">المجموع الفرعي:</span>
                    <strong id="subtotalDisp">0.00 {{ $posSettings['currencySymbol'] }}</strong>
                </div>
                <div class="cf-row">
                    <span class="text-muted">خصم:</span>
                    <input id="discountInput" type="number" min="0" step="0.01" value="0"
                           class="form-control form-control-sm text-center"
                           style="width:85px" oninput="updateTotals()">
                </div>
                <div class="cf-row" id="taxRow" style="display:none;">
                    <span class="text-muted">ضريبة (<span id="vatRateDisp">0</span>%):</span>
                    <strong id="taxDisp" class="text-warning">0.00</strong>
                </div>
                <div class="cf-row" style="font-size:1rem; margin-bottom:6px;">
                    <strong>الإجمالي:</strong>
                    <strong class="text-primary" id="totalDisp" style="font-size:1.1rem;">0.00 {{ $posSettings['currencySymbol'] }}</strong>
                </div>

                <select id="payMethodSel" class="form-select form-select-sm mb-1">
                    <option value="cash"><i class="bi bi-cash"></i> نقدي</option>
                    <option value="card">بطاقة بنكية</option>
                    <option value="mobile_wallet">محفظة إلكترونية</option>
                </select>

                <button class="btn-checkout" id="checkoutBtn" onclick="checkout()">
                    <i class="bi bi-check-circle-fill me-1"></i> إتمام البيع
                    <small class="d-block opacity-70" style="font-size:.68rem; font-weight:400; margin-top:1px;">F2</small>
                </button>
                <button class="btn-clear" onclick="clearCart()">
                    <i class="bi bi-x-circle me-1"></i>إلغاء (F1)
                </button>
            </div>
        </aside>
    </div>{{-- end pos-main --}}
</div>{{-- end pos-shell --}}

{{-- Overlay for mobile cart --}}
<div class="cart-overlay" id="cartOverlay" style="display:none;" onclick="closeCart()"></div>

{{-- FAB (mobile) --}}
<button class="cart-fab" id="cartFab" onclick="openCart()" aria-label="فتح السلة">
    <i class="bi bi-cart3"></i>
    <span class="fab-badge" id="fabBadge" style="display:none;">0</span>
</button>

{{-- ── Payment confirmation modal ────────────────────────── --}}
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-cash-coin me-1"></i>تأكيد الدفع</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pb-2">

                <div class="pm-total-box">
                    <div class="text-muted small">الإجمالي المطلوب</div>
                    <div class="amount" id="pmTotal">0.00</div>
                    <div class="text-muted small">{{ $posSettings['currencySymbol'] }}</div>
                </div>

                <div id="pmCashSection">
                    <label class="form-label small fw-semibold mb-1">المبلغ المستلم</label>
                    <input id="pmPaid" type="number" min="0" step="0.01"
                           class="form-control form-control-lg text-center fw-bold"
                           oninput="calcChange()" style="font-size:1.4rem;">
                    <div class="quick-btns" id="quickBtns"></div>
                    <div class="mt-2">
                        <label class="form-label small fw-semibold mb-1">الباقي للعميل</label>
                        <input id="pmChange" type="text" readonly
                               class="form-control text-center fw-bold"
                               style="font-size:1.1rem; background:#f8f9fa;">
                    </div>
                </div>

                <div id="pmCreditSection" class="d-none">
                    <div class="alert alert-info py-2 small mb-0 text-center">
                        <i class="bi bi-info-circle me-1"></i>
                        بيع آجل — سيُضاف للذمم المدينة
                    </div>
                </div>
            </div>
            <div class="modal-footer pt-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success px-4" id="confirmSaleBtn" onclick="completeSale()">
                    <i class="bi bi-check-lg me-1"></i> تأكيد
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@php
$productsData = $products->map(fn($p) => [
    'id'      => $p->id,
    'name'    => $p->name,
    'price'   => (float) $p->selling_price,
    'qty'     => (int) $p->quantity,
    'barcode' => $p->barcode ?? '',
    'cat'     => $p->category_id,
]);
@endphp
{{-- Hidden data containers — Blade escapes into HTML text nodes; JS reads via textContent --}}
<div id="__posProducts" style="display:none">{{ json_encode($productsData) }}</div>
<div id="__posSettings" style="display:none">{{ json_encode($posSettings) }}</div>

@section('scripts')
<script>
// ── Data (read from hidden HTML text nodes — no Blade in JS context) ──────
const PRODUCTS     = JSON.parse(document.getElementById('__posProducts').textContent);
const POS_SETTINGS = JSON.parse(document.getElementById('__posSettings').textContent);
const CUR          = POS_SETTINGS.currencySymbol || 'ج.م';

// ── State ─────────────────────────────────────────
let cart        = [];
let lastReceipt = null;
let activeCat   = '';
let payModal, toastEl, toastInst;

// ── Init ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    payModal  = new bootstrap.Modal(document.getElementById('payModal'));
    toastEl   = document.getElementById('posToast');
    toastInst = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2500 });

    // Focus search only on non-touch (desktop barcode scanners/keyboards)
    focusSearch();

    // Clock
    const clock = document.getElementById('clock');
    if (clock) {
        const tick = () => {
            const now = new Date();
            clock.textContent = now.toLocaleTimeString('ar-EG', { hour:'2-digit', minute:'2-digit' });
        };
        tick(); setInterval(tick, 1000);
    }

    // Discount input
    document.getElementById('discountInput').addEventListener('input', updateTotals);

    // Keyboard shortcuts
    document.addEventListener('keydown', e => {
        if (e.key === 'F2') { e.preventDefault(); checkout(); }
        if (e.key === 'F1') { e.preventDefault(); clearCart(); }
        if (e.key === 'Escape') {
            closeCart();
            bootstrap.Modal.getInstance(document.getElementById('payModal'))?.hide();
        }
    });

    // Search: always return focus to search on keypress unless modal open or in customer search
    document.addEventListener('keydown', e => {
        const search = document.getElementById('searchInput');
        const customerSearch = document.getElementById('customerSearch');

        // لا تركز على حقل المنتجات إذا كنت تكتب في حقل الزبائن
        if (e.target === search || e.target === customerSearch) return;
        if (customerSearch && customerSearch.contains(e.target)) return;

        const modal = document.querySelector('.modal.show');
        if (modal) return;
        if (e.key.length === 1 && !e.ctrlKey && !e.altKey) {
            focusSearch();
        }
    });

    // Search input handler
    const searchEl = document.getElementById('searchInput');
    let searchTimer;
    searchEl.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilter, 80);
    });

    // Barcode scanner: Enter on search field
    searchEl.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const val = searchEl.value.trim();
        if (!val) return;
        const p = PRODUCTS.find(x => x.barcode === val);
        if (p) {
            addToCart(p.id);
            searchEl.value = '';
            applyFilter();
        } else {
            // Server lookup for unlisted products
            fetch(`/products/barcode/${encodeURIComponent(val)}`)
                .then(r => r.ok ? r.json() : null)
                .then(data => {
                    if (data?.id) {
                        PRODUCTS.push({ id: data.id, name: data.name, price: parseFloat(data.selling_price),
                            qty: data.quantity, barcode: data.barcode ?? '', cat: data.category_id });
                        addToCart(data.id);
                        searchEl.value = '';
                        applyFilter();
                    } else {
                        showToast('الباركود غير موجود', 'danger');
                        searchEl.select();
                    }
                });
        }
    });
});

// ── Filter ────────────────────────────────────────
function filterCat(btn, catId) {
    activeCat = catId ? String(catId) : '';
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilter();
}

function applyFilter() {
    const s = document.getElementById('searchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.p-card');
    let visible = 0;
    cards.forEach(card => {
        const catMatch = !activeCat || card.dataset.cat === activeCat;
        const textMatch = !s || card.dataset.name.includes(s) || card.dataset.barcode.includes(s);
        const show = catMatch && textMatch;
        card.classList.toggle('d-none', !show);
        if (show) visible++;
    });
    document.getElementById('noResults').style.display = visible === 0 ? 'block' : 'none';
}

// ── Cart ──────────────────────────────────────────
function addToCart(productId) {
    const p = PRODUCTS.find(x => x.id === productId);
    if (!p) return;
    if (!POS_SETTINGS.allowNegStock && p.qty <= 0) { showToast('المنتج نفذ من المخزون', 'warning'); return; }

    const item = cart.find(x => x.id === productId);
    if (item) {
        if (!POS_SETTINGS.allowNegStock && item.qty >= p.qty) { showToast('لا يوجد مخزون كافٍ', 'warning'); return; }
        item.qty++;
    } else {
        cart.push({ id: p.id, name: p.name, price: p.price, qty: 1, maxQty: p.qty });
    }

    renderCart();
    flashCard(productId);
    focusSearch();
}

function flashCard(id) {
    const card = document.querySelector(`.p-card[data-id="${id}"]`);
    if (!card) return;
    card.classList.add('flash');
    setTimeout(() => card.classList.remove('flash'), 300);
}

function changeQty(idx, delta) {
    if (!cart[idx]) return;
    const newQty = cart[idx].qty + delta;
    if (newQty <= 0) { removeItem(idx); return; }
    if (!POS_SETTINGS.allowNegStock && newQty > cart[idx].maxQty) { showToast('لا يوجد مخزون كافٍ', 'warning'); return; }
    cart[idx].qty = newQty;
    renderCart();
}

function removeItem(idx) {
    cart.splice(idx, 1);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    cart = [];
    document.getElementById('discountInput').value = 0;
    document.getElementById('customerSel').value = '';
    document.getElementById('creditToggle').checked = false;
    document.getElementById('payMethodSel').value = 'cash';
    document.getElementById('payMethodSel').disabled = false;
    renderCart();
    focusSearch();
}

function renderCart() {
    const wrap = document.getElementById('cartItemsWrap');
    const empty = document.getElementById('emptyCart');

    if (cart.length === 0) {
        wrap.innerHTML = '';
        wrap.appendChild(empty);
        empty.style.display = 'flex';
    } else {
        const html = cart.map((item, i) => `
        <div class="ci-row">
            <div class="ci-name">
                <strong>${esc(item.name)}</strong>
                <small>${item.price.toFixed(2)} ${CUR} / قطعة</small>
            </div>
            <div class="ci-controls">
                <button class="ci-btn" onclick="changeQty(${i},-1)">−</button>
                <span class="ci-qty">${item.qty}</span>
                <button class="ci-btn" onclick="changeQty(${i},1)">+</button>
                <span class="ci-total">${(item.qty * item.price).toFixed(2)}</span>
                <button class="ci-del" onclick="removeItem(${i})"><i class="bi bi-x"></i></button>
            </div>
        </div>`).join('');
        wrap.innerHTML = html;
    }

    updateTotals();
}

// ── VAT helper ────────────────────────────────────
function calcTax(afterDiscount) {
    if (!POS_SETTINGS.vatEnabled || afterDiscount <= 0) return 0;
    if (POS_SETTINGS.vatInclusive) {
        return Math.round((afterDiscount - afterDiscount / (1 + POS_SETTINGS.vatRate / 100)) * 100) / 100;
    }
    return Math.round(afterDiscount * POS_SETTINGS.vatRate / 100 * 100) / 100;
}

function updateTotals() {
    const subtotal    = cart.reduce((s, i) => s + i.qty * i.price, 0);
    const discRaw     = parseFloat(document.getElementById('discountInput').value) || 0;
    const disc        = Math.min(discRaw, subtotal);
    const afterDisc   = Math.max(0, subtotal - disc);
    const tax         = calcTax(afterDisc);
    const total       = afterDisc + tax;
    const count       = cart.reduce((s, i) => s + i.qty, 0);

    document.getElementById('subtotalDisp').textContent   = subtotal.toFixed(2) + ' ' + CUR;

    // Discount ceiling warning
    const maxDisc = subtotal * (POS_SETTINGS.maxDiscountPct / 100);
    document.getElementById('discountInput').classList.toggle('is-invalid', discRaw > maxDisc + 0.005);

    // Tax row visibility
    const taxRow = document.getElementById('taxRow');
    if (taxRow) {
        taxRow.style.display = POS_SETTINGS.vatEnabled ? 'flex' : 'none';
        document.getElementById('vatRateDisp').textContent = POS_SETTINGS.vatRate;
        document.getElementById('taxDisp').textContent     = tax.toFixed(2) + ' ' + CUR;
    }

    document.getElementById('totalDisp').textContent      = total.toFixed(2) + ' ' + CUR;
    document.getElementById('cartCountBadge').textContent  = count;

    const fab = document.getElementById('fabBadge');
    fab.textContent = count;
    fab.style.display = count > 0 ? 'flex' : 'none';
}

function getTotal() {
    const subtotal  = cart.reduce((s, i) => s + i.qty * i.price, 0);
    const disc      = Math.min(parseFloat(document.getElementById('discountInput').value) || 0, subtotal);
    const afterDisc = Math.max(0, subtotal - disc);
    return afterDisc + calcTax(afterDisc);
}

// ── Credit toggle ─────────────────────────────────
function onCreditToggle() {
    const isCredit = document.getElementById('creditToggle').checked;
    document.getElementById('payMethodSel').disabled = isCredit;
    if (isCredit && !document.getElementById('customerSel').value) {
        showToast('اختر عميلاً للبيع الآجل', 'warning');
        document.getElementById('creditToggle').checked = false;
        document.getElementById('payMethodSel').disabled = false;
    }
}

// ── Checkout ─────────────────────────────────────
function checkout() {
    if (cart.length === 0) { showToast('السلة فارغة', 'warning'); return; }

    const isCredit = document.getElementById('creditToggle').checked;
    if (isCredit && !document.getElementById('customerSel').value) {
        showToast('اختر عميلاً للبيع الآجل', 'danger'); return;
    }

    // Validate discount ceiling before opening modal
    const subtotal = cart.reduce((s, i) => s + i.qty * i.price, 0);
    const disc     = parseFloat(document.getElementById('discountInput').value) || 0;
    const maxDisc  = subtotal * (POS_SETTINGS.maxDiscountPct / 100);
    if (disc > maxDisc + 0.005) {
        showToast('الخصم يتجاوز الحد المسموح به (' + POS_SETTINGS.maxDiscountPct + '%)', 'danger'); return;
    }

    if (isCredit) { completeSale(); return; }

    const total = getTotal();
    document.getElementById('pmTotal').textContent = total.toFixed(2);
    document.getElementById('pmPaid').value = total.toFixed(2);
    document.getElementById('pmChange').value = '0.00 ' + CUR;
    document.getElementById('pmChange').style.color = '#198754';
    buildQuickBtns(total);
    document.getElementById('pmCashSection').classList.remove('d-none');
    document.getElementById('pmCreditSection').classList.add('d-none');

    payModal.show();
    setTimeout(() => {
        const paid = document.getElementById('pmPaid');
        paid.focus(); paid.select();
    }, 220);
}

function buildQuickBtns(total) {
    const rounds = [50, 100, 200, 500, 1000];
    const exact  = Math.ceil(total / 5) * 5;
    const shown  = [...new Set([exact, ...rounds.filter(a => a >= total)])].slice(0, 5);
    document.getElementById('quickBtns').innerHTML = shown.map(a =>
        `<button class="quick-btn ${a === exact ? 'exact' : ''}" onclick="setPaid(${a})">${a}</button>`
    ).join('');
}

function setPaid(amount) {
    document.getElementById('pmPaid').value = amount;
    calcChange();
}

function calcChange() {
    const total  = parseFloat(document.getElementById('pmTotal').textContent) || 0;
    const paid   = parseFloat(document.getElementById('pmPaid').value) || 0;
    const change = paid - total;
    const el = document.getElementById('pmChange');
    if (change >= 0) {
        el.value = change.toFixed(2) + ' ' + CUR;
        el.style.color = '#198754';
    } else {
        el.value = 'غير كافٍ (' + Math.abs(change).toFixed(2) + ')';
        el.style.color = '#dc3545';
    }
}

// ── Complete sale ─────────────────────────────────
async function completeSale() {
    const isCredit  = document.getElementById('creditToggle').checked;
    const subtotal  = cart.reduce((s, i) => s + i.qty * i.price, 0);
    const disc      = parseFloat(document.getElementById('discountInput').value) || 0;
    const afterDisc = Math.max(0, subtotal - disc);
    const tax       = calcTax(afterDisc);
    const total     = parseFloat((afterDisc + tax).toFixed(2));
    const paid      = isCredit ? 0 : (parseFloat(document.getElementById('pmPaid').value) || 0);

    if (!isCredit && paid < total - 0.005) {
        showToast('المبلغ المدفوع أقل من الإجمالي', 'danger'); return;
    }

    const btn = document.getElementById('confirmSaleBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>جارٍ الحفظ…';

    try {
        const res = await fetch('{{ route("pos.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({
                items:          cart.map(i => ({ product_id: i.id, quantity: i.qty, unit_price: i.price })),
                subtotal, discount: disc, tax, total_amount: total,
                is_credit:      isCredit ? 1 : 0,
                customer_id:    document.getElementById('customerSel').value || null,
                payment_method: isCredit ? 'cash' : document.getElementById('payMethodSel').value,
                paid_amount:    paid,
            }),
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
            showToast(data.error || 'حدث خطأ', 'danger');
            return;
        }

        payModal.hide();
        lastReceipt = data.receipt;
        printReceipt(data.receipt);
        showSuccessBar(data);

        // Reset
        cart = [];
        document.getElementById('discountInput').value = 0;
        document.getElementById('customerSel').value = '';
        document.getElementById('creditToggle').checked = false;
        document.getElementById('payMethodSel').value = 'cash';
        document.getElementById('payMethodSel').disabled = false;
        renderCart();
        closeCart();
        focusSearch();

    } catch (err) {
        showToast('خطأ في الاتصال بالخادم', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> تأكيد';
    }
}

// ── focusSearch: only on non-touch (avoids unwanted keyboard on mobile) ──
function focusSearch() {
    if ('ontouchstart' in window) return;
    document.getElementById('searchInput')?.focus();
}

// ── Print receipt ─────────────────────────────────
function printReceipt(r) {
    if (!r) return;
    const html = buildReceiptHTML(r);
    const isTouch = 'ontouchstart' in window || window.innerWidth < 992;

    if (isTouch) {
        // iOS/Android: iframe.contentWindow.print() prints the parent page.
        // Instead: inject receipt into a full-screen overlay, user taps "طباعة".
        const overlay = document.getElementById('_rcptOverlay');
        const content = document.getElementById('_rcptContent');

        // Extract <style> and <body> from the receipt HTML
        const parser  = new DOMParser();
        const doc     = parser.parseFromString(html, 'text/html');
        const style   = doc.querySelector('style')?.outerHTML ?? '';
        // Remove the auto-print script (user taps the button instead)
        doc.querySelectorAll('script').forEach(s => s.remove());
        content.innerHTML = style + doc.body.innerHTML;

        overlay.classList.add('show');
        // Prevent POS scroll-through
        document.querySelector('.pos-shell').style.overflow = 'hidden';
        return;
    }

    // Desktop: hidden iframe (fast, no page reflow)
    let frame = document.getElementById('pFrame');
    if (!frame) {
        frame = document.createElement('iframe');
        frame.id = 'pFrame';
        Object.assign(frame.style, {
            position:'fixed', top:'-9999px', left:'-9999px',
            width:'1px', height:'1px', border:'0', opacity:'0',
        });
        document.body.appendChild(frame);
    }
    frame.contentDocument.open();
    frame.contentDocument.write(html);
    frame.contentDocument.close();
    frame.onload = () => { try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch(e) {} };
}

function closeMobileReceipt() {
    document.getElementById('_rcptOverlay').classList.remove('show');
    document.querySelector('.pos-shell').style.overflow = '';
}

function rePrint() { if (lastReceipt) printReceipt(lastReceipt); }

function buildReceiptHTML(r) {
    const rows = r.items.map(i => `
        <tr>
            <td>${esc(i.name)}</td>
            <td style="text-align:center">${i.qty}</td>
            <td style="text-align:left">${i.price}</td>
            <td style="text-align:left"><b>${i.total}</b></td>
        </tr>`).join('');

    const w = POS_SETTINGS.receiptWidthMm || 80;

    return `<!DOCTYPE html><html lang="ar" dir="rtl"><head>
    <meta charset="UTF-8">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Courier New',monospace;width:${w}mm;margin:0 auto;padding:8px;font-size:11px}
        .hdr{text-align:center;border-bottom:2px dashed #000;padding-bottom:8px;margin-bottom:8px}
        .hdr h2{font-size:17px;margin-bottom:3px}.hdr p{font-size:10px;margin:1px 0}
        .info p{margin:2px 0;font-size:11px}
        table{width:100%;border-collapse:collapse;margin:8px 0}
        th{border-bottom:1px solid #000;padding:3px 0;text-align:right}
        td{padding:3px 0;border-bottom:1px dashed #ccc;font-size:10px}
        .totals{border-top:2px solid #000;margin-top:8px;padding-top:8px}
        .totals p{display:flex;justify-content:space-between;margin:3px 0;font-size:12px}
        .grand{font-size:14px;font-weight:bold;border-top:1px solid #000;padding-top:4px;margin-top:3px}
        .ftr{text-align:center;margin-top:14px;padding-top:8px;border-top:2px dashed #000;font-size:10px}
        @media print{body{width:${w}mm}}
    </style>
    </head><body>
    <div class="hdr">
        <h2>${esc(POS_SETTINGS.storeName)}</h2>
        ${POS_SETTINGS.storeAddress ? `<p>${esc(POS_SETTINGS.storeAddress)}</p>` : ''}
        ${POS_SETTINGS.storePhone   ? `<p>هاتف: ${esc(POS_SETTINGS.storePhone)}</p>` : ''}
        ${POS_SETTINGS.storeTaxNumber ? `<p>رقم ضريبي: ${esc(POS_SETTINGS.storeTaxNumber)}</p>` : ''}
    </div>
    <div class="info">
        <p><b>رقم الفاتورة:</b> ${r.invoice_number}</p>
        <p><b>التاريخ:</b> ${r.date}</p>
        <p><b>الكاشير:</b> ${esc(r.cashier)}</p>
        <p><b>الدفع:</b> ${esc(r.payment_method)}</p>
        ${r.customer ? `<p><b>العميل:</b> ${esc(r.customer)}</p>` : ''}
    </div>
    <table>
        <thead><tr>
            <th>المنتج</th><th style="text-align:center">ك</th>
            <th style="text-align:left">سعر</th><th style="text-align:left">مج</th>
        </tr></thead>
        <tbody>${rows}</tbody>
    </table>
    <div class="totals">
        <p><span>المجموع:</span><span>${r.subtotal} ${CUR}</span></p>
        ${r.has_discount ? `<p><span>خصم:</span><span>- ${r.discount} ${CUR}</span></p>` : ''}
        ${r.has_tax ? `<p><span>ضريبة ${r.tax_rate}%:</span><span>${r.tax} ${CUR}</span></p>` : ''}
        <p class="grand"><span>الإجمالي:</span><span>${r.total} ${CUR}</span></p>
        ${!r.is_credit
            ? `<p><span>المدفوع:</span><span>${r.paid} ${CUR}</span></p>
               <p><span>الباقي:</span><span>${r.change} ${CUR}</span></p>`
            : `<p><span>آجل</span><span>—</span></p>`}
    </div>
    <div class="ftr">
        <p>${esc(POS_SETTINGS.receiptFooter)}</p>
        <p style="margin-top:8px">* * * * * * * *</p>
        @if(config('mizaan.print_footer'))
        <p style="margin-top:6px;font-size:9px;color:#aaa;letter-spacing:0.3px;">{!! config('mizaan.print_footer') !!}</p>
        @endif
    </div>
    <script>window.onload=function(){window.print();}<\/script>
    </body></html>`;
}

// ── UI helpers ─────────────────────────────────────
function showSuccessBar(data) {
    const bar = document.getElementById('saleSuccessBar');
    document.getElementById('sSuccInvoice').textContent = '✓ ' + data.invoice_number;
    const ch = parseFloat(data.change_amount);
    document.getElementById('sSuccChange').textContent = ch > 0 ? 'الباقي: ' + ch.toFixed(2) + ' ' + CUR : '';
    bar.style.display = 'flex';
}

function newSale() {
    document.getElementById('saleSuccessBar').style.display = 'none';
    lastReceipt = null;
    focusSearch();
}

function openCart() {
    document.getElementById('posCart').classList.add('cart-open');
    document.getElementById('cartOverlay').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeCart() {
    document.getElementById('posCart').classList.remove('cart-open');
    document.getElementById('cartOverlay').style.display = 'none';
    document.body.style.overflow = '';
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function showToast(msg, type = 'info') {
    toastEl.className = `toast align-items-center text-bg-${type} border-0`;
    document.getElementById('posToastBody').textContent = msg;
    toastInst.show();
}

// ── Customer search AJAX ─────────────────────────
const customerSearch = document.getElementById('customerSearch');
const customerSel = document.getElementById('customerSel');
const customerDropdown = document.getElementById('customerDropdown');
let customerSearchTimer;

customerSearch.addEventListener('input', e => {
    clearTimeout(customerSearchTimer);
    const query = e.target.value.trim();

    if (query.length < 1) {
        customerDropdown.style.display = 'none';
        return;
    }

    customerSearchTimer = setTimeout(() => {
        fetch(`{{ route('pos.customers.search') }}?q=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(customers => {
                if (!customers.length) {
                    customerDropdown.innerHTML = '<div class="p-2 text-muted text-center">لم يتم العثور على نتائج</div>';
                    customerDropdown.style.display = 'block';
                    return;
                }
                customerDropdown.innerHTML = customers.map(c =>
                    `<div class="p-2 cursor-pointer border-bottom" onclick="selectCustomer(${c.id}, '${esc(c.name)}', ${parseFloat(c.credit_limit)})"
                          style="cursor:pointer; transition:background 0.1s">
                        <strong>${esc(c.name)}</strong>
                        ${c.phone ? `<br><small class="text-muted">${esc(c.phone)}</small>` : ''}
                        <span class="float-start text-success small">ح: ${parseFloat(c.credit_limit).toFixed(2)}</span>
                    </div>`
                ).join('');
                customerDropdown.style.display = 'block';
            })
            .catch(err => {
                console.error('Customer search error:', err);
                customerDropdown.innerHTML = '<div class="p-2 text-danger">خطأ في البحث</div>';
                customerDropdown.style.display = 'block';
            });
    }, 300);
});

function selectCustomer(id, name, limit) {
    customerSel.value = id;
    customerSearch.value = name;
    customerSearch.dataset.limit = limit;
    customerDropdown.style.display = 'none';
}

customerSearch.addEventListener('focus', e => {
    if (e.target.value.length > 0) {
        customerDropdown.style.display = 'block';
    }
});

document.addEventListener('click', e => {
    if (!e.target.closest('.position-relative')) {
        customerDropdown.style.display = 'none';
    }
});
</script>
@endsection
