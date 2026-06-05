export default function ConfirmModal({
  confirmLabel = 'Hapus',
  description,
  loading = false,
  onCancel,
  onConfirm,
  title = 'Konfirmasi Hapus',
  variant = 'error', // 'error' or 'primary'
}) {
  const isError = variant === 'error'

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4">
      <section className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <div className={`mb-4 flex h-12 w-12 items-center justify-center rounded-full ${
          isError ? 'bg-error-container text-error' : 'bg-primary/10 text-primary'
        }`}>
          <span className="material-symbols-outlined">{isError ? 'warning' : 'info'}</span>
        </div>
        <h2 className="text-xl font-bold text-on-surface">{title}</h2>
        <p className="mt-2 text-sm leading-6 text-on-surface-variant">{description}</p>
        <div className="mt-6 flex justify-end gap-3">
          <button
            className="rounded-lg border border-outline-variant px-5 py-2.5 font-bold text-on-surface"
            type="button"
            onClick={onCancel}
            disabled={loading}
          >
            Batal
          </button>
          <button
            className={`rounded-lg px-5 py-2.5 font-bold text-white disabled:opacity-50 ${
              isError ? 'bg-error' : 'bg-primary'
            }`}
            type="button"
            onClick={onConfirm}
            disabled={loading}
          >
            {loading ? 'Memproses...' : confirmLabel}
          </button>
        </div>
      </section>
    </div>
  )
}
