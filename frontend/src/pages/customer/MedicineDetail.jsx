import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api from '../../api/axios'
import Footer from '../../components/Footer'
import Navbar from '../../components/Navbar'
import { useAuth } from '../../context/AuthContext'

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))

export default function MedicineDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { isAuthenticated, user } = useAuth()
  const [medicine, setMedicine] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [adding, setAdding] = useState(false)
  const [imageBroken, setImageBroken] = useState(false)

  useEffect(() => {
    const fetchMedicine = async () => {
      setLoading(true)
      setError('')

      try {
        const response = await api.get(`/catalog/medicines/${id}`)
        setMedicine(response.data.data)
      } catch (err) {
        setError(err.response?.data?.message || 'Gagal memuat detail obat.')
      } finally {
        setLoading(false)
      }
    }

    fetchMedicine()
  }, [id])

  const addToCart = async () => {
    if (!isAuthenticated || user?.role !== 'pelanggan') {
      navigate('/login')
      return
    }

    setAdding(true)
    setError('')

    try {
      await api.post('/cart/items', {
        medicine_id: medicine.id,
        quantity: 1,
      })
      navigate('/cart')
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal menambahkan obat ke keranjang.')
    } finally {
      setAdding(false)
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-surface">
      <Navbar />
      <main className="mx-auto w-full max-w-container-max flex-grow px-margin-mobile py-10 md:px-margin-desktop">
        <Link to="/catalog" className="mb-6 inline-flex items-center gap-2 text-sm font-bold text-primary">
          <span className="material-symbols-outlined text-[18px]">arrow_back</span>
          Kembali ke katalog
        </Link>

        {loading ? (
          <div className="rounded-xl border border-outline-variant bg-white p-8 text-center text-on-surface-variant">Memuat detail obat...</div>
        ) : error ? (
          <div className="rounded-xl border border-error-container bg-error-container p-6 text-on-error-container">{error}</div>
        ) : medicine && (
          <section className="grid gap-8 rounded-xl border border-outline-variant bg-white p-6 shadow-sm lg:grid-cols-[0.9fr_1.1fr]">
            <div className="overflow-hidden rounded-xl bg-surface-container-low">
              {medicine.image_url && !imageBroken ? (
                <img alt={medicine.name} src={medicine.image_url} className="aspect-square h-full w-full object-contain" onError={() => setImageBroken(true)} />
              ) : (
                <div className="flex aspect-square items-center justify-center text-primary">
                  <span className="material-symbols-outlined text-7xl">medication</span>
                </div>
              )}
            </div>
            <div>
              <div className="mb-4 flex flex-wrap gap-2">
                <span className="rounded-full bg-secondary-container px-3 py-1 text-xs font-bold text-secondary">{medicine.category?.name || 'Tanpa kategori'}</span>
                {medicine.requires_prescription && <span className="rounded-full bg-error-container px-3 py-1 text-xs font-bold text-on-error-container">Resep Diperlukan</span>}
                <span className={`rounded-full px-3 py-1 text-xs font-bold ${medicine.total_stock > 0 ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700'}`}>{medicine.total_stock > 0 ? 'Tersedia' : 'Stok Habis'}</span>
              </div>
              <h1 className="text-4xl font-bold text-on-surface">{medicine.name}</h1>
              <p className="mt-3 text-on-surface-variant">{medicine.description || 'Deskripsi obat belum tersedia.'}</p>
              <div className="my-6 text-3xl font-bold text-primary">{formatCurrency(medicine.price)}</div>
              <div className="mb-6 grid gap-4 md:grid-cols-2">
                <InfoCard label="Stok" value={`${medicine.total_stock} unit`} />
                <InfoCard label="Supplier" value={medicine.supplier?.name || '-'} />
                <InfoCard label="Dosis" value={medicine.dosage || '-'} />
                <InfoCard label="Efek Samping" value={medicine.side_effects || '-'} />
              </div>
              <section className="mb-6 rounded-xl border border-outline-variant bg-surface-container-low p-4">
                <h2 className="mb-2 font-bold">Komposisi</h2>
                <p className="text-sm leading-6 text-on-surface-variant">{medicine.composition || '-'}</p>
              </section>
              {error && <div className="mb-4 rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}
              <button className="w-full rounded-lg bg-primary py-4 font-bold text-white shadow-lg disabled:bg-outline" type="button" disabled={medicine.total_stock <= 0 || adding} onClick={addToCart}>
                {adding ? 'Menambahkan...' : medicine.total_stock > 0 ? 'Tambah ke Keranjang' : 'Stok Habis'}
              </button>
            </div>
          </section>
        )}
      </main>
      <Footer />
    </div>
  )
}

function InfoCard({ label, value }) {
  return (
    <div className="rounded-lg border border-outline-variant p-4">
      <p className="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{label}</p>
      <p className="mt-1 text-sm font-semibold text-on-surface">{value}</p>
    </div>
  )
}
