export default function Toast({ actionLabel, message, onAction, onClose, type = 'success' }) {
  const colorClass = type === 'error' ? 'border-error text-error' : 'border-primary text-primary'

  return (
    <div className={`fixed bottom-6 right-6 z-[120] flex max-w-md items-center gap-4 rounded-xl border bg-white p-4 shadow-xl ${colorClass}`}>
      <span className="material-symbols-outlined">{type === 'error' ? 'error' : 'check_circle'}</span>
      <p className="flex-1 text-sm font-semibold text-on-surface">{message}</p>
      {actionLabel && (
        <button className="text-sm font-bold text-primary hover:underline" type="button" onClick={onAction}>
          {actionLabel}
        </button>
      )}
      <button className="rounded-full p-1 hover:bg-surface-container-low" type="button" onClick={onClose}>
        <span className="material-symbols-outlined text-[18px] text-on-surface-variant">close</span>
      </button>
    </div>
  )
}
