<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $orders = PurchaseOrder::with('supplier')->orderByDesc('id')->get();
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'order_number' => 'required|string|unique:purchase_orders,order_number',
            'order_date' => 'required|date',
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
        $order = PurchaseOrder::create($validator->validated());
        foreach ($request->items as $item) {
            $order->items()->create($item);
        }
        return response()->json($order->load('items'), 201);
    }

    public function show($id)
    {
        $order = PurchaseOrder::with(['supplier', 'items'])->findOrFail($id);
        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $order = PurchaseOrder::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'order_number' => 'sometimes|string|unique:purchase_orders,order_number,' . $order->id,
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
        return response()->json($order->load('items'));
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
}
