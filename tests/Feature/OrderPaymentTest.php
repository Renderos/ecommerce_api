<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected FakePaymentGateway $gateway;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    protected function createOrder(int $quantity = 2, float $price = 100): array
    {
        $product = Product::factory()->create(['price' => $price, 'stock' => 5]);

        $response = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'shipping_address' => 'Calle Falsa 123',
        ]);

        return [$product, $response];
    }

    public function test_order_is_created_with_server_side_prices_and_reserves_stock(): void
    {
        [$product, $response] = $this->createOrder(2, 100);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.tax', 38)
            ->assertJsonPath('data.total', 238)
            ->assertJsonPath('data.items.0.unit_price', 100);

        $this->assertSame(3, $product->refresh()->stock);
    }

    public function test_order_fails_with_insufficient_stock(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 3]]])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonStructure(['errors' => ['items.0.quantity']]);

        $this->assertSame(1, $product->refresh()->stock);
    }

    public function test_successful_payment_marks_order_as_paid_and_shows_in_history(): void
    {
        [, $response] = $this->createOrder();
        $orderId = $response->json('data.id');

        $this->postJson("/api/orders/{$orderId}/pay", ['payment_method' => 'pm_card_visa'])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'succeeded')
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.client_secret', null);

        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'amount' => 238, 'status' => 'succeeded']);

        // No se puede pagar dos veces
        $this->postJson("/api/orders/{$orderId}/pay", ['payment_method' => 'pm_card_visa'])->assertStatus(409);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'paid')
            ->assertJsonPath('data.0.latest_payment.status', 'succeeded');
    }

    public function test_declined_card_returns_402_and_marks_order_failed(): void
    {
        [, $response] = $this->createOrder();
        $orderId = $response->json('data.id');

        $this->postJson("/api/orders/{$orderId}/pay", ['payment_method' => 'pm_card_chargeDeclined'])
            ->assertStatus(402)
            ->assertJsonPath('error_code', 'CARD_DECLINED');

        $this->assertSame(Order::STATUS_FAILED, Order::find($orderId)->status);
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'status' => 'requires_payment_method']);

        // Se puede reintentar con otra tarjeta
        $this->postJson("/api/orders/{$orderId}/pay", ['payment_method' => 'pm_card_visa'])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'paid');
    }

    public function test_client_side_flow_returns_client_secret_and_syncs_status(): void
    {
        [, $response] = $this->createOrder();
        $orderId = $response->json('data.id');

        $data = $this->postJson("/api/orders/{$orderId}/pay")
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'requires_payment_method')
            ->json('data');

        $this->assertNotNull($data['client_secret']);

        $this->gateway->markSucceeded($data['payment']['stripe_payment_intent_id']);

        $this->getJson("/api/payments/{$data['payment']['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertSame(Order::STATUS_PAID, Order::find($orderId)->status);
    }

    public function test_webhook_with_valid_signature_updates_payment(): void
    {
        [, $response] = $this->createOrder();
        $orderId = $response->json('data.id');
        $intentId = $this->postJson("/api/orders/{$orderId}/pay")->json('data.payment.stripe_payment_intent_id');

        $intent = $this->gateway->intents[$intentId]->toArray();
        $intent['status'] = 'succeeded';
        $payload = json_encode([
            'id' => 'evt_test', 'object' => 'event', 'type' => 'payment_intent.succeeded',
            'data' => ['object' => $intent],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test_secret');

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('data.handled', true);

        $this->assertSame(Order::STATUS_PAID, Order::find($orderId)->status);

        // Firma inválida
        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=invalida",
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload)->assertStatus(400)->assertJsonPath('error_code', 'INVALID_SIGNATURE');
    }

    public function test_cancel_order_restores_stock(): void
    {
        [$product, $response] = $this->createOrder(2);
        $orderId = $response->json('data.id');

        $this->postJson("/api/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(5, $product->refresh()->stock);
        $this->postJson("/api/orders/{$orderId}/cancel")->assertStatus(409);
    }

    public function test_user_cannot_see_or_pay_other_users_orders(): void
    {
        [, $response] = $this->createOrder();
        $orderId = $response->json('data.id');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/orders/{$orderId}")->assertForbidden();
        $this->postJson("/api/orders/{$orderId}/pay", ['payment_method' => 'pm_card_visa'])->assertForbidden();
        $this->getJson('/api/orders')->assertJsonPath('meta.total', 0);
    }
}
