import Tooltip from './Tooltip'

export default function MedicineDraftModal({
  drafts,
  loading = false,
  onClose,
  onDelete,
  onLoad,
}) {
  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
      <section className="w-full max-w-3xl rounded-xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-outline-variant p-5">
          <div>
            <h2 className="text-xl font-bold text-on-surface">Draft Obat</h2>
            <p className="text-sm text-on-surface-variant">
              Draft otomatis dihapus setelah 7 hari jika tidak disimpan.
            </p>
          </div>
          <button className="rounded-full p-2 hover:bg-surface-container-low" type="button" onClick={onClose}>
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>
        <div className="max-h-[70vh] overflow-y-auto p-5">
          {loading ? (
            <p className="py-8 text-center text-on-surface-variant">Memuat draft...</p>
          ) : drafts.length === 0 ? (
            <p className="py-8 text-center text-on-surface-variant">Belum ada draft aktif.</p>
          ) : (
            <div className="space-y-3">
              {drafts.map((draft) => (
                <article key={draft.id} className="flex items-center justify-between gap-4 rounded-xl border border-outline-variant p-4">
                  <div className="min-w-0">
                    <h3 className="font-bold text-on-surface">
                      {draft.payload?.name || 'Draft tanpa nama'}
                    </h3>
                    <p className="mt-1 text-xs text-on-surface-variant">
                      Dibuat: {new Date(draft.created_at).toLocaleString('id-ID')} | Expired: {new Date(draft.expires_at).toLocaleString('id-ID')}
                    </p>
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    <Tooltip label="Lanjutkan Draft">
                      <button
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white"
                        type="button"
                        onClick={() => onLoad(draft)}
                      >
                        Lanjutkan
                      </button>
                    </Tooltip>
                    <Tooltip label="Hapus Draft">
                      <button
                        className="rounded-lg border border-error px-4 py-2 text-sm font-bold text-error"
                        type="button"
                        onClick={() => onDelete(draft)}
                      >
                        Hapus
                      </button>
                    </Tooltip>
                  </div>
                </article>
              ))}
            </div>
          )}
        </div>
      </section>
    </div>
  )
}
