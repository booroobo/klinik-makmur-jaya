<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use InvalidArgumentException;

class OrderWorkflowService
{
    /**
     * @return array<int, string>
     */
    public function allowedNextStatuses(Order $order, ?User $actor = null): array
    {
        $status = $order->normalizedStatus();
        $isAdmin = $actor?->role === User::ROLE_ADMIN;

        return match ($status) {
            Order::STATUS_PENDING_PAYMENT => [Order::STATUS_PAID],
            Order::STATUS_PAID => [Order::STATUS_CONFIRMED],
            Order::STATUS_CONFIRMED => [Order::STATUS_PROCESSING],
            Order::STATUS_PROCESSING => [$order->fulfillment_method === Order::FULFILLMENT_DELIVERY ? Order::STATUS_OUT_FOR_DELIVERY : Order::STATUS_READY_FOR_PICKUP],
            Order::STATUS_READY_FOR_PICKUP, Order::STATUS_OUT_FOR_DELIVERY => [Order::STATUS_COMPLETED],
            Order::STATUS_WAITING_PRESCRIPTION_REVIEW => $isAdmin ? [Order::STATUS_REJECTED] : [],
            default => [],
        };
    }

    public function updateStatus(Order $order, string $nextStatus, User $actor): Order
    {
        if (! in_array($nextStatus, Order::STATUSES, true)) {
            throw new InvalidArgumentException('Status pesanan tidak valid.');
        }

        if (! in_array($nextStatus, $this->allowedNextStatuses($order, $actor), true)) {
            throw new InvalidArgumentException('Transisi status pesanan tidak valid.');
        }

        $order->update(['status' => $nextStatus]);

        return $order->fresh();
    }

    public function updatePayment(Order $order, string $paymentStatus): Order
    {
        if (! in_array($paymentStatus, Order::PAYMENT_STATUSES, true)) {
            throw new InvalidArgumentException('Status pembayaran tidak valid.');
        }

        if (in_array($order->normalizedStatus(), [Order::STATUS_CANCELLED, Order::STATUS_REJECTED], true)) {
            throw new InvalidArgumentException('Pembayaran tidak bisa diubah untuk pesanan terminal.');
        }

        $data = ['payment_status' => $paymentStatus];

        if ($paymentStatus === Order::PAYMENT_STATUS_PAID && $order->normalizedStatus() === Order::STATUS_PENDING_PAYMENT) {
            $data['status'] = Order::STATUS_PAID;
        }

        $order->update($data);

        return $order->fresh();
    }

    public function cancel(Order $order): Order
    {
        if (in_array($order->normalizedStatus(), [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED, Order::STATUS_REJECTED], true)) {
            throw new InvalidArgumentException('Pesanan tidak bisa dibatalkan pada status saat ini.');
        }

        $order->update(['status' => Order::STATUS_CANCELLED]);

        return $order->fresh();
    }
}
