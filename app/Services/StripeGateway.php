<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Exceptions\BusinessException;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Implementación de la pasarela usando el SDK oficial stripe/stripe-php.
 */
class StripeGateway implements PaymentGateway
{
    protected ?StripeClient $client = null;

    public function __construct(
        protected ?string $secret,
        protected ?string $webhookSecret,
    ) {}

    protected function client(): StripeClient
    {
        if (empty($this->secret)) {
            throw new BusinessException('La pasarela de pago no está configurada (falta STRIPE_SECRET).', 503);
        }

        return $this->client ??= new StripeClient([
            'api_key' => $this->secret,
            'max_network_retries' => 2,
        ]);
    }

    public function createPaymentIntent(array $params, string $idempotencyKey): PaymentIntent
    {
        return $this->client()->paymentIntents->create($params, [
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($id);
    }

    public function cancelPaymentIntent(string $id): PaymentIntent
    {
        return $this->client()->paymentIntents->cancel($id);
    }

    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        if (empty($this->webhookSecret)) {
            throw new BusinessException('El webhook de Stripe no está configurado (falta STRIPE_WEBHOOK_SECRET).', 503);
        }

        return Webhook::constructEvent($payload, $signature, $this->webhookSecret);
    }
}
