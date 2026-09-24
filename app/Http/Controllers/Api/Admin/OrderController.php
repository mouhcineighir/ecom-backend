<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // List all orders
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['customer', 'items.product']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        // Compute status counts for filter tabs
        $statusCounts = Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statusCounts['all'] = Order::count();

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'status_counts' => $statusCounts,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    // Show one order with full details
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['customer', 'items.product'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    // Update order notes
    public function updateNotes(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $order->update(['notes' => $validated['notes'] ?? null]);

        return response()->json([
            'success' => true,
            'message' => 'Call notes updated successfully.',
            'data' => $order->fresh()->load(['customer', 'items.product']),
        ]);
    }

    // Update order status
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::with('items.product')->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,shipped,delivered,cancelled,returned'],
        ]);

        $newStatus = $validated['status'];
        $currentStatus = $order->status;

        // Define which transitions are allowed from each current status
        $allowedTransitions = [
            'pending'   => ['confirmed', 'cancelled'],
            'confirmed' => ['shipped', 'cancelled', 'returned'],
            'shipped'   => ['delivered', 'returned'],
            'delivered' => ['returned'],
            'cancelled' => [],
            'returned'  => [],
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot change status from \"{$currentStatus}\" to \"{$newStatus}\".",
            ], 422);
        }

        // Active statuses where stock was already deducted upon order placement
        $activeStatuses = ['pending', 'confirmed', 'shipped', 'delivered'];

        DB::transaction(function () use ($order, $currentStatus, $newStatus, $activeStatuses) {
            // If order is cancelled or returned, restore stock back to products
            if (in_array($currentStatus, $activeStatuses) && in_array($newStatus, ['cancelled', 'returned'])) {
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }
            }

            $order->update(['status' => $newStatus]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'data' => $order->fresh()->load(['customer', 'items.product']),
        ]);
    }
}