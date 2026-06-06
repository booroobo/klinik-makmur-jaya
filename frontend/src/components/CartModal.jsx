export default function CartModal({
  error,
  loading,
  medicine,
  onBuyNow,
  onClose,
  onQuantityChange,
  onVariantChange,
  selectedVariantId,
  onSubmitCart,
  quantity,
}) {
  if (!medicine) {
    return null
  }

  const variants = medicine.has_variants ? (medicine.variants || []).filter((variant) => variant.is_active !== false) : []
  const selectedVariant = variants.find((variant) => String(variant.id) === String(selectedVariantId))
  const effectivePrice = selectedVariant?.price ?? medicine.price
  const effectiveStock = selectedVariant?.stock ?? medicine.total_stock
  const canSubmit = !medicine.has_variants || selectedVariant

  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
      <section className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <div className="mb-5 flex items-start justify-between gap-4">
          <div>
            <p className="text-sm font-bold uppercase tracking-wider text-primary">Tambah Produk</p>
            <h2 className="mt-1 text-xl font-bold text-on-surface">{medicine.name}</h2>
            <p className="mt-1 text-sm font-semibold text-primary">{new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(effectivePrice || 0))}</p>
            <p className="mt-1 text-sm text-on-surface-variant">Stok tersedia: {effectiveStock} unit</p>
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

        {medicine.has_variants && (
          <label className="mb-4 block text-sm font-semibold text-on-surface" htmlFor="cart-variant">
            Pilih Varian
            <select
              className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-3 outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
              id="cart-variant"
              value={selectedVariantId}
              onChange={(event) => onVariantChange(event.target.value)}
            >
              <option value="">Pilih varian</option>
              {variants.map((variant) => (
                <option key={variant.id} value={variant.id}>
                  {variant.name} - {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(variant.price || 0))} - Stok {variant.stock}
                </option>
              ))}
            </select>
          </label>
        )}

        <label className="text-sm font-semibold text-on-surface" htmlFor="cart-quantity">
          Jumlah yang ingin dibeli
          <input
            className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-3 text-lg font-bold outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
            id="cart-quantity"
            min="1"
            max={effectiveStock}
            type="number"
            value={quantity}
            onChange={(event) => onQuantityChange(event.target.value)}
          />
        </label>

        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          <button
            className="rounded-lg border border-primary px-4 py-3 font-bold text-primary disabled:opacity-50"
            type="button"
            disabled={loading || !canSubmit || effectiveStock <= 0}
            onClick={onSubmitCart}
          >
            {canSubmit ? 'Masukkan Keranjang' : 'Pilih Varian'}
          </button>
          <button
            className="rounded-lg bg-primary px-4 py-3 font-bold text-white disabled:opacity-50"
            type="button"
            disabled={loading || !canSubmit || effectiveStock <= 0}
            onClick={onBuyNow}
          >
            {canSubmit ? 'Beli Sekarang' : 'Pilih Varian'}
          </button>
        </div>
      </section>
    </div>
  )
}
