<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * Crea una orden calculando precios desde la base de datos (nunca desde el
     * cliente) y reserva el stock dentro de una transacción con bloqueo de filas.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function create(User $user, array $items, ?string $shippingAddress = null, ?string $notes = null): Order
    {
        return DB::transaction(function () use ($user, $items, $shippingAddress, $notes) {
            $products = Product::whereIn('id', array_column($items, 'product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];
            $lines = [];
            $subtotal = 0.0;

            foreach (array_values($items) as $index => $item) {
                $product = $products->get($item['product_id']);

                if (! $product || ! $product->is_active) {
                    $errors["items.{$index}.product_id"][] = 'El producto no está disponible para la venta.';

                    continue;
                }

                if ($product->stock < $item['quantity']) {
                    $errors["items.{$index}.quantity"][] = "Solo quedan {$product->stock} unidades de {$product->name}.";

                    continue;
                }

                $lineTotal = round((float) $product->price * $item['quantity'], 2);
                $subtotal += $lineTotal;

                $lines[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $lineTotal,
                ];
            }

            if ($errors) {
                throw new BusinessException('Stock insuficiente o productos no disponibles.', 422, $errors);
            }

            $subtotal = round($subtotal, 2);
            $tax = round($subtotal * config('services.shop.tax_rate'), 2);

            $order = $user->orders()->create([
                'order_number' => $this->generateOrderNumber(),
                'status' => Order::STATUS_PENDING,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => round($subtotal + $tax, 2),
                'currency' => config('services.stripe.currency'),
                'shipping_address' => $shippingAddress,
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'subtotal' => $line['subtotal'],
                ]);

                $line['product']->decrement('stock', $line['quantity']);
            }

            return $order->load('items');
        });
    }

    /**
     * Cancela una orden no pagada y devuelve el stock reservado.
     */
    public function cancel(Order $order): Order
    {
        if ($order->status === Order::STATUS_PAID) {
            throw new BusinessException('No se puede cancelar una orden que ya fue pagada.', 409);
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            throw new BusinessException('La orden ya se encuentra cancelada.', 409);
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
            }

            $order->update(['status' => Order::STATUS_CANCELLED]);

            return $order->refresh()->load('items');
        });
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
