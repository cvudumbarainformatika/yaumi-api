<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;  // Add this at the top with other uses

class LaporanController extends Controller
{
    public function labaRugi(Request $request)
    {
        $start = $request->input('start_date') . ' 00:00:00';
        $end = $request->input('end_date') . ' 23:59:59';

       

        // Total Penjualan
        // $totalPenjualan = DB::table('sales')
        //     ->whereBetween('created_at', [$start, $end])
        //     ->where('status', 'completed')
        //     ->sum('total');

        $penjualan = DB::table('sales')
          ->whereBetween('created_at', [$start, $end])
          ->where('status', 'completed') // jika kamu filtering completed juga
          ->selectRaw("
              SUM(CASE WHEN payment_method = 'credit' THEN total ELSE 0 END) as total_kredit,
              SUM(CASE WHEN payment_method != 'credit' THEN total ELSE 0 END) as total_tunai
          ")
          ->first();

        $totalPenjualanTunai = $penjualan->total_tunai;
        $totalPenjualanKredit = $penjualan->total_kredit;

        $totalPenjualan = $totalPenjualanTunai + $totalPenjualanKredit;

        // Total Retur Penjualan
        $totalRetur = DB::table('return_penjualans')
            ->whereBetween('tanggal', [$start, $end])
            ->sum('total');

        // Harga Pokok Penjualan (dari sales_items)
        $hpp = DB::table('sales_items')
            ->join('sales', 'sales_items.sales_id', '=', 'sales.id')
            ->join('products', 'sales_items.product_id', '=', 'products.id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->select(DB::raw("
                SUM(
                    sales_items.qty * 
                    CASE 
                        WHEN sales_items.harga_modal > 0 
                        THEN sales_items.harga_modal 
                        ELSE products.hargabeli 
                    END
                ) as total_hpp
            "))
            ->value('total_hpp');

        // 4. Total Biaya Operasional (dari cash_flows yang bukan transaksi penjualan/pembelian/hutang/piutang)
        // $biayaOperasional = DB::table('cash_flows')
        //     ->where('tipe', 'out')
        //     ->whereBetween('tanggal', [$start, $end])
        //     ->sum('jumlah');
        // Hitungan
        $pendapatanBersih = $totalPenjualan - $totalRetur;
        $labaKotor = $pendapatanBersih - $hpp;
        $labaBersih = $labaKotor - 0;

        return response()->json([
            'pendapatan' => [
                'penjualan_tunai' => $totalPenjualanTunai,
                'penjualan_kredit' => $totalPenjualanKredit,
                'total_penjualan' => $totalPenjualan,
                'retur_penjualan' => $totalRetur,
                'pendapatan_bersih' => $pendapatanBersih,
            ],
            'hpp' => [
                'hpp' => $hpp,
                'laba_kotor' => $labaKotor,
            ],
            'operasional' => [
                'biaya_operasional' => 0,
            ],
            'laba_bersih' => $labaBersih,
        ]);
    }

    public function cashFlows(Request $request)
    {
        $start = $request->input('start_date');
        // $end = $request->input('end_date') . ' 23:59:59';

        $userId = $request->input('q');


        $penjualan = DB::table('sales')
          ->whereDate('created_at', $start)
          ->where('status', 'completed') // jika kamu filtering completed juga
          ->when($userId, function ($query, $userId) {
            return  $query->where('sales.cashier_id', '=', "{$userId}"); // jika kamu filtering completed juga
          })
          ->selectRaw("
              SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END) as total_tunai
          ")
          ->first();

        $keluar = self::getCashFlows('out', $start, null, $userId);
        $masuk = self::getCashFlows('in', $start, null, $userId);

        $penjualanTunai = (float) $penjualan->total_tunai;
        // Hitung total akhir
        $totalSemua = $penjualanTunai + $masuk['total'] - $keluar['total'];

        return response()->json([
            'penjualan' => [
                'penjualan_tunai' => $penjualanTunai,
            ],
            'operasional' => [
                'keluar' => [
                    'total' => $keluar['total'],
                    'items' => $keluar['items'],
                ],
                'masuk' => [
                    'total' => $masuk['total'],
                    'items' => $masuk['items'],
                ],
            ],
            'total_semua' => $totalSemua
        ]);
    }

    static function getCashFlows($tipe, $start, $end = null, $userId = null)
    {
        $query = DB::table('cash_flows')
        ->select('cash_flows.*', 'kasir.name as kasir_name', 'user.name as user_name')
        ->leftJoin('users as kasir', 'cash_flows.kasir_id', '=', 'kasir.id')
        ->leftJoin('users as user', 'cash_flows.user_id', '=', 'user.id')
        // ->whereBetween('cash_flows.tanggal', [$start, $end])
        ->where('cash_flows.tanggal', $start)
        ->when($userId, fn($q) => $q->where('cash_flows.kasir_id', $userId))
        ->where('cash_flows.tipe', $tipe);

        $items = $query->get();
        $total = $items->sum(fn($item) => (float) $item->jumlah);

        return [
            'total' => $total,
            'items' => $items
        ];
    }
}

