<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->user_id === $user->id;
    }

    /**
     * Solo el dueño de la orden puede pagarla o cancelarla.
     */
    public function update(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
