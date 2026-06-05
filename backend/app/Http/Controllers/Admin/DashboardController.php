<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Prescription;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->startOfDay();
        $validRevenueQuery = $this->validRevenueOrders();

        return response()->json([
            'data' => [
                'summary' => [
                    'revenue_today' => $this->sumRevenue((clone $validRevenueQuery), $today, $today->copy()->endOfDay()),
                    'revenue_week' => $this->sumRevenue((clone $validRevenueQuery), now()->startOfWeek(), now()->endOfWeek()),
                    'revenue_month' => $this->sumRevenue((clone $validRevenueQuery), now()->startOfMonth(), now()->endOfMonth()),
                    'orders_today' => Order::query()->whereBetween('created_at', [$today, $today->copy()->endOfDay()])->count(),
                    'customers' => User::query()->where('role', User::ROLE_PELANGGAN)->count(),
                    'active_medicines' => Medicine::query()->where('is_active', true)->count(),
                    'pending_prescriptions' => Prescription::query()->where('status', Prescription::STATUS_PENDING)->count(),
                ],
                'orders_by_status' => $this->ordersByStatus(),
                'recent_orders' => $this->recentOrders(),
                'critical_stock_medicines' => $this->criticalStockMedicines(),
                'expiring_batches' => $this->expiringBatches(),
                'top_selling_medicines' => $this->topSellingMedicines(),
                'sales_daily' => $this->dailySales(30),
                'sales_monthly' => $this->monthlySales(6),
            ],
        ]);
    }

    private function validRevenueOrders()
    {
        return Order::query()
            ->where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REJECTED]);
    }

    private function sumRevenue($query, Carbon $from, Carbon $to): float
    {
        return (float) $query
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');
    }

    /**
     * @return array<string, int>
     */
    private function ordersByStatus(): array
    {
        $counts = Order::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];
        foreach (Order::STATUSES as $status) {
            $result[$status] = (int) ($counts[$status] ?? 0);
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return Order::query()
            ->with('user:id,name,email')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->user?->email,
                'status' => $order->normalizedStatus(),
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'created_at' => $order->created_at,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function criticalStockMedicines(): array
    {
        return Medicine::query()
            ->where('is_active', true)
            ->with(['category:id,name', 'batches' => fn ($query) => $query
                ->whereDate('expired_date', '>=', now()->toDateString())])
            ->get()
            ->map(function (Medicine $medicine): array {
                $stock = (int) $medicine->batches->sum('quantity');

                return [
                    'id' => $medicine->id,
                    'name' => $medicine->name,
                    'category' => $medicine->category?->name,
                    'minimum_stock' => $medicine->minimum_stock,
                    'total_stock' => $stock,
                    'deficit' => max(0, $medicine->minimum_stock - $stock),
                ];
            })
            ->filter(fn (array $medicine): bool => $medicine['total_stock'] <= $medicine['minimum_stock'])
            ->sortBy([['deficit', 'desc'], ['total_stock', 'asc']])
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expiringBatches(): array
    {
        return MedicineBatch::query()
            ->with('medicine:id,name,is_active')
            ->where('quantity', '>', 0)
            ->whereDate('expired_date', '>=', now()->toDateString())
            ->whereDate('expired_date', '<=', now()->copy()->addDays(90)->toDateString())
            ->whereHas('medicine', fn ($query) => $query->where('is_active', true))
            ->orderBy('expired_date')
            ->orderBy('id')
            ->limit(15)
            ->get()
            ->map(fn (MedicineBatch $batch): array => [
                'id' => $batch->id,
                'medicine_name' => $batch->medicine?->name,
                'batch_number' => $batch->batch_number,
                'expired_date' => $batch->expired_date?->toDateString(),
                'days_remaining' => now()->startOfDay()->diffInDays($batch->expired_date, false),
                'quantity' => $batch->quantity,
                'bucket' => $this->expiryBucket($batch->expired_date),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topSellingMedicines(): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', Order::PAYMENT_STATUS_PAID)
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_REJECTED])
            ->select(
                'order_items.medicine_id',
                'order_items.medicine_name',
                DB::raw('sum(order_items.quantity) as quantity_sold'),
                DB::raw('sum(order_items.subtotal) as revenue'),
            )
            ->groupBy('order_items.medicine_id', 'order_items.medicine_name')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get()
            ->map(fn ($item): array => [
                'medicine_id' => $item->medicine_id,
                'medicine_name' => $item->medicine_name,
                'quantity_sold' => (int) $item->quantity_sold,
                'revenue' => (float) $item->revenue,
            ])
            ->all();
    }

    /**
     * @return array<int, array{date: string, label: string, revenue: float, orders: int}>
     */
    private function dailySales(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();
        $orders = $this->validRevenueOrders()
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'total', 'created_at'])
            ->groupBy(fn (Order $order): string => $order->created_at->toDateString());

        return collect(CarbonPeriod::create($start, '1 day', $end))
            ->map(function (Carbon $date) use ($orders): array {
                $key = $date->toDateString();
                $dailyOrders = $orders->get($key, collect());

                return [
                    'date' => $key,
                    'label' => $date->format('d M'),
                    'revenue' => (float) $dailyOrders->sum('total'),
                    'orders' => $dailyOrders->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{month: string, label: string, revenue: float, orders: int}>
     */
    private function monthlySales(int $months): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();
        $end = now()->endOfMonth();
        $orders = $this->validRevenueOrders()
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'total', 'created_at'])
            ->groupBy(fn (Order $order): string => $order->created_at->format('Y-m'));

        return Collection::times($months)
            ->map(function (int $index) use ($start, $orders): array {
                $date = $start->copy()->addMonths($index - 1);
                $key = $date->format('Y-m');
                $monthlyOrders = $orders->get($key, collect());

                return [
                    'month' => $key,
                    'label' => $date->format('M Y'),
                    'revenue' => (float) $monthlyOrders->sum('total'),
                    'orders' => $monthlyOrders->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function expiryBucket(?Carbon $expiredDate): string
    {
        if (! $expiredDate) {
            return 'unknown';
        }

        $days = now()->startOfDay()->diffInDays($expiredDate, false);

        return match (true) {
            $days <= 30 => '30',
            $days <= 60 => '60',
            default => '90',
        };
    }
}
