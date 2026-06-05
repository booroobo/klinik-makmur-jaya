<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;

class NotificationService
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function notifyUser(User|int $user, string $type, string $title, string $message, string $severity = Notification::SEVERITY_INFO, ?array $data = null): Notification
    {
        $userId = $user instanceof User ? $user->id : $user;

        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'data' => $data,
        ]);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function notifyRole(string $role, string $type, string $title, string $message, string $severity = Notification::SEVERITY_INFO, ?array $data = null): Notification
    {
        return Notification::create([
            'role_target' => $role,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'data' => $data,
        ]);
    }

    public function notifyNewOrder(Order $order): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_KASIR] as $role) {
            $this->notifyRole(
                $role,
                'order_created',
                "Order baru {$order->order_number}",
                "Order baru dari {$order->customer_name} menunggu diproses.",
                Notification::SEVERITY_INFO,
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                ],
            );
        }
    }

    public function notifyNewPrescription(Prescription $prescription): void
    {
        $prescription->loadMissing('order');

        foreach ([User::ROLE_ADMIN, User::ROLE_APOTEKER] as $role) {
            $this->notifyRole(
                $role,
                'prescription_created',
                "Resep baru {$prescription->order?->order_number}",
                'Resep baru menunggu verifikasi admin/apoteker.',
                Notification::SEVERITY_WARNING,
                [
                    'prescription_id' => $prescription->id,
                    'order_id' => $prescription->order_id,
                    'order_number' => $prescription->order?->order_number,
                ],
            );
        }
    }

    public function notifyOrderStatusChanged(Order $order, ?string $beforeStatus = null): void
    {
        $status = $order->normalizedStatus();

        $this->notifyUser(
            $order->user_id,
            'order_status_changed',
            "Status order {$order->order_number} berubah",
            "Status pesanan Anda sekarang: {$status}.",
            Notification::SEVERITY_INFO,
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'before_status' => $beforeStatus,
                'status' => $status,
            ],
        );
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function notifyInventoryAlert(string $type, string $title, string $message, string $severity, ?array $data = null): int
    {
        $created = 0;

        foreach ([User::ROLE_ADMIN, User::ROLE_APOTEKER] as $role) {
            $exists = Notification::query()
                ->where('role_target', $role)
                ->where('type', $type)
                ->where('title', $title)
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            $this->notifyRole($role, $type, $title, $message, $severity, $data);
            $created++;
        }

        return $created;
    }
}
