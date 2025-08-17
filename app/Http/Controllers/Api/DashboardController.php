<?php
// app/Http/Controllers/Api/V1/CompanyController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function penjualan()
    {
        $data = DB::table('sales')
          ->selectRaw("
              COALESCE(SUM(CASE 
                WHEN created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY 
                THEN total ELSE 0 END), 0) AS total_today,
              
              COALESCE(SUM(CASE 
                WHEN created_at >= CURDATE() - INTERVAL 1 DAY AND created_at < CURDATE() 
                THEN total ELSE 0 END), 0) AS total_yesterday,

              COUNT(CASE 
                WHEN created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY 
                THEN 1 END) AS transaksi_today,

              COUNT(CASE 
                WHEN created_at >= CURDATE() - INTERVAL 1 DAY AND created_at < CURDATE() 
                THEN 1 END) AS transaksi_yesterday
          ")
          ->first();

      $growth = 0;
      if ($data->total_yesterday > 0) {
          $growth = round((($data->total_today - $data->total_yesterday) / $data->total_yesterday) * 100, 2);
      }
      $growthTransaksi = 0;
      if ($data->transaksi_yesterday > 0) {
          $growthTransaksi = round((($data->transaksi_today - $data->transaksi_yesterday) / $data->transaksi_yesterday) * 100, 2);
      }

      return response()->json([
          'total_today' => $data->total_today,
          'total_yesterday' => $data->total_yesterday,
          'growth' => $growth,
          'transaksi_today' => $data->transaksi_today,
          'transaksi_yesterday' => $data->transaksi_yesterday,
          'growthTransaksi' => $growthTransaksi
      ]);
    }

    public function cartPenjualan()
    {
      $year = Carbon::now()->year;

      $sales = DB::table('sales')
          ->selectRaw('MONTH(created_at) as month, SUM(total) as total')
          ->whereYear('created_at', $year)
          ->groupByRaw('MONTH(created_at)')
          ->orderByRaw('MONTH(created_at)')
          ->pluck('total', 'month')
          ->toArray();


      $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

      $data = [];
      for ($i = 1; $i <= 12; $i++) {
          $data[] = isset($sales[$i]) ? (float)$sales[$i] : 0;
      }

      // Jika hanya ingin sampai bulan saat ini
      $currentMonth = now()->month;
      $labels = array_slice($labels, 0, $currentMonth);
      $data = array_slice($data, 0, $currentMonth);

      // Return sebagai format ChartJS
      return response()->json([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Penjualan',
                'data' => $data,
            ]]
          ]);
    }
    public function cartPembelian()
    {
      $year = Carbon::now()->year;

      $sales = DB::table('purchases')
          ->selectRaw('MONTH(created_at) as month, SUM(total) as total')
          ->whereYear('created_at', $year)
          ->groupByRaw('MONTH(created_at)')
          ->orderByRaw('MONTH(created_at)')
          ->pluck('total', 'month')
          ->toArray();


      $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

      $data = [];
      for ($i = 1; $i <= 12; $i++) {
          $data[] = isset($sales[$i]) ? (float)$sales[$i] : 0;
      }

      // Jika hanya ingin sampai bulan saat ini
      $currentMonth = now()->month;
      $labels = array_slice($labels, 0, $currentMonth);
      $data = array_slice($data, 0, $currentMonth);

      // Return sebagai format ChartJS
      return response()->json([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Pembelian',
                'data' => $data,
                
            ]]
          ]);
    }


    public function pembelian()
    {
       $data = DB::table('purchases')
            ->selectRaw("
                COALESCE(SUM(CASE 
                    WHEN created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY 
                    THEN total ELSE 0 END), 0) AS total_today,
                
                COALESCE(SUM(CASE 
                    WHEN created_at >= CURDATE() - INTERVAL 1 DAY AND created_at < CURDATE() 
                    THEN total ELSE 0 END), 0) AS total_yesterday,

                COUNT(CASE 
                    WHEN created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY 
                    THEN 1 END) AS transaksi_today,

                COUNT(CASE 
                    WHEN created_at >= CURDATE() - INTERVAL 1 DAY AND created_at < CURDATE() 
                    THEN 1 END) AS transaksi_yesterday
            ")
            ->first();

        // Hitung pertumbuhan nilai total
        $growth = 0;
        if ($data->total_yesterday > 0) {
            $growth = round((($data->total_today - $data->total_yesterday) / $data->total_yesterday) * 100, 2);
        }
        $statusGrowth = $growth > 0 ? 'up' : ($growth < 0 ? 'down' : 'stay');

        // Hitung pertumbuhan jumlah transaksi
        $growthTransaksi = 0;
        if ($data->transaksi_yesterday > 0) {
            $growthTransaksi = round((($data->transaksi_today - $data->transaksi_yesterday) / $data->transaksi_yesterday) * 100, 2);
        }
        $statusTransaksi = $growthTransaksi > 0 ? 'up' : ($growthTransaksi < 0 ? 'down' : 'stay');

        // Hasil response
        return [
            'total_today' => $data->total_today,
            'total_yesterday' => $data->total_yesterday,
            'transaksi_today' => $data->transaksi_today,
            'transaksi_yesterday' => $data->transaksi_yesterday,
            'growth' => $growth,
            'growth_status' => $statusGrowth,
            'growth_transaksi' => $growthTransaksi,
            'growth_transaksi_status' => $statusTransaksi,
        ];
    }


    public function topProductSales()
    {
       $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $topProducts = DB::table('sales_items')
            ->join('products', 'sales_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(sales_items.qty) as total_qty'),
                DB::raw('SUM(sales_items.subtotal) as total_sales')
            )
            ->whereBetween('sales_items.created_at', [$startDate, $endDate])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        return response()->json($topProducts);
    }

    public function activity()
    {
        $activity = UserActivity::with('user')->orderBy('created_at', 'desc')->limit(5)->get();
        return response()->json($activity);
    }
}
