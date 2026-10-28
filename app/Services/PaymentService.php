<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;

class PaymentService
{
    /** Estados de PaymentIntent que todavía pueden completarse. */
    protected const OPEN_STATUSES = ['requires_payment_method', 'requires_confirmation', 'requires_action', 'processing'];

    public function __construct(protected PaymentGateway $gateway) {}

    /**
     * Crea un PaymentIntent en Stripe para la orden. Si se entrega un
     * payment_method, se confirma inmediatamente en el servidor.
     *
     * @return array{payment: Payment, client_secret: ?string, requires_action: bool}
     */
    public function pay(Order $order, ?string $paymentMethod = null): array
    {
        if (! $order->isPayable()) {
            throw new BusinessException("La orden no se puede pagar porque su estado es '{$order->status}'.", 409);
        }

        $params = [
            'amount' => $order->amountInCents(),
            'currency' => $order->currency,
            'description' => "Orden {$order->order_number}",
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_number' => $order->order_number,
                'user_id' => (string) $order->user_id,
            ],
            // Solo métodos que no requieren redirección (tarjetas), así se puede confirmar desde la API
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
        ];

        if ($paymentMethod) {
            $params['payment_method'] = $paymentMethod;
            $params['confirm'] = true;
        }

        // La clave de idempotencia evita cobros duplicados si el cliente reintenta la petición
        $attempt = $order->payments()->count() + 1;
        $idempotencyKey = "order-{$order->id}-attempt-{$attempt}-".md5((string) $paymentMethod);

        try {
            $intent = $this->gateway->createPaymentIntent($params, $idempotencyKey);
        } catch (CardException $e) {
            // La tarjeta fue rechazada: Stripe igual crea el PaymentIntent, lo registramos como fallido
            $intentData = $e->getError()?->payment_intent;
            if ($intentData) {
                $this->syncFromIntent($this->recordPayment($order, PaymentIntent::constructFrom($intentData->toArray())));
            } else {
                $order->update(['status' => Order::STATUS_FAILED]);
            }

            throw $e;
        }

        $payment = $this->syncFromIntent($this->recordPayment($order, $intent));

        return [
            'payment' => $payment,
            'client_secret' => in_array($intent->status, self::OPEN_STATUSES, true) ? $intent->client_secret : null,
            'requires_action' => $intent->status === 'requires_action',
        ];
    }

    /**
     * Consulta el estado actual del PaymentIntent en Stripe y actualiza la BD.
     */
    public function refresh(Payment $payment): Payment
    {
        $intent = $this->gateway->retrievePaymentIntent($payment->stripe_payment_intent_id);

        return $this->syncFromIntent($payment, $intent);
    }

    /**
     * Cancela en Stripe los PaymentIntent abiertos de una orden (al cancelar la orden).
     */
    public function cancelOpenIntents(Order $order): void
    {
        $order->payments()->whereIn('status', self::OPEN_STATUSES)->each(function (Payment $payment) {
            try {
                $this->syncFromIntent($payment, $this->gateway->cancelPaymentIntent($payment->stripe_payment_intent_id));
            } catch (\Throwable $e) {
                Log::warning('No se pudo cancelar el PaymentIntent', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        });
    }

    /**
     * Procesa un evento de webhook ya verificado. Devuelve true si el evento fue gestionado.
     */
    public function handleWebhookEvent(Event $event): bool
    {
        if (! str_starts_with($event->type, 'payment_intent.')) {
            return false;
        }

        /** @var PaymentIntent $intent */
        $intent = $event->data->object;
        $payment = Payment::where('stripe_payment_intent_id', $intent->id)->first();

        if (! $payment) {
            Log::info('Webhook de Stripe para un PaymentIntent desconocido', ['intent' => $intent->id, 'type' => $event->type]);

            return false;
        }

        $this->syncFromIntent($payment, $intent);

        return true;
    }

    protected function recordPayment(Order $order, PaymentIntent $intent): Payment
    {
        return Payment::firstOrCreate(
            ['stripe_payment_intent_id' => $intent->id],
            [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'provider' => 'stripe',
                'amount' => $order->total,
                'currency' => $order->currency,
                'status' => $intent->status,
            ]
        );
    }

    /**
     * Sincroniza el registro local del pago y el estado de la orden con el PaymentIntent.
     */
    public function syncFromIntent(Payment $payment, ?PaymentIntent $intent = null): Payment
    {
        $intent ??= $this->gateway->retrievePaymentIntent($payment->stripe_payment_intent_id);

        return DB::transaction(function () use ($payment, $intent) {
            $order = $payment->order()->lockForUpdate()->first();
            $paymentMethod = $intent->payment_method;

            $payment->fill([
                'status' => $intent->status,
                'payment_method' => is_string($paymentMethod) ? $paymentMethod : ($paymentMethod->id ?? null),
                'failure_message' => $intent->last_payment_error->message ?? null,
                'metadata' => ['stripe_status' => $intent->status, 'livemode' => (bool) ($intent->livemode ?? false)],
            ]);

            if ($intent->status === 'succeeded') {
                // Verificación de integridad: el monto cobrado debe coincidir con la orden
                if ((int) $intent->amount !== $order->amountInCents() || $intent->currency !== $order->currency) {
                    Log::critical('Monto de Stripe no coincide con la orden', ['order_id' => $order->id, 'intent' => $intent->id]);
                    $payment->status = Payment::STATUS_FAILED;
                    $payment->failure_message = 'El monto cobrado no coincide con el total de la orden.';
                } else {
                    $payment->paid_at ??= now();
                    $order->update(['status' => Order::STATUS_PAID, 'paid_at' => $order->paid_at ?? now()]);
                }
            } elseif ($intent->status === 'requires_payment_method' && isset($intent->last_payment_error)) {
                if ($order->status !== Order::STATUS_PAID) {
                    $order->update(['status' => Order::STATUS_FAILED]);
                }
            }

            $payment->save();

            return $payment->refresh();
        });
    }
}
