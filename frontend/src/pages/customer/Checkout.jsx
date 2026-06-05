import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api from '../../api/axios'
import FileDropzone from '../../components/FileDropzone'
import Footer from '../../components/Footer'
import Navbar from '../../components/Navbar'
import { useAuth } from '../../context/AuthContext'

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

const getApiErrorMessage = (err, fallback) => {
  const errors = err.response?.data?.errors

  if (errors) {
    return Object.values(errors).flat().join(' ')
  }

  return err.response?.data?.message || fallback
}

export default function Checkout() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const [cart, setCart] = useState(emptyCart)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [prescriptionFile, setPrescriptionFile] = useState(null)
  const [form, setForm] = useState({
    customer_name: user?.name || '',
    customer_phone: '',
    customer_address: '',
    fulfillment_method: 'pickup',
    payment_method: 'bank_transfer',
    notes: '',
  })

  const deliveryFee = form.fulfillment_method === 'delivery' ? 10000 : 0
  const serviceFee = cart.items.length > 0 ? 2500 : 0
  const total = useMemo(
    () => Number(cart.subtotal || 0) + serviceFee + deliveryFee,
    [cart.subtotal, deliveryFee, serviceFee],
  )

  useEffect(() => {
    const fetchCart = async () => {
      setLoading(true)
      setError('')

      try {
        const response = await api.get('/cart')
        setCart(response.data.data || emptyCart)
      } catch (err) {
        setError(getApiErrorMessage(err, 'Gagal memuat data checkout.'))
      } finally {
        setLoading(false)
      }
    }

    fetchCart()
  }, [])

  const handleChange = (event) => {
    setForm((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      if (cart.has_prescription_items && !(prescriptionFile instanceof File)) {
        setError('File resep wajib diunggah untuk pesanan yang berisi obat resep.')
        return
      }

      const payload = new FormData()

      Object.entries(form).forEach(([key, value]) => {
        if (value !== '') {
          payload.append(key, value)
        }
      })

      if (prescriptionFile instanceof File) {
        payload.append('prescription_file', prescriptionFile)
      }

      const response = await api.post('/checkout', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      navigate(`/my-orders/${response.data.data.id}`, {
        replace: true,
        state: { message: `Checkout berhasil. Nomor pesanan ${response.data.order_number}.` },
      })
    } catch (err) {
      setError(getApiErrorMessage(err, 'Checkout gagal. Periksa kembali data pesanan.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-surface">
      <Navbar />
      <main className="mx-auto w-full max-w-container-max flex-grow px-margin-mobile py-8 md:px-margin-desktop">
        <Link to="/cart" className="mb-6 inline-flex items-center gap-2 text-sm font-bold text-primary">
          <span className="material-symbols-outlined text-[18px]">arrow_back</span>
          Kembali ke keranjang
        </Link>
        <header className="mb-8">
          <h1 className="text-4xl font-bold">Checkout</h1>
          <p className="mt-2 text-on-surface-variant">Lengkapi data pesanan, metode pengambilan, pembayaran, dan resep jika diperlukan.</p>
        </header>

        {error && <div className="mb-5 rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

        {loading ? (
          <div className="rounded-xl border border-outline-variant bg-white p-8 text-center text-on-surface-variant">
            Memuat checkout...
          </div>
        ) : cart.items.length === 0 ? (
          <div className="rounded-xl border border-outline-variant bg-white p-8 text-center">
            <p className="text-on-surface-variant">Keranjang kosong. Tambahkan produk sebelum checkout.</p>
            <Link className="mt-4 inline-flex rounded-lg bg-primary px-5 py-2.5 font-bold text-white" to="/catalog">Belanja Sekarang</Link>
          </div>
        ) : (
          <div className="grid items-start gap-gutter lg:grid-cols-12">
            <form className="space-y-6 lg:col-span-8" onSubmit={handleSubmit}>
              <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                <h2 className="mb-5 text-xl font-bold">Data Pelanggan</h2>
                <div className="grid gap-4 md:grid-cols-2">
                  <Field label="Nama Pelanggan" name="customer_name" value={form.customer_name} onChange={handleChange} required />
                  <Field label="Telepon" name="customer_phone" value={form.customer_phone} onChange={handleChange} />
                </div>
                <TextArea label="Alamat" name="customer_address" value={form.customer_address} onChange={handleChange} placeholder="Wajib diisi jika memilih pengiriman." />
              </section>

              <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                <h2 className="mb-5 text-xl font-bold">Metode Pesanan</h2>
                <div className="grid gap-4 md:grid-cols-2">
                  <SelectField label="Pengambilan" name="fulfillment_method" value={form.fulfillment_method} onChange={handleChange} options={[
                    { value: 'pickup', label: 'Ambil di Klinik' },
                    { value: 'delivery', label: 'Pengiriman' },
                  ]} />
                  <SelectField label="Pembayaran" name="payment_method" value={form.payment_method} onChange={handleChange} options={[
                    { value: 'bank_transfer', label: 'Transfer Bank' },
                    { value: 'cashier', label: 'Bayar di Kasir' },
                    { value: 'e_wallet', label: 'E-Wallet' },
                  ]} />
                </div>
                <TextArea label="Catatan" name="notes" value={form.notes} onChange={handleChange} placeholder="Catatan tambahan untuk pesanan." />
              </section>

              {cart.has_prescription_items && (
                <section className="rounded-xl border-2 border-dashed border-primary bg-primary-container/10 p-6">
                  <div className="mb-4 flex gap-3">
                    <span className="material-symbols-outlined text-4xl text-primary">upload_file</span>
                    <div>
                      <h2 className="text-xl font-bold">Upload Resep Wajib</h2>
                      <p className="text-sm text-on-surface-variant">Keranjang berisi obat resep. Unggah file JPG, PNG, WEBP, atau PDF maksimal 4 MB.</p>
                    </div>
                  </div>
                  <FileDropzone
                    accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                    description="Tarik resep ke area ini atau klik untuk memilih file JPG, PNG, WEBP, atau PDF maksimal 4 MB."
                    label="File Resep"
                    maxSizeMb={4}
                    name="prescription_file"
                    selectedFile={prescriptionFile}
                    onFileSelect={setPrescriptionFile}
                  />
                  {prescriptionFile && (
                    <p className="mt-3 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-primary">
                      File dipilih: {prescriptionFile.name}
                    </p>
                  )}
                </section>
              )}

              <div className="flex flex-wrap justify-end gap-3">
                <Link className="rounded-lg border border-outline-variant bg-white px-5 py-3 font-bold" to="/cart">Batal</Link>
                <button className="rounded-lg bg-primary px-6 py-3 font-bold text-white shadow-lg disabled:opacity-50" type="submit" disabled={submitting}>
                  {submitting ? 'Memproses...' : 'Buat Pesanan'}
                </button>
              </div>
            </form>

            <aside className="sticky top-24 lg:col-span-4">
              <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
                <h2 className="mb-5 text-xl font-bold">Ringkasan</h2>
                <div className="max-h-[340px] space-y-4 overflow-y-auto pr-1">
                  {cart.items.map((item) => (
                    <div key={item.id} className="flex justify-between gap-4 border-b border-outline-variant pb-3 text-sm">
                      <div>
                        <p className="font-bold">{item.medicine?.name}</p>
                        <p className="text-on-surface-variant">{item.quantity} x {formatCurrency(item.medicine?.price)}</p>
                      </div>
                      <span className="font-bold text-primary">{formatCurrency(item.line_total)}</span>
                    </div>
                  ))}
                </div>
                <div className="mt-5 space-y-3 text-sm">
                  <SummaryRow label="Subtotal" value={formatCurrency(cart.subtotal)} />
                  <SummaryRow label="Biaya Layanan" value={formatCurrency(serviceFee)} />
                  <SummaryRow label="Biaya Pengiriman" value={formatCurrency(deliveryFee)} />
                </div>
                <div className="mt-5 flex items-end justify-between border-t border-outline-variant pt-4">
                  <span className="font-bold">Total</span>
                  <span className="text-3xl font-bold text-primary">{formatCurrency(total)}</span>
                </div>
              </section>
            </aside>
          </div>
        )}
      </main>
      <Footer />
    </div>
  )
}

function Field({ label, name, onChange, required = false, value }) {
  return (
    <label className="block text-sm font-semibold">
      {label}
      <input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" name={name} value={value} onChange={onChange} required={required} />
    </label>
  )
}

function TextArea({ label, name, onChange, placeholder, value }) {
  return (
    <label className="mt-4 block text-sm font-semibold">
      {label}
      <textarea className="mt-1 min-h-24 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" name={name} value={value} onChange={onChange} placeholder={placeholder} />
    </label>
  )
}

function SelectField({ label, name, onChange, options, value }) {
  return (
    <label className="block text-sm font-semibold">
      {label}
      <select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" name={name} value={value} onChange={onChange}>
        {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
      </select>
    </label>
  )
}

function SummaryRow({ label, value }) {
  return (
    <div className="flex justify-between text-on-surface-variant">
      <span>{label}</span>
      <span>{value}</span>
    </div>
  )
}
