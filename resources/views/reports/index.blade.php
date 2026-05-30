@extends('layouts.app')

@section('page-title', 'التقارير')

@section('content')
    <div class="row g-4">
        <div class="col-md-4">
            <a href="{{ route('reports.sales') }}" class="text-decoration-none">
                <div class="card hover-card border-primary">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-graph-up fs-1 text-primary mb-3"></i>
                        <h5>تقرير المبيعات</h5>
                        <p class="text-muted">عرض تقارير المبيعات اليومية والشهرية</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.purchases') }}" class="text-decoration-none">
                <div class="card hover-card border-success">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-bag-plus fs-1 text-success mb-3"></i>
                        <h5>تقرير المشتريات</h5>
                        <p class="text-muted">عرض تقارير المشتريات والموردين</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.profit') }}" class="text-decoration-none">
                <div class="card hover-card border-warning">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-currency-dollar fs-1 text-warning mb-3"></i>
                        <h5>تقرير الأرباح</h5>
                        <p class="text-muted">عرض تحليل الأرباح والخسائر</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.inventory') }}" class="text-decoration-none">
                <div class="card hover-card border-info">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-box-seam fs-1 text-info mb-3"></i>
                        <h5>تقرير المخزون</h5>
                        <p class="text-muted">عرض حالة المخزون والقيمة</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.top-products') }}" class="text-decoration-none">
                <div class="card hover-card border-danger">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-star fs-1 text-danger mb-3"></i>
                        <h5>المنتجات الأكثر مبيعاً</h5>
                        <p class="text-muted">عرض المنتجات الأكثر طلباً</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.ar-aging') }}" class="text-decoration-none">
                <div class="card hover-card border-warning">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-people fs-1 text-warning mb-3"></i>
                        <h5>تقادم ذمم العملاء</h5>
                        <p class="text-muted">تحليل الفواتير الآجلة حسب التقادم</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('reports.ap-aging') }}" class="text-decoration-none">
                <div class="card hover-card border-secondary">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-truck fs-1 text-secondary mb-3"></i>
                        <h5>تقادم ذمم الموردين</h5>
                        <p class="text-muted">فواتير الموردين غير المسددة حسب التقادم</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
@endsection
