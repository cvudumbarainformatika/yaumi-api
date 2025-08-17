<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class MetricsController extends Controller
{
    /**
     * Penjualan per hari (default: 30 hari terakhir)
     * Sumber data: sales_items (sum subtotal per DATE(created_at))
     * Query ringan: agregasi + filter rentang tanggal + index created_at
     */
    public function salesDaily(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Jakarta');

        $end   = $request->filled('end_date')
            ? Carbon::parse($request->end_date, $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();

        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date, $tz)->startOfDay()
            : Carbon::now($tz)->subDays(10)->startOfDay();

        // cache 2 menit utk dashboard
        $cacheKey = sprintf('metrics:sales-daily:%s:%s', $start->toDateString(), $end->toDateString());

        $data = Cache::remember($cacheKey, 120, function () use ($start, $end) {
            // Agregasi per hari
            $rows = DB::table('sales_items')
                ->selectRaw('DATE(created_at) as d, SUM(subtotal) as total')
                ->whereBetween('created_at', [$start, $end])
                ->groupByRaw('DATE(created_at)')
                ->orderBy('d')
                ->get();

            // Lengkapi tanggal yang tidak ada (isi 0) agar line chart mulus
            $period = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay());
            $map = collect($rows)->keyBy(fn($r) => $r->d);

            $labels = [];
            $values = [];
            foreach ($period as $day) {
                $key = $day->toDateString();
                $labels[] = $key;
                $values[] = isset($map[$key]) ? (float)$map[$key]->total : 0.0;
            }

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Penjualan (Rp)',
                        'data' => $values,
                        'borderColor'=> '#3B82F6',
                        'tension' => 0.3,
                        'fill' => false
                    ]
                ],
            ];
        });

        return response()->json($data);
    }

    /**
     * Pembelian per minggu (default: 12 minggu terakhir)
     * Sumber data: purchases (sum grand_total per YEARWEEK)
     * ⚠️ Ganti kolom total sesuai skema kamu: grand_total / total / amount
     */
    public function purchasesWeekly(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Jakarta');

        // default 12 minggu terakhir
        // $end   = $request->filled('end_date')
        //     ? Carbon::parse($request->end_date, $tz)->endOfWeek(Carbon::MONDAY)
        //     : Carbon::now($tz)->endOfWeek(Carbon::MONDAY);

        // $start = $request->filled('start_date')
        //     ? Carbon::parse($request->start_date, $tz)->startOfWeek(Carbon::MONDAY)
        //     : Carbon::now($tz)->subWeeks(11)->startOfWeek(Carbon::MONDAY);

        $end   = $request->filled('end_date')
            ? Carbon::parse($request->end_date, $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();

        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date, $tz)->startOfDay()
            : Carbon::now($tz)->subDays(10)->startOfDay();

        $cacheKey = sprintf('metrics:purchases-weekly:%s:%s', $start->toDateString(), $end->toDateString());

        $data = Cache::remember($cacheKey, 120, function () use ($start, $end) {
            // NOTE: ubah "grand_total" ke kolom total milik tabel purchases kamu
            // $rows = DB::table('purchases')
            //     ->selectRaw('YEARWEEK(created_at, 3) as yw, MIN(DATE(created_at)) as week_start, SUM(total) as total')
            //     ->whereBetween('created_at', [$start, $end])
            //     ->groupByRaw('YEARWEEK(created_at, 3)')
            //     ->orderBy('yw')
            //     ->get();

            $rows = DB::table('purchases')
                ->selectRaw('DATE(created_at) as d, SUM(total) as total')
                ->whereBetween('created_at', [$start, $end])
                ->groupByRaw('DATE(created_at)')
                ->orderBy('d')
                ->get();

            // Build label per minggu (pakai week_start dari query)
            // Lengkapi minggu kosong
            $labels = [];
            $values = [];

            // // Buat daftar minggu dari $start s/d $end
            // $weeks = [];
            // $cursor = $start->copy();
            // while ($cursor->lte($end)) {
            //     $weeks[] = [
            //         'yw' => (int)$cursor->isoFormat('GGGGWW'), // ISO year+week (mis: 202534)
            //         'label' => $cursor->isoFormat('GGGG-[W]WW'), // contoh: 2025-W34
            //         'start' => $cursor->toDateString(),
            //     ];
            //     $cursor->addWeek();
            // }

            // // Map hasil query berdasarkan YEARWEEK mode-3 (mendekati ISO week),
            // // perhatikan format bisa beda, jadi fallback by week_start date
            // $byWeek = collect($rows)->keyBy(function ($r) {
            //     // gunakan ISO year-week approx untuk mapping
            //     return (int)Carbon::parse($r->week_start)->isoFormat('GGGGWW');
            // });

            // foreach ($weeks as $w) {
            //     $labels[] = $w['label'];
            //     $values[] = isset($byWeek[$w['yw']]) ? (float)$byWeek[$w['yw']]->total : 0.0;
            // }


             // Lengkapi tanggal yang tidak ada (isi 0) agar line chart mulus
            $period = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay());
            $map = collect($rows)->keyBy(fn($r) => $r->d);

            $labels = [];
            $values = [];
            foreach ($period as $day) {
                $key = $day->toDateString();
                $labels[] = $key;
                $values[] = isset($map[$key]) ? (float)$map[$key]->total : 0.0;
            }

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Pembelian per Hari (Rp)',
                        'data' => $values,
                        'borderColor'=> 'oklch(70.5% 0.213 47.604)',
                        'tension' => 0.3,
                        'fill' => false
                    ]
                ],
            ];
        });

        return response()->json($data);
    }
}
