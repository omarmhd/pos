@php $shift = $shift ?? null; @endphp

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">اسم الوردية <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $shift?->name) }}"
               placeholder="مثال: صباحي، مسائي، ليلي" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">عدد الساعات <span class="text-danger">*</span></label>
        <input type="number" name="hours" id="hoursInput"
               class="form-control @error('hours') is-invalid @enderror"
               value="{{ old('hours', $shift?->hours ?? 8) }}" min="1" max="24" required>
        @error('hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">وقت البداية <span class="text-danger">*</span></label>
        <input type="time" name="start_time" id="startTime"
               class="form-control @error('start_time') is-invalid @enderror"
               value="{{ old('start_time', $shift?->start_time ?? '08:00') }}" required>
        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">وقت النهاية <span class="text-danger">*</span></label>
        <input type="time" name="end_time" id="endTime"
               class="form-control @error('end_time') is-invalid @enderror"
               value="{{ old('end_time', $shift?->end_time ?? '16:00') }}" required>
        @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @if($shift)
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="shiftActive"
                   {{ old('is_active', $shift->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="shiftActive">وردية نشطة</label>
        </div>
    </div>
    @endif
</div>

@section('scripts')
<script>
    // Auto-calculate hours from start/end times
    function calcHours() {
        const start = document.getElementById('startTime').value;
        const end   = document.getElementById('endTime').value;
        if (!start || !end) return;

        const [sh, sm] = start.split(':').map(Number);
        const [eh, em] = end.split(':').map(Number);
        let diff = (eh * 60 + em) - (sh * 60 + sm);
        if (diff <= 0) diff += 24 * 60;  // overnight shift

        document.getElementById('hoursInput').value = Math.round(diff / 60);
    }

    document.getElementById('startTime').addEventListener('change', calcHours);
    document.getElementById('endTime').addEventListener('change',   calcHours);
</script>
@endsection
