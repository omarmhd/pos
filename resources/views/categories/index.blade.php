@extends('layouts.app')

@section('page-title', 'الفئات')

@section('content')
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-tags"></i> قائمة الفئات</h5>
            <a href="{{ route('categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> إضافة فئة جديدة
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover dt-table" style="width:100%">
                    <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>الوصف</th>
                        <th>عدد المنتجات</th>
                        <th>إجراءات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($categories as $category)
                        <tr>
                            <td>{{ $category->id }}</td>
                            <td><strong>{{ $category->name }}</strong></td>
                            <td>{{ $category->description ?? '-' }}</td>
                            <td><span class="badge bg-primary">{{ $category->products_count }}</span></td>
                            <td>
                                <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm btn-primary btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('categories.destroy', $category) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger btn-action"
                                            onclick="return confirm('هل أنت متأكد؟')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
