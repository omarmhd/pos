@extends('layouts.app')
@section('page-title', isset($role) ? 'تعديل الدور: ' . $role->name : 'إنشاء دور جديد')

@section('content')
<form action="{{ isset($role) ? route('roles.update', $role) : route('roles.store') }}"
      method="POST" id="roleForm">
    @csrf
    @if(isset($role)) @method('PUT') @endif

    <div class="row g-4">

        {{-- ── Left column: role info + copy tool ── --}}
        <div class="col-lg-3">

            {{-- Role name --}}
            <div class="card">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-shield-lock"></i> معلومات الدور
                </div>
                <div class="card-body">
                    <label class="form-label fw-semibold">اسم الدور <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="roleName"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $role->name ?? '') }}"
                           placeholder="مثال: supervisor"
                           {{ (isset($role) && $role->name === 'admin') ? 'readonly' : '' }}
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">استخدم حروف إنجليزية صغيرة وشرطة سفلية فقط</small>
                </div>
            </div>

            {{-- Copy from role --}}
            <div class="card mt-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-copy"></i> نسخ صلاحيات من دور
                </div>
                <div class="card-body">
                    <select id="copyFromRole" class="form-select form-select-sm">
                        <option value="">— اختر دوراً للنسخ منه —</option>
                        @foreach($copyFromRoles as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="btnCopy" class="btn btn-outline-secondary btn-sm mt-2 w-100">
                        <i class="bi bi-clipboard-check"></i> تطبيق الصلاحيات
                    </button>
                    <small class="text-muted d-block mt-1">سيُستبدل التحديد الحالي بصلاحيات الدور المختار</small>
                </div>
            </div>

            {{-- Quick actions --}}
            <div class="card mt-3">
                <div class="card-body d-flex flex-column gap-2">
                    <button type="button" id="btnSelectAll" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-check-all"></i> تحديد الكل
                    </button>
                    <button type="button" id="btnClearAll" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-square"></i> إلغاء الكل
                    </button>
                    <div class="mt-1 small text-muted text-center">
                        <span id="selectedCount">0</span> صلاحية محددة
                    </div>
                </div>
            </div>

            {{-- Save --}}
            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> {{ isset($role) ? 'حفظ التعديلات' : 'إنشاء الدور' }}
                </button>
                <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> إلغاء
                </a>
            </div>

        </div>

        {{-- ── Right column: permission matrix ── --}}
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-key"></i> مصفوفة الصلاحيات</span>
                    <input type="text" id="permSearch" class="form-control form-control-sm"
                           style="max-width:220px" placeholder="بحث في الصلاحيات…">
                </div>
                <div class="card-body" style="max-height:72vh; overflow-y:auto;">

                    @foreach(config('permission_groups') as $moduleKey => $module)
                    @php
                        $modulePerms = array_keys($module['permissions']);
                    @endphp
                    <div class="module-block mb-4" data-module="{{ $moduleKey }}">
                        {{-- Module header with select-all toggle --}}
                        <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                            <input type="checkbox" class="form-check-input module-check mt-0"
                                   id="mod_{{ $moduleKey }}"
                                   data-module="{{ $moduleKey }}">
                            <label for="mod_{{ $moduleKey }}" class="fw-semibold mb-0 cursor-pointer user-select-none">
                                <i class="bi {{ $module['icon'] }} text-primary"></i>
                                {{ $module['label'] }}
                            </label>
                            <span class="badge bg-light text-dark border ms-auto module-count"
                                  data-module="{{ $moduleKey }}">0 / {{ count($modulePerms) }}</span>
                        </div>

                        {{-- Permission checkboxes --}}
                        <div class="row g-2 ps-3 permission-list" data-module="{{ $moduleKey }}">
                            @foreach($module['permissions'] as $permKey => $permLabel)
                            <div class="col-md-6 col-xl-4 perm-item" data-label="{{ strtolower($permLabel . ' ' . $permKey) }}">
                                <div class="form-check">
                                    <input class="form-check-input perm-check"
                                           type="checkbox"
                                           name="permissions[]"
                                           value="{{ $permKey }}"
                                           id="perm_{{ str_replace(['.', '_'], '-', $permKey) }}"
                                           data-module="{{ $moduleKey }}"
                                           {{ in_array($permKey, $rolePermissions ?? []) ? 'checked' : '' }}>
                                    <label class="form-check-label"
                                           for="perm_{{ str_replace(['.', '_'], '-', $permKey) }}">
                                        <span class="text-muted" style="font-size:0.7rem;font-family:monospace">{{ $permKey }}</span><br>
                                        <small>{{ $permLabel }}</small>
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach

                </div>
            </div>
        </div>

    </div>
</form>
@endsection

@push('styles')
<style>
.cursor-pointer { cursor: pointer; }
.perm-item.d-none-search { display: none !important; }
.module-block.all-hidden  { display: none !important; }
</style>
@endpush

@section('scripts')
<script>
const ROLE_PERM_URL = '{{ route("roles.permissions-of", ":id") }}';

// ── Count helpers ────────────────────────────────────────────────────────────
function updateModuleCount(moduleKey) {
    const total   = document.querySelectorAll(`.perm-check[data-module="${moduleKey}"]`).length;
    const checked = document.querySelectorAll(`.perm-check[data-module="${moduleKey}"]:checked`).length;
    const badge   = document.querySelector(`.module-count[data-module="${moduleKey}"]`);
    const modChk  = document.querySelector(`#mod_${moduleKey}`);
    if (badge)  badge.textContent = `${checked} / ${total}`;
    if (modChk) modChk.checked = (checked === total && total > 0);
    if (modChk) modChk.indeterminate = (checked > 0 && checked < total);
    updateGlobalCount();
}

function updateGlobalCount() {
    document.getElementById('selectedCount').textContent =
        document.querySelectorAll('.perm-check:checked').length;
}

// ── Module "select all" checkbox ─────────────────────────────────────────────
document.querySelectorAll('.module-check').forEach(function(chk) {
    chk.addEventListener('change', function() {
        const mod = this.dataset.module;
        document.querySelectorAll(`.perm-check[data-module="${mod}"]`).forEach(function(p) {
            if (!p.closest('.perm-item').classList.contains('d-none-search')) {
                p.checked = chk.checked;
            }
        });
        updateModuleCount(mod);
    });
});

// ── Individual permission checkbox ───────────────────────────────────────────
document.querySelectorAll('.perm-check').forEach(function(chk) {
    chk.addEventListener('change', function() {
        updateModuleCount(this.dataset.module);
    });
});

// ── Select / Clear all ───────────────────────────────────────────────────────
document.getElementById('btnSelectAll').addEventListener('click', function() {
    document.querySelectorAll('.perm-check:not(.d-none-search .perm-check)').forEach(function(c) {
        if (!c.closest('.perm-item').classList.contains('d-none-search')) c.checked = true;
    });
    document.querySelectorAll('.module-check').forEach(function(c) {
        updateModuleCount(c.dataset.module);
    });
});

document.getElementById('btnClearAll').addEventListener('click', function() {
    document.querySelectorAll('.perm-check').forEach(function(c) { c.checked = false; });
    document.querySelectorAll('.module-check').forEach(function(c) {
        updateModuleCount(c.dataset.module);
    });
});

// ── Search ────────────────────────────────────────────────────────────────────
document.getElementById('permSearch').addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('.module-block').forEach(function(block) {
        let anyVisible = false;
        block.querySelectorAll('.perm-item').forEach(function(item) {
            const match = !q || item.dataset.label.includes(q);
            item.classList.toggle('d-none-search', !match);
            if (match) anyVisible = true;
        });
        block.classList.toggle('all-hidden', !anyVisible);
    });
});

// ── Copy from role ────────────────────────────────────────────────────────────
document.getElementById('btnCopy').addEventListener('click', function() {
    const roleId = document.getElementById('copyFromRole').value;
    if (!roleId) { alert('اختر دوراً أولاً'); return; }

    fetch(ROLE_PERM_URL.replace(':id', roleId))
        .then(r => r.json())
        .then(function(perms) {
            // Clear all first
            document.querySelectorAll('.perm-check').forEach(function(c) { c.checked = false; });
            // Apply copied permissions
            perms.forEach(function(p) {
                const el = document.querySelector(`.perm-check[value="${p}"]`);
                if (el) el.checked = true;
            });
            // Refresh counts
            document.querySelectorAll('.module-check').forEach(function(c) {
                updateModuleCount(c.dataset.module);
            });
        })
        .catch(function() { alert('حدث خطأ أثناء جلب الصلاحيات'); });
});

// ── Init counts on page load ──────────────────────────────────────────────────
document.querySelectorAll('.module-check').forEach(function(c) {
    updateModuleCount(c.dataset.module);
});
</script>
@endsection
