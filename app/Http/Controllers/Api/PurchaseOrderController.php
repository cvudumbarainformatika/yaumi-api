<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        // Inisialisasi query dasar
        $query = PurchaseOrder::query();
        $query->select('purchase_orders.*', 'suppliers.name as supplier_name');
        $query->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id');
        // Relasi yang perlu di-load
        $query->with(['supplier', 'items.product', 'purchases:id,purchase_order_id']);
        
        // Filter pencarian jika parameter q tidak kosong
        if ($request->filled('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function($q) use ($search) {

                $q->where('unique_code', 'like', "%{$search}%")
                ->orWhere('suppliers.name', 'like', "%{$search}%");
            });
        }
        
        // Filter berdasarkan status jika ada dan bukan 'semua'
        if ($request->filled('status') && $request->status !== 'semua') {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan rentang tanggal jika ada
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('order_date', [$request->start_date, $request->end_date]);
        }
        
       
        
        // Sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        
        // Validasi field sorting untuk mencegah SQL injection
        $allowedSortFields = ['id', 'created_at', 'updated_at', 'order_date', 'status'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy('purchase_orders.' . $sortField, $sortDirection);

        } else {
            $query->orderBy('purchase_orders.created_at', 'desc');
        }
        
        // Pagination
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        // $filterParams = $request->only(['q', 'status', 'supplier_id', 'date_from', 'date_to']);
        $totalCount = (clone $query)->count();
        
        // Lakukan pagination dengan simplePaginate
        $orders = $query->simplePaginate($perPage);
        
        // Buat response yang optimal
        $data = [
            'data' => $orders->items(),
            'meta' => [
                'first' => $orders->url(1),
                'last' => $orders->url(ceil($totalCount / $perPage)),
                'prev' => $orders->previousPageUrl(),
                'next' => $orders->nextPageUrl(),
                'current_page' => $orders->currentPage(),
                'per_page' => (int)$perPage,
                'total' => (int)$totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => (($orders->currentPage() - 1) * $perPage) + 1,
                'to' => min($orders->currentPage() * $perPage, $totalCount),
            ],
        ];
        
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,ordered,received,cancelled',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Membuat kode unik yang lebih pendek (maksimal 20 karakter)
        // Menggunakan hanya 4 karakter terakhir dari uniqid
        $uniqueCode = 'PO-' . date('ymd') . '-' . substr(uniqid(), -4);
        
        // Menambahkan order_date secara otomatis dengan tanggal hari ini
        $validatedData = $validator->validated();
        $validatedData['order_date'] = date('Y-m-d');
        
        $order = PurchaseOrder::create(array_merge($validatedData, ['unique_code' => $uniqueCode]));
        
        foreach ($request->items as $item) {
            $order->items()->create($item);
        }
        
        return response()->json($order->load(['supplier', 'items.product:id,name,barcode']), 201);
    }

    public function show($id)
    {
        $order = PurchaseOrder::with(['supplier', 'items.product:id,name,barcode','purchases:id,purchase_order_id'])->findOrFail($id);
        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $order = PurchaseOrder::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'unique_code' => 'sometimes|string|unique:purchase_orders,unique_code,' . $order->id,
            'order_date' => 'sometimes|date',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,ordered,received,cancelled',
            'items' => 'sometimes|array',
            'items.*.product_id' => 'sometimes|exists:products,id',
            'items.*.quantity' => 'sometimes|integer|min:1',
            'items.*.price' => 'sometimes|numeric|min:0',
            'items.*.total' => 'sometimes|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $order->update($validator->validated());
        if ($request->has('items')) {
            $order->items()->delete();
            foreach ($request->items as $item) {
                $order->items()->create($item);
            }
        }
        return response()->json($order->load(['supplier', 'items.product:id,name,barcode']));
    }

    public function destroy($id)
    {
        $order = PurchaseOrder::findOrFail($id);
        $order->items()->delete();
        $order->delete();
        return response()->json(['message' => 'Purchase order deleted']);
    }

    public function updateItemStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,cancelled,added',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $item = PurchaseOrderItem::findOrFail($id);
        $item->update(['status' => $request->status]);
        return response()->json($item);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,ordered,received,cancelled',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $order = PurchaseOrder::findOrFail($id);
        $order->update(['status' => $request->status]);
        
        return response()->json([
            'message' => 'Status purchase order berhasil diperbarui',
            'data' => $order->load(['supplier', 'items'])
        ]);
    }

    public function receiveItems(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,ordered,received,cancelled',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:purchase_order_items,id',
            'items.*.received_quantity' => 'required|integer|min:0',
            'items.*.notes' => 'nullable|string',
            'items.*.status' => 'required|in:active,cancelled,added',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $order = PurchaseOrder::findOrFail($id);
        
        // Update status dan catatan PO
        $order->update([
            'status' => $request->status,
            'notes' => $request->notes ?? $order->notes
        ]);
        
        // Update item-item PO
        foreach ($request->items as $item) {
            $poItem = PurchaseOrderItem::findOrFail($item['id']);
            $poItem->update([
                'received_quantity' => $item['received_quantity'],
                'notes' => $item['notes'] ?? $poItem->notes,
                'status' => $item['status']
            ]);
        }
        
        return response()->json([
            'message' => 'Penerimaan barang berhasil dicatat',
            'data' => $order->load(['supplier', 'items'])
        ]);
    }
}
