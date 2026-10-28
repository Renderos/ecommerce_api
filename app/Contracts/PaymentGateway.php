<?php

namespace App\Contracts;

use Stripe\Event;
use Stripe\PaymentIntent;

/**
 * Abstracción de la pasarela de pago. Permite reemplazar Stripe por un doble
 * de prueba en los tests sin realizar llamadas reales a la red.
 */
interface PaymentGateway
{
    public function createPaymentIntent(array $params, string $idempotencyKey): PaymentIntent;

    public function retrievePaymentIntent(string $id): PaymentIntent;

    public function cancelPaymentIntent(string $id): PaymentIntent;

    public function constructWebhookEvent(string $payload, string $signature): Event;
}
