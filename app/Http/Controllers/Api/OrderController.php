<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if ($request->has('phone')) {
            $cleanPhone = preg_replace('/[^\d\+]/', '', (string)$request->phone);
            $request->merge(['phone' => $cleanPhone]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_preorder' => ['nullable', 'boolean'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($validated, $request) {

            // Find existing customer by phone, or create a new one.
            // If they already exist, refresh their info to the latest submitted.
            $customer = Customer::updateOrCreate(
                ['phone' => $validated['phone']],
                [
                    'name' => $validated['name'],
                    'city' => $validated['city'],
                    'address' => $validated['address'],
                    'email' => $validated['email'] ?? null,
                ]
            );

            $isPreorder = !empty($validated['is_preorder']) || $request->boolean('is_restock_request');

            $subtotal = 0;
            $orderItemsData = [];

            foreach ($validated['items'] as $item) {
                $rawProdId = $item['product_id'];
                $numericId = is_numeric($rawProdId) ? (int)$rawProdId : (int)preg_replace('/\D/', '', (string)$rawProdId);
                if ($numericId <= 0) {
                    $numericId = 1;
                }

                $product = Product::where('id', $numericId)
                    ->where('is_active', true)
                    ->first();

                if (!$product) {
                    $product = Product::where('is_active', true)->first();
                }

                if (!$product) {
                    throw ValidationException::withMessages([
                        'items' => ["No active product found."],
                    ]);
                }

                if ($product->stock < $item['quantity']) {
                    if (!$isPreorder) {
                        throw ValidationException::withMessages([
                            'items' => ["Désolé, le produit \"{$product->name}\" est temporairement épuisé."],
                        ]);
                    }
                } else {
                    // Decrement stock upon order placement for in-stock items
                    $product->decrement('stock', $item['quantity']);
                }

                $lineTotal = $product->price * $item['quantity'];
                $subtotal += $lineTotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ];
            }

            $shippingCost = 0;
            $total = $subtotal + $shippingCost;

            $defaultNotes = $isPreorder ? '🔔 DEMANDE DE RÉAPPROVISIONNEMENT - Client en attente de stock' : null;

            $order = Order::create([
                'customer_id' => $customer->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'payment_method' => 'cod',
                'notes' => $validated['notes'] ?? $defaultNotes,
            ]);

            $order->items()->createMany($orderItemsData);

            return $order;
        });

        // Send Email Notification to Admin
        try {
            $adminEmail = env('ADMIN_EMAIL') ?: config('mail.from.address', 'contact@maisonim.ma');
            if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                \Illuminate\Support\Facades\Mail::to($adminEmail)->send(
                    new \App\Mail\NewOrderNotification($order->fresh()->load(['customer', 'items.product']))
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("New order admin email notification failed: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'data' => $order->load(['customer', 'items.product']),
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $order = Order::with(['customer', 'items.product'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }
}