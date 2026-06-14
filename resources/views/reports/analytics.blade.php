@extends('layouts.app')

@section('page-title', 'الرسوم التحليلية')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-bar-chart-line text-danger"></i> الرسوم التحليلية — آخر ١٢ شهراً</h5>
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> التقارير</a>
</div>

<div class="row g-3">
    <div class="col-lg-12">
        <div class="card"><div class="card-header bg-white"><strong>المبيعات مقابل المشتريات ({{ $cur }})</strong></div>
            <div class="card-body"><canvas id="chSP" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-lg-12">
        <div class="card"><div class="card-header bg-white"><strong>صافي الفرق الشهري (مبيعات − مشتريات)</strong></div>
            <div class="card-body"><canvas id="chNet" height="90"></canvas></div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function(){
    const labels = @json($labels);
    const sales = @json($sales);
    const purchases = @json($purchases);
    const net = sales.map((v,i)=> v - purchases[i]);

    function ready(cb){ if(window.Chart){cb();} else { setTimeout(()=>ready(cb),120); } }
    ready(function(){
        new Chart(document.getElementById('chSP'), {
            type: 'bar',
            data: { labels, datasets: [
                { label:'المبيعات', data: sales, backgroundColor:'rgba(25,135,84,0.6)' },
                { label:'المشتريات', data: purchases, backgroundColor:'rgba(13,110,253,0.6)' },
            ]},
            options: { responsive:true, plugins:{legend:{position:'top'}} }
        });
        new Chart(document.getElementById('chNet'), {
            type: 'line',
            data: { labels, datasets: [
                { label:'صافي الفرق', data: net, borderColor:'#dc3545', backgroundColor:'rgba(220,53,69,0.15)', fill:true, tension:0.3 },
            ]},
            options: { responsive:true, plugins:{legend:{position:'top'}} }
        });
    });
})();
</script>
@endsection
