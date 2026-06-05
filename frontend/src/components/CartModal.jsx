export default function CartModal({
  error,
  loading,
  medicine,
  onBuyNow,
  onClose,
  onQuantityChange,
  onSubmitCart,
  quantity,
}) {
  if (!medicine) {
    return null
  }

  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
      <section className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <div className="mb-5 flex items-start justify-between gap-4">
          <div>
            <p className="text-sm font-bold uppercase tracking-wider text-primary">Tambah Produk</p>
            <h2 className="mt-1 text-xl font-bold text-on-surface">{medicine.name}</h2>
            <p className="mt-1 text-sm text-on-surface-variant">Stok tersedia: {medicine.total_stock} unit</p>
          </div>
          <button className="rounded-full p-2 hover:bg-surface-container-low" type="button" onClick={onClose}>
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        {error && (
          <div className="mb-4 rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">
            {error}
          </div>
        )}

        <label className="text-sm font-semibold text-on-surface" htmlFor="cart-quantity">
          Jumlah yang ingin dibeli
          <input
            className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-3 text-lg font-bold outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
            id="cart-quantity"
            min="1"
            max={medicine.total_stock}
            type="number"
            value={quantity}
            onChange={(event) => onQuantityChange(event.target.value)}
          />
        </label>

        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          <button
            className="rounded-lg border border-primary px-4 py-3 font-bold text-primary disabled:opacity-50"
            type="button"
            disabled={loading}
            onClick={onSubmitCart}
          >
            Masukkan Keranjang
          </button>
          <button
            className="rounded-lg bg-primary px-4 py-3 font-bold text-white disabled:opacity-50"
            type="button"
            disabled={loading}
            onClick={onBuyNow}
          >
            Beli Sekarang
          </button>
        </div>
      </section>
    </div>
  )
}
