{{--
    Organizational Assignment partial — SAP SU01 / Oracle User-BU equivalent.
    Included in both users/create and users/edit.
    Variables expected: $branches, $warehouses, $terminals, $user (optional for edit).
--}}
<div class="card border-primary mt-4">
    <div class="card-header bg-primary bg-opacity-10 text-primary py-2">
        <i class="bi bi-diagram-3-fill me-2"></i>
        <strong>التعيين التنظيمي</strong>
        <small class="text-muted ms-2">— اختياري: لتقييد المستخدم بفرع/مخزن معيّن</small>
    </div>
    <div class="card-body">

        <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            هذا القسم <strong>اختياري</strong> ويفيد المنشآت متعددة الفروع/المخازن. الغرض منه تحديد <strong>نطاق عمل المستخدم</strong>:
            <ul class="mb-0 mt-1">
                <li><strong>الفرع</strong>: يحصر ما يراه ويُرحّل عليه المستخدم في فرعه فقط (مثل كاشير/محاسب فرع). اتركه فارغًا للمدير/المحاسب العام ليرى كل الفروع.</li>
                <li><strong>المخزن الافتراضي</strong>: المخزن الذي تُخصم/تُضاف منه الكميات تلقائيًا في عمليات هذا المستخدم (شراء، جرد، تسويات).</li>
                <li><strong>نقطة البيع</strong>: تربط الكاشير بجهاز/مخزن بيع محدد (الأعلى أولوية).</li>
            </ul>
            في المنشأة ذات الفرع الواحد يمكن ترك الحقول الثلاثة فارغة.
        </div>

        {{-- Row 1: Branch + Default Warehouse --}}
        <div class="row g-3 mb-3">
            {{-- Branch --}}
            <div class="col-md-6">
                <label class="form-label">
                    <i class="bi bi-building-fill-check text-primary me-1"></i>
                    الفرع المرتبط
                    <small class="text-muted">(يقيّد ما يراه المستخدم)</small>
                </label>
                <select name="branch_id" id="userBranchSelect" class="form-select">
                    <option value="">— بدون قيد فرع (صلاحيات عامة) —</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}"
                            {{ old('branch_id', $user->branch_id ?? '') == $b->id ? 'selected' : '' }}>
                            [{{ $b->code }}] {{ $b->name }}
                            @if($b->type === 'retail') · تجزئة @elseif($b->type === 'wholesale') · جملة @endif
                        </option>
                    @endforeach
                </select>
                <div class="form-text">
                    المدير العام والمحاسب العام: اتركه فارغاً ليرى كل الفروع.
                    الكاشير والمحاسب الفرعي: حدد فرعهم.
                </div>
            </div>

            {{-- Default Warehouse --}}
            <div class="col-md-6">
                <label class="form-label">
                    <i class="bi bi-archive-fill text-success me-1"></i>
                    المخزن الافتراضي
                    <small class="text-muted">(SAP: Default Plant)</small>
                </label>
                <select name="default_warehouse_id" id="userWarehouseSelect" class="form-select">
                    <option value="">— يُحدَّد من إعدادات النظام —</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}"
                            data-branch="{{ $wh->branch_id }}"
                            {{ old('default_warehouse_id', $user->default_warehouse_id ?? '') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                            @if($wh->branch) ({{ $wh->branch->name }}) @endif
                        </option>
                    @endforeach
                </select>
                <div class="form-text">
                    يُستخدم افتراضياً عند: استلام المشتريات، تعديلات المخزون، جلسات الجرد.
                </div>
            </div>
        </div>

        {{-- Row 2: POS Terminal --}}
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">
                    <i class="bi bi-cash-register text-warning me-1"></i>
                    نقطة البيع (Terminal)
                    <small class="text-muted">(تحدد مخزن خصم POS)</small>
                </label>
                <select name="pos_terminal_id" class="form-select">
                    <option value="">— بدون terminal (يستخدم مخزن الفرع) —</option>
                    @foreach($terminals as $pt)
                        <option value="{{ $pt->id }}"
                            {{ old('pos_terminal_id', $user->pos_terminal_id ?? '') == $pt->id ? 'selected' : '' }}>
                            {{ $pt->code }} — {{ $pt->name }}
                            @if($pt->warehouse) ({{ $pt->warehouse->name }}) @endif
                        </option>
                    @endforeach
                </select>
                <div class="form-text">
                    يخصص كاشيراً بعينه لنقطة بيع محددة بمخزنها. أعلى أولوية من المخزن الافتراضي.
                </div>
            </div>
            <div class="col-md-6 d-flex align-items-end pb-1">
                <div class="alert alert-light border small mb-0 p-2 w-100">
                    <strong>أولوية تحديد المخزن عند البيع:</strong><br>
                    <span class="text-danger">1.</span> Terminal المحدد هنا<br>
                    <span class="text-warning">2.</span> المخزن الافتراضي للمستخدم<br>
                    <span class="text-success">3.</span> مخزن الفرع الافتراضي<br>
                    <span class="text-muted">4.</span> مخزن النظام الافتراضي
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// When branch changes → filter warehouse options to show only that branch's warehouses
document.getElementById('userBranchSelect')?.addEventListener('change', function () {
    var selectedBranch = this.value;
    var whSelect = document.getElementById('userWarehouseSelect');
    if (!whSelect) return;
    Array.from(whSelect.options).forEach(function(opt) {
        if (!opt.value) return; // keep the blank option
        opt.hidden = selectedBranch && opt.dataset.branch !== selectedBranch;
    });
    // If current selection is now hidden, reset it
    if (whSelect.selectedOptions[0]?.hidden) {
        whSelect.value = '';
    }
});
</script>
