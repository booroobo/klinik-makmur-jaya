import assert from 'node:assert/strict'
import test from 'node:test'
import { buildSalesTrendChartData } from './salesTrendChart.js'

test('sales trend chart maps report labels and revenue like dashboard', () => {
  const chart = buildSalesTrendChartData([
    { label: '01 Jun', revenue: 100000 },
    { label: '02 Jun', revenue: 0 },
  ])

  assert.deepEqual(chart.labels, ['01 Jun', '02 Jun'])
  assert.deepEqual(chart.datasets[0].data, [100000, 0])
  assert.equal(chart.datasets[0].fill, true)
  assert.equal(chart.datasets[0].backgroundColor, 'rgba(15, 118, 110, 0.16)')
})
