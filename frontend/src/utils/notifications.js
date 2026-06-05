export const isNotificationUnread = (notification) => !notification?.is_read && !notification?.read_at

export const sortNotificationsByPriority = (notifications) => [...notifications].sort((first, second) => {
  const firstUnread = isNotificationUnread(first)
  const secondUnread = isNotificationUnread(second)

  if (firstUnread !== secondUnread) return firstUnread ? -1 : 1

  return new Date(second.created_at || 0).getTime() - new Date(first.created_at || 0).getTime()
})

export const markNotificationReadLocally = (notifications, notificationId, readAt = new Date().toISOString()) =>
  sortNotificationsByPriority(notifications.map((notification) => (
    notification.id === notificationId ? { ...notification, is_read: true, read_at: notification.read_at || readAt } : notification
  )))

export const markAllNotificationsReadLocally = (notifications, readAt = new Date().toISOString()) =>
  sortNotificationsByPriority(notifications.map((notification) => ({ ...notification, is_read: true, read_at: notification.read_at || readAt })))

export const unreadIndicatorClass = (notification) => (
  isNotificationUnread(notification) ? 'bg-red-500 ring-red-100' : 'bg-blue-600 ring-blue-100 opacity-70'
)

export const getNotificationRedirectTarget = (notification) => notification?.target_url || ''
