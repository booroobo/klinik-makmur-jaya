export const buildSalesTrendChartData = (trend = []) => ({
  labels: trend.map((row) => row.label),
  datasets: [
    {
      label: 'Omzet',
      data: trend.map((row) => row.revenue),
      borderColor: '#0f766e',
      backgroundColor: 'rgba(15, 118, 110, 0.16)',
      fill: true,
      tension: 0.35,
    },
  ],
})

export const buildSalesTrendChartOptions = (formatCurrency) => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (context) => formatCurrency(context.parsed.y ?? context.parsed),
      },
    },
  },
  scales: {
    y: { beginAtZero: true, ticks: { callback: (value) => formatCurrency(value) } },
  },
})
