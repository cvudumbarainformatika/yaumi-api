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
        $start = $request->input('start_date');
        $end = $request->input('end_date');

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
        $biayaOperasional = DB::table('cash_flows')
            ->where('tipe', 'out')
            ->whereBetween('tanggal', [$start, $end])
            ->sum('jumlah');
        // Hitungan
        $pendapatanBersih = $totalPenjualan - $totalRetur;
        $labaKotor = $pendapatanBersih - $hpp;
        $labaBersih = $labaKotor - $biayaOperasional;

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
                'biaya_operasional' => $biayaOperasional,
            ],
            'laba_bersih' => $labaBersih,
        ]);
    }

}
