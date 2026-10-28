<?php

namespace Tests\Fakes;

use App\Contracts\PaymentGateway;
use Stripe\Event;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\Webhook;

/**
 * Doble de prueba de Stripe: simula PaymentIntents sin llamadas a la red.
 * Reproduce los PaymentMethods de prueba oficiales de Stripe.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, PaymentIntent> */
    public array $intents = [];

    public function __construct(protected string $webhookSecret = 'whsec_test_secret') {}

    public function createPaymentIntent(array $params, string $idempotencyKey): PaymentIntent
    {
        $id = 'pi_fake_'.substr(md5($idempotencyKey), 0, 12);
        $pm = $params['payment_method'] ?? null;
        $confirm = $params['confirm'] ?? false;

        $data = [
            'id' => $id,
            'object' => 'payment_intent',
            'amount' => $params['amount'],
            'currency' => $params['currency'],
            'client_secret' => "{$id}_secret_fake",
            'payment_method' => $pm,
            'livemode' => false,
            'metadata' => $params['metadata'] ?? [],
            'status' => 'requires_payment_method',
            'last_payment_error' => null,
        ];

        if ($confirm && $pm === 'pm_card_chargeDeclined') {
            $data['last_payment_error'] = ['message' => 'Your card was declined.', 'decline_code' => 'generic_decline'];
            $this->intents[$id] = PaymentIntent::constructFrom($data);

            $e = CardException::factory('Your card was declined.', 402, null, [
                'error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'generic_decline', 'payment_intent' => $data],
            ], null, 'card_declined', 'generic_decline');

            throw $e;
        }

        if ($confirm) {
            $data['status'] = $pm === 'pm_card_authenticationRequired' ? 'requires_action' : 'succeeded';
        }

        return $this->intents[$id] = PaymentIntent::constructFrom($data);
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return $this->intents[$id];
    }

    public function cancelPaymentIntent(string $id): PaymentIntent
    {
        $data = $this->intents[$id]->toArray();
        $data['status'] = 'canceled';

        return $this->intents[$id] = PaymentIntent::constructFrom($data);
    }

    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        return Webhook::constructEvent($payload, $signature, $this->webhookSecret);
    }

    /** Simula que el cliente completó el pago en el frontend. */
    public function markSucceeded(string $id): void
    {
        $data = $this->intents[$id]->toArray();
        $data['status'] = 'succeeded';
        $this->intents[$id] = PaymentIntent::constructFrom($data);
    }
}
