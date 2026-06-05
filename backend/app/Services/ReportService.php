<?php

namespace App\Services;

use App\Models\MedicineBatch;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function sales(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $orders = $this->validSalesOrders($filters)
            ->with('items')
            ->whereBetween('created_at', [$from, $to])
            ->get();
        $totalRevenue = (float) $orders->sum('total');
        $totalTransactions = $orders->count();
        $totalItems = (int) $orders->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));

        return [
            'period' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'group_by' => $filters['group_by'] ?? 'daily',
            ],
            'summary' => [
                'total_transactions' => $totalTransactions,
                'total_revenue' => $totalRevenue,
                'average_order_value' => $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0,
                'items_sold' => $totalItems,
            ],
            'trend' => $this->salesTrend($orders, $filters['group_by'] ?? 'daily', $from, $to),
            'status_summary' => $this->statusSummary($filters, $from, $to),
            'payment_summary' => $this->paymentSummary($filters, $from, $to),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function topMedicines(array $filters, int $limit = 10): array
    {
        [$from, $to] = $this->period($filters);

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('medicines', 'medicines.id', '=', 'order_items.medicine_id')
            ->leftJoin('categories', 'categories.id', '=', 'medicines.category_id')
            ->where('orders.payment_status', Order::PAYMENT_STATUS_PAID)
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->where('orders.status', '!=', Order::STATUS_REJECTED)
            ->whereBetween('orders.created_at', [$from, $to])
            ->when($filters['payment_method'] ?? null, fn ($query, $value) => $query->where('orders.payment_method', $value))
            ->when($filters['order_status'] ?? null, fn ($query, $value) => $query->where('orders.status', $value))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('medicines.category_id', $value))
            ->select(
                'order_items.medicine_id',
                'order_items.medicine_name',
                DB::raw('coalesce(categories.name, \'-\') as category_name'),
                DB::raw('sum(order_items.quantity) as quantity_sold'),
                DB::raw('sum(order_items.subtotal) as revenue'),
            )
            ->groupBy('order_items.medicine_id', 'order_items.medicine_name', 'categories.name')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'medicine_id' => $row->medicine_id,
                'medicine_name' => $row->medicine_name,
                'category_name' => $row->category_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function expiringMedicines(array $filters, int $limit = 50): array
    {
        return MedicineBatch::query()
            ->with('medicine.category')
            ->where('quantity', '>', 0)
            ->whereDate('expired_date', '>=', now()->toDateString())
            ->whereDate('expired_date', '<=', now()->copy()->addDays(90)->toDateString())
            ->whereHas('medicine', function ($query) use ($filters): void {
                $query->where('is_active', true)
                    ->when($filters['category_id'] ?? null, fn ($medicineQuery, $value) => $medicineQuery->where('category_id', $value));
            })
            ->orderBy('expired_date')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(function (MedicineBatch $batch): array {
                $days = now()->startOfDay()->diffInDays($batch->expired_date, false);

                return [
                    'id' => $batch->id,
                    'medicine_name' => $batch->medicine?->name,
                    'category_name' => $batch->medicine?->category?->name,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expired_date?->toDateString(),
                    'quantity' => $batch->quantity,
                    'days_remaining' => $days,
                    'bucket' => $days <= 30 ? '30' : ($days <= 60 ? '60' : '90'),
                ];
            })
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function transactions(array $filters, int $limit = 50): array
    {
        [$from, $to] = $this->period($filters);

        return $this->orders($filters)
            ->with('user:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->user?->email,
                'date' => $order->created_at?->toDateString(),
                'created_at' => $order->created_at,
                'status' => $order->normalizedStatus(),
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'total' => (float) $order->total,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function exportPayload(array $filters): array
    {
        return [
            'sales' => $this->sales($filters),
            'transactions' => $this->transactions($filters, 500),
            'top_medicines' => $this->topMedicines($filters, 50),
            'expiring_medicines' => $this->expiringMedicines($filters, 100),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function exportExcelXml(array $payload, Carbon $from, Carbon $to): string
    {
        $summary = $payload['sales']['summary'];
        $summaryRows = [
            ['Klinik Makmur Jaya'],
            ['Laporan Penjualan'],
            ['Periode', $from->toDateString().' s/d '.$to->toDateString()],
            [],
            ['Total Transaksi', $summary['total_transactions']],
            ['Total Omzet', $summary['total_revenue']],
            ['Rata-rata Order', $summary['average_order_value']],
            ['Item Terjual', $summary['items_sold']],
            [],
            ['Trend', 'Revenue', 'Transaksi'],
        ];

        foreach ($payload['sales']['trend'] as $trend) {
            $summaryRows[] = [$trend['label'], $trend['revenue'], $trend['transactions']];
        }

        $transactionRows = [['Order', 'Pelanggan', 'Tanggal', 'Status', 'Payment', 'Metode', 'Total']];
        foreach ($payload['transactions'] as $transaction) {
            $transactionRows[] = [
                $transaction['order_number'],
                $transaction['customer_name'],
                $transaction['date'],
                $transaction['status'],
                $transaction['payment_status'],
                $transaction['payment_method'],
                $transaction['total'],
            ];
        }

        $topRows = [['Obat', 'Kategori', 'Qty Terjual', 'Revenue']];
        foreach ($payload['top_medicines'] as $medicine) {
            $topRows[] = [
                $medicine['medicine_name'],
                $medicine['category_name'],
                $medicine['quantity_sold'],
                $medicine['revenue'],
            ];
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            .'xmlns:o="urn:schemas-microsoft-com:office:office" '
            .'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            .'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            .$this->worksheetXml('Ringkasan', $summaryRows)
            .$this->worksheetXml('Detail Transaksi', $transactionRows)
            .$this->worksheetXml('Top Medicines', $topRows)
            .'</Workbook>';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    public function period(array $filters): array
    {
        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function validSalesOrders(array $filters): Builder
    {
        return $this->orders($filters)
            ->where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->where('status', '!=', Order::STATUS_REJECTED);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function orders(array $filters): Builder
    {
        return Order::query()
            ->when($filters['payment_method'] ?? null, fn ($query, $value) => $query->where('payment_method', $value))
            ->when($filters['order_status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->whereHas('items.medicine', fn ($medicineQuery) => $medicineQuery->where('category_id', $value)));
    }

    /**
     * @return array<int, array{period: string, label: string, revenue: float, transactions: int}>
     */
    private function salesTrend(Collection $orders, string $groupBy, Carbon $from, Carbon $to): array
    {
        $groupedOrders = $orders->groupBy(fn (Order $order): string => $this->groupKey($order->created_at, $groupBy));

        return $this->trendPeriods($from, $to, $groupBy)
            ->map(function (Carbon $date) use ($groupedOrders, $groupBy): array {
                $key = $this->groupKey($date, $groupBy);
                $group = $groupedOrders->get($key, collect());

                return [
                'period' => $key,
                'label' => $this->groupLabel($date, $groupBy),
                'revenue' => (float) $group->sum('total'),
                'transactions' => $group->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function trendPeriods(Carbon $from, Carbon $to, string $groupBy): Collection
    {
        $cursor = match ($groupBy) {
            'weekly' => $from->copy()->startOfWeek(),
            'monthly' => $from->copy()->startOfMonth(),
            default => $from->copy()->startOfDay(),
        };
        $end = match ($groupBy) {
            'weekly' => $to->copy()->startOfWeek(),
            'monthly' => $to->copy()->startOfMonth(),
            default => $to->copy()->startOfDay(),
        };
        $periods = collect();

        while ($cursor->lte($end)) {
            $periods->push($cursor->copy());
            match ($groupBy) {
                'weekly' => $cursor->addWeek(),
                'monthly' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $periods;
    }

    private function groupLabel(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'weekly' => $date->format('d M Y'),
            'monthly' => $date->format('M Y'),
            default => $date->format('d M'),
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    private function statusSummary(array $filters, Carbon $from, Carbon $to): array
    {
        $counts = $this->orders($filters)
            ->whereBetween('created_at', [$from, $to])
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
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    private function paymentSummary(array $filters, Carbon $from, Carbon $to): array
    {
        $counts = $this->orders($filters)
            ->whereBetween('created_at', [$from, $to])
            ->select('payment_status', DB::raw('count(*) as total'))
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status')
            ->all();

        $result = [];
        foreach (Order::PAYMENT_STATUSES as $status) {
            $result[$status] = (int) ($counts[$status] ?? 0);
        }

        return $result;
    }

    private function groupKey(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'weekly' => $date->copy()->startOfWeek()->format('Y-m-d'),
            'monthly' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function worksheetXml(string $name, array $rows): string
    {
        $xml = '<Worksheet ss:Name="'.$this->xml($name).'"><Table>';

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $type = is_numeric($cell) ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="'.$type.'">'.$this->xml((string) $cell).'</Data></Cell>';
            }
            $xml .= '</Row>';
        }

        return $xml.'</Table></Worksheet>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
