import assert from 'node:assert/strict'
import test from 'node:test'
import {
  isNotificationUnread,
  getNotificationRedirectTarget,
  markNotificationReadLocally,
  sortNotificationsByPriority,
  unreadIndicatorClass,
} from './notifications.js'

test('unread indicator appears for new notifications', () => {
  assert.equal(isNotificationUnread({ id: 1, is_read: false, read_at: null }), true)
  assert.match(unreadIndicatorClass({ id: 1, is_read: false, read_at: null }), /bg-red-500/)
})

test('clicked notification redirects to order detail and falls below unread items', () => {
  const notifications = [
    { id: 1, title: 'Unread older', is_read: false, read_at: null, created_at: '2026-06-06T09:00:00Z', target_url: '/my-orders/1' },
    { id: 2, title: 'Unread newer', is_read: false, read_at: null, created_at: '2026-06-06T10:00:00Z', target_url: '/my-orders/2' },
  ]

  const sorted = sortNotificationsByPriority(notifications)
  assert.equal(sorted[0].title, 'Unread newer')

  const afterClick = markNotificationReadLocally(sorted, 2, '2026-06-06T10:01:00Z')
  assert.equal(afterClick[0].title, 'Unread older')
  assert.equal(afterClick[1].is_read, true)
  assert.equal(getNotificationRedirectTarget(afterClick[1]), '/my-orders/2')
})
