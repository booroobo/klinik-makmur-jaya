<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan Klinik Makmur Jaya</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .header { background: #0f766e; color: white; padding: 18px; border-radius: 8px; }
        .logo { display: inline-block; width: 42px; height: 42px; border-radius: 50%; background: #ccfbf1; color: #0f766e; text-align: center; line-height: 42px; font-weight: bold; margin-right: 12px; }
        .title { display: inline-block; vertical-align: top; }
        h1 { margin: 0; font-size: 20px; }
        h2 { margin-top: 24px; font-size: 15px; color: #0f766e; }
        .muted { color: #6b7280; }
        .cards { margin-top: 16px; width: 100%; }
        .card { display: inline-block; width: 23%; margin-right: 1%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #f9fafb; }
        .label { font-size: 10px; color: #6b7280; text-transform: uppercase; }
        .value { margin-top: 6px; font-size: 16px; font-weight: bold; color: #0f766e; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #e6fffb; color: #0f766e; text-align: left; }
        th, td { border: 1px solid #d1d5db; padding: 7px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <span class="logo">KMJ</span>
        <span class="title">
            <h1>Klinik Makmur Jaya</h1>
            <div>Laporan Penjualan</div>
            <div>Periode {{ $from->toDateString() }} s/d {{ $to->toDateString() }}</div>
        </span>
    </div>

    <div class="cards">
        <div class="card"><div class="label">Transaksi</div><div class="value">{{ $payload['sales']['summary']['total_transactions'] }}</div></div>
        <div class="card"><div class="label">Omzet</div><div class="value">Rp {{ number_format($payload['sales']['summary']['total_revenue'], 0, ',', '.') }}</div></div>
        <div class="card"><div class="label">Rata-rata</div><div class="value">Rp {{ number_format($payload['sales']['summary']['average_order_value'], 0, ',', '.') }}</div></div>
        <div class="card"><div class="label">Item Terjual</div><div class="value">{{ $payload['sales']['summary']['items_sold'] }}</div></div>
    </div>

    <h2>Trend Penjualan</h2>
    <table>
        <thead><tr><th>Periode</th><th class="right">Omzet</th><th class="right">Transaksi</th></tr></thead>
        <tbody>
            @forelse ($payload['sales']['trend'] as $trend)
                <tr><td>{{ $trend['label'] }}</td><td class="right">Rp {{ number_format($trend['revenue'], 0, ',', '.') }}</td><td class="right">{{ $trend['transactions'] }}</td></tr>
            @empty
                <tr><td colspan="3">Tidak ada data penjualan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detail Transaksi</h2>
    <table>
        <thead><tr><th>Order</th><th>Pelanggan</th><th>Tanggal</th><th>Status</th><th>Payment</th><th class="right">Total</th></tr></thead>
        <tbody>
            @forelse ($payload['transactions'] as $transaction)
                <tr>
                    <td>{{ $transaction['order_number'] }}</td>
                    <td>{{ $transaction['customer_name'] }}</td>
                    <td>{{ $transaction['date'] }}</td>
                    <td>{{ $transaction['status'] }}</td>
                    <td>{{ $transaction['payment_status'] }}</td>
                    <td class="right">Rp {{ number_format($transaction['total'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Tidak ada transaksi.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Top Medicines</h2>
    <table>
        <thead><tr><th>Obat</th><th>Kategori</th><th class="right">Qty</th><th class="right">Revenue</th></tr></thead>
        <tbody>
            @forelse ($payload['top_medicines'] as $medicine)
                <tr><td>{{ $medicine['medicine_name'] }}</td><td>{{ $medicine['category_name'] }}</td><td class="right">{{ $medicine['quantity_sold'] }}</td><td class="right">Rp {{ number_format($medicine['revenue'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="4">Belum ada obat terjual.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted">Dibuat pada {{ $generatedAt->format('Y-m-d H:i:s') }}</p>
</body>
</html>
