export default function ConfirmModal({
  confirmLabel = 'Hapus',
  description,
  loading = false,
  onCancel,
  onConfirm,
  title = 'Konfirmasi Hapus',
}) {
  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4">
      <section className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-error-container text-error">
          <span className="material-symbols-outlined">warning</span>
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
            className="rounded-lg bg-error px-5 py-2.5 font-bold text-white disabled:opacity-50"
            type="button"
            onClick={onConfirm}
            disabled={loading}
          >
            {loading ? 'Menghapus...' : confirmLabel}
          </button>
        </div>
      </section>
    </div>
  )
}
