@extends('layouts.app')

@section('content')
<div class="container">
    <h3>استيراد شجرة الحسابات (CSV)</h3>
    <form method="POST" action="{{ route('accounts.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label class="form-label">ملف CSV</label>
            <input type="file" name="csv_file" accept=".csv" class="form-control" required>
        </div>
        <button class="btn btn-primary">استيراد</button>
    </form>
</div>
@endsection
