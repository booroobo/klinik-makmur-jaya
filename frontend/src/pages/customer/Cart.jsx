import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import Footer from '../../components/Footer'
import Navbar from '../../components/Navbar'

const emptyCart = {
  items: [],
  subtotal: 0,
  total_quantity: 0,
  has_prescription_items: false,
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))

export default function Cart() {
  const [cart, setCart] = useState(emptyCart)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [updatingId, setUpdatingId] = useState(null)
  const [itemErrors, setItemErrors] = useState({})

  const fetchCart = async () => {
    setLoading(true)
    setError('')

    try {
      const response = await api.get('/cart')
      setCart(response.data.data || emptyCart)
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat keranjang.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchCart()
  }, [])

  const updateQuantity = async (item, quantity) => {
    if (!Number.isInteger(quantity) || quantity < 1 || quantity > item.medicine.total_stock) {
      setItemErrors((current) => ({ ...current, [item.id]: quantity > item.medicine.total_stock ? 'Jumlah melebihi stok tersedia.' : 'Jumlah minimal 1.' }))
      return
    }

    setUpdatingId(item.id)
    setError('')

    try {
      const response = await api.put(`/cart/items/${item.id}`, { quantity })
      setCart(response.data.data || emptyCart)
      setItemErrors((current) => ({ ...current, [item.id]: '' }))
    } catch (err) {
      setItemErrors((current) => ({ ...current, [item.id]: err.response?.data?.message || 'Gagal memperbarui jumlah item.' }))
    } finally {
      setUpdatingId(null)
    }
  }

  const deleteItem = async (item) => {
    setUpdatingId(item.id)
    setError('')

    try {
      const response = await api.delete(`/cart/items/${item.id}`)
      setCart(response.data.data || emptyCart)
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal menghapus item.')
    } finally {
      setUpdatingId(null)
    }
  }

  const clearCart = async () => {
    setError('')

    try {
      const response = await api.delete('/cart/clear')
      setCart(response.data.data || emptyCart)
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal mengosongkan keranjang.')
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-surface">
      <Navbar />
      <main className="mx-auto w-full max-w-container-max flex-grow px-margin-mobile py-8 md:px-margin-desktop">
        <header className="mb-8">
          <h1 className="mb-2 text-4xl font-bold">Keranjang Belanja</h1>
          <p className="text-on-surface-variant">Pastikan semua item sudah sesuai sebelum melakukan pembayaran.</p>
        </header>
        {error && <div className="mb-5 rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}
        {cart.has_prescription_items && (
          <section className="mb-6 flex gap-4 rounded-xl border-2 border-dashed border-primary bg-primary-container/10 p-6">
            <span className="material-symbols-outlined text-4xl text-primary">upload_file</span>
            <div>
              <h2 className="mb-1 text-xl font-bold">Pesanan ini membutuhkan upload resep saat checkout.</h2>
              <p className="text-sm opacity-80">Beberapa obat di keranjang memerlukan resep resmi dari dokter.</p>
            </div>
          </section>
        )}
        <div className="grid grid-cols-1 items-start gap-gutter lg:grid-cols-12">
          <div className="space-y-6 lg:col-span-8">
            <section className="overflow-hidden rounded-xl border border-outline-variant bg-white">
              <div className="flex items-center justify-between border-b border-outline-variant bg-surface-container-low p-6 font-bold">
                <span>DAFTAR PRODUK</span>
                {cart.items.length > 0 && <button className="text-sm font-bold text-error" type="button" onClick={clearCart}>Kosongkan</button>}
              </div>
              {loading ? (
                <div className="p-8 text-center text-on-surface-variant">Memuat keranjang...</div>
              ) : cart.items.length === 0 ? (
                <div className="p-8 text-center">
                  <p className="text-on-surface-variant">Keranjang masih kosong.</p>
                  <Link className="mt-4 inline-flex rounded-lg bg-primary px-5 py-2.5 font-bold text-white" to="/catalog">Belanja Sekarang</Link>
                </div>
              ) : (
                <div className="divide-y divide-outline-variant">
                  {cart.items.map((item) => (
                    <CartItemRow key={item.id} error={itemErrors[item.id]} item={item} updating={updatingId === item.id} onDelete={() => deleteItem(item)} onUpdate={(quantity) => updateQuantity(item, quantity)} />
                  ))}
                </div>
              )}
            </section>
          </div>
          <aside className="sticky top-24 lg:col-span-4">
            <div className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
              <h2 className="mb-6 font-bold">Ringkasan Pesanan</h2>
              <div className="mb-6 space-y-3">
                <div className="flex justify-between text-on-surface-variant"><span>Total Item</span><span>{cart.total_quantity}</span></div>
                <div className="flex justify-between text-on-surface-variant"><span>Subtotal</span><span>{formatCurrency(cart.subtotal)}</span></div>
                <div className="flex justify-between font-bold text-primary"><span>Biaya Pengiriman</span><span>Ditentukan saat checkout</span></div>
              </div>
              <div className="mb-8 flex items-end justify-between border-t pt-4">
                <span className="text-sm">Total Sementara</span>
                <span className="text-3xl font-bold text-primary">{formatCurrency(cart.subtotal)}</span>
              </div>
              <Link className={`block w-full rounded-lg py-4 text-center font-bold text-white shadow-lg transition-all ${cart.items.length === 0 ? 'pointer-events-none bg-outline' : 'bg-primary hover:opacity-90 active:scale-95'}`} to="/checkout">
                Lanjut ke Checkout
              </Link>
            </div>
          </aside>
        </div>
      </main>
      <Footer />
    </div>
  )
}

function CartItemRow({ error, item, onDelete, onUpdate, updating }) {
  const [imageBroken, setImageBroken] = useState(false)
  const [quantity, setQuantity] = useState(String(item.quantity))
  const medicine = item.medicine

  const commitQuantity = () => {
    const nextQuantity = Number(quantity)
    if (nextQuantity === item.quantity) return
    onUpdate(nextQuantity)
  }

  const changeQuantity = (nextQuantity) => {
    setQuantity(String(nextQuantity))
    onUpdate(nextQuantity)
  }

  return (
    <div className="flex gap-6 p-6">
      {medicine?.image_url && !imageBroken ? (
        <img alt={medicine.name} src={medicine.image_url} className="h-24 w-24 rounded-lg object-cover" onError={() => setImageBroken(true)} />
      ) : (
        <div className="flex h-24 w-24 items-center justify-center rounded-lg bg-surface-container-high text-primary">
          <span className="material-symbols-outlined text-4xl">medication</span>
        </div>
      )}
      <div className="flex-grow">
        <div className="flex justify-between gap-4 font-bold">
          <div>
            <Link to={`/catalog/${medicine?.id}`} className="hover:text-primary">{medicine?.name}</Link>
            {medicine?.requires_prescription && <span className="mt-1 block w-fit rounded-full bg-error-container px-2 py-0.5 text-[10px] text-on-error-container">BUTUH RESEP</span>}
          </div>
          <span className="text-primary">{formatCurrency(item.line_total)}</span>
        </div>
        <div className="mt-4 flex items-center justify-between">
          <div className="flex items-center rounded-lg border border-outline">
            <button className="px-3 py-1 disabled:opacity-40" type="button" disabled={updating || item.quantity <= 1} onClick={() => changeQuantity(item.quantity - 1)}>-</button>
            <input
              aria-label={`Jumlah ${medicine?.name}`}
              className="w-16 border-x border-outline px-2 py-1 text-center outline-none focus:bg-primary-container/20"
              disabled={updating}
              min="1"
              max={medicine?.total_stock}
              type="number"
              value={quantity}
              onBlur={commitQuantity}
              onChange={(event) => setQuantity(event.target.value)}
              onKeyDown={(event) => { if (event.key === 'Enter') event.currentTarget.blur() }}
            />
            <button className="px-3 py-1 disabled:opacity-40" type="button" disabled={updating || item.quantity >= medicine?.total_stock} onClick={() => changeQuantity(item.quantity + 1)}>+</button>
          </div>
          <button className="flex items-center gap-1 text-sm font-bold text-error disabled:opacity-40" type="button" disabled={updating} onClick={onDelete}>
            <span className="material-symbols-outlined text-[18px]">delete</span> Hapus
          </button>
        </div>
        <p className="mt-2 text-xs text-on-surface-variant">Stok tersedia: {medicine?.total_stock ?? 0}</p>
        {error && <p className="mt-1 text-xs font-semibold text-error">{error}</p>}
      </div>
    </div>
  )
}
