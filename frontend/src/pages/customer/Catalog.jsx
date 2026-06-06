import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api from '../../api/axios'
import CartModal from '../../components/CartModal'
import CategoryFilter from '../../components/CategoryFilter'
import Footer from '../../components/Footer'
import Navbar from '../../components/Navbar'
import Toast from '../../components/Toast'
import { useAuth } from '../../context/AuthContext'
import { formatMedicinePrice } from '../../utils/medicinePricing'

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))

const getApiErrorMessage = (err, fallback) => err.response?.data?.message || fallback

export default function Catalog() {
  const navigate = useNavigate()
  const { isAuthenticated, user } = useAuth()
  const [medicines, setMedicines] = useState([])
  const [categories, setCategories] = useState([])
  const [filters, setFilters] = useState({
    search: '',
    category_id: '',
    requires_prescription: '',
    sort_price: '',
  })
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [addingId, setAddingId] = useState(null)
  const [showAllCategories, setShowAllCategories] = useState(false)
  const [cartModalMedicine, setCartModalMedicine] = useState(null)
  const [cartVariantId, setCartVariantId] = useState('')
  const [cartQuantity, setCartQuantity] = useState(1)
  const [cartModalError, setCartModalError] = useState('')
  const [toast, setToast] = useState(null)
  const [suggestions, setSuggestions] = useState([])
  const [suggestionLoading, setSuggestionLoading] = useState(false)
  const [suggestionError, setSuggestionError] = useState('')
  const [suggestionOpen, setSuggestionOpen] = useState(false)

  const fetchCategories = async () => {
    try {
      const response = await api.get('/categories')
      setCategories(response.data.data || [])
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal memuat kategori.'))
    }
  }

  const fetchMedicines = async (page = 1) => {
    setLoading(true)
    setError('')

    try {
      const response = await api.get('/catalog/medicines', {
        params: {
          page,
          per_page: 12,
          search: filters.search || undefined,
          category_id: filters.category_id || undefined,
          requires_prescription: filters.requires_prescription || undefined,
          sort_price: filters.sort_price || undefined,
        },
      })
      setMedicines(response.data.data || [])
      setPagination({
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        total: response.data.total || 0,
      })
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal memuat katalog obat.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    // Kategori publik diperlukan untuk filter katalog.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    fetchCategories()
  }, [])

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      fetchMedicines(1)
    }, 350)

    return () => window.clearTimeout(timeout)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters])

  useEffect(() => {
    const query = filters.search.trim()

    const timeout = window.setTimeout(async () => {
      if (query.length < 2) {
        setSuggestions([])
        setSuggestionOpen(false)
        setSuggestionError('')
        return
      }

      setSuggestionLoading(true)
      setSuggestionError('')

      try {
        const response = await api.get('/catalog/medicines/autocomplete', {
          params: {
            q: query,
            limit: 8,
            category_id: filters.category_id || undefined,
            requires_prescription: filters.requires_prescription || undefined,
          },
        })
        setSuggestions(response.data.data || [])
        setSuggestionOpen(true)
      } catch (err) {
        setSuggestionError(getApiErrorMessage(err, 'Gagal memuat autocomplete.'))
      } finally {
        setSuggestionLoading(false)
      }
    }, 300)

    return () => window.clearTimeout(timeout)
  }, [filters.category_id, filters.requires_prescription, filters.search])

  const handleFilterChange = (event) => {
    setFilters((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }))
  }

  const handleCategoryChange = (categoryId) => {
    setFilters((current) => ({
      ...current,
      category_id: categoryId,
    }))
  }

  const handleSearchSubmit = (event) => {
    event.preventDefault()
    setSuggestionOpen(false)
    fetchMedicines(1)
  }

  const openCartModal = (medicine) => {
    if (!isAuthenticated || user?.role !== 'pelanggan') {
      navigate('/login')
      return
    }

    setCartModalMedicine(medicine)
    setCartVariantId('')
    setCartQuantity(1)
    setCartModalError('')
  }

  const addToCart = async ({ buyNow = false } = {}) => {
    if (!cartModalMedicine) {
      return
    }

    const quantity = Number(cartQuantity)
    const variants = cartModalMedicine.has_variants ? (cartModalMedicine.variants || []) : []
    const selectedVariant = variants.find((variant) => String(variant.id) === String(cartVariantId))
    const effectiveStock = selectedVariant?.stock ?? cartModalMedicine.total_stock

    if (!Number.isInteger(quantity) || quantity < 1) {
      setCartModalError('Jumlah minimal 1.')
      return
    }

    if (cartModalMedicine.has_variants && !selectedVariant) {
      setCartModalError('Varian wajib dipilih.')
      return
    }

    if (quantity > effectiveStock) {
      setCartModalError('Jumlah melebihi stok tersedia.')
      return
    }

    setAddingId(cartModalMedicine.id)
    setError('')
    setCartModalError('')

    try {
      const response = await api.post('/cart/items', {
        medicine_id: cartModalMedicine.id,
        medicine_variant_id: selectedVariant?.id,
        quantity,
      })
      const addedItem = response.data.data?.items?.find(
        (item) => item.medicine_id === cartModalMedicine.id
          && (item.medicine_variant_id || null) === (selectedVariant?.id || null),
      )

      setCartModalMedicine(null)

      if (buyNow) {
        navigate('/cart')
        return
      }

      setToast({
        message: 'Produk berhasil dimasukkan ke keranjang.',
        actionLabel: 'Undo',
        onAction: async () => {
          if (addedItem?.id) {
            await api.delete(`/cart/items/${addedItem.id}`)
          }
          setToast(null)
        },
      })
    } catch (err) {
      setCartModalError(getApiErrorMessage(err, 'Gagal menambahkan obat ke keranjang.'))
    } finally {
      setAddingId(null)
    }
  }

  return (
    <div className="min-h-screen bg-surface">
      <Navbar />
      <header className="bg-primary-container px-margin-mobile py-12 text-center md:px-margin-desktop">
        <h1 className="mb-6 text-4xl font-bold text-white">Solusi Kesehatan Terpercaya Anda</h1>
        <form className="relative mx-auto max-w-2xl" onSubmit={handleSearchSubmit}>
          <input
            className="w-full rounded-xl border border-slate-200 bg-white px-14 py-4 text-lg text-on-surface shadow-xl placeholder:text-slate-500 outline-none transition-all hover:border-outline focus:border-primary focus:ring-4 focus:ring-primary/15"
            name="search"
            value={filters.search}
            onChange={handleFilterChange}
            onFocus={() => suggestions.length > 0 && setSuggestionOpen(true)}
            placeholder="Cari obat atau alat kesehatan..."
          />
          <span className="material-symbols-outlined absolute left-5 top-1/2 -translate-y-1/2 text-3xl text-primary">search</span>
          <button className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg bg-primary px-6 py-2 font-bold text-white" type="submit">
            Cari
          </button>
          {suggestionOpen && (
            <div className="absolute left-0 right-0 top-full z-40 mt-2 overflow-hidden rounded-xl border border-outline-variant bg-white text-left shadow-xl">
              {suggestionLoading ? (
                <div className="px-4 py-3 text-sm text-on-surface-variant">Memuat saran...</div>
              ) : suggestionError ? (
                <div className="px-4 py-3 text-sm text-error">{suggestionError}</div>
              ) : suggestions.length === 0 ? (
                <div className="px-4 py-3 text-sm text-on-surface-variant">Tidak ada saran obat.</div>
              ) : (
                suggestions.map((medicine) => (
                  <button
                    key={medicine.id}
                    className="flex w-full items-center justify-between gap-4 border-b border-outline-variant px-4 py-3 text-left hover:bg-surface-container-low last:border-b-0"
                    type="button"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => navigate('/catalog/' + medicine.id)}
                  >
                    <span>
                      <span className="block font-bold text-on-surface">{medicine.name}</span>
                      <span className="text-xs text-on-surface-variant">{medicine.category || 'Tanpa kategori'}</span>
                    </span>
                    <span className="shrink-0 font-bold text-primary">{formatCurrency(medicine.price)}</span>
                  </button>
                ))
              )}
            </div>
          )}
        </form>
      </header>
      <main className="mx-auto flex max-w-container-max gap-gutter px-margin-mobile py-10 md:px-margin-desktop">
        <aside className="hidden w-sidebar-width flex-shrink-0 md:block">
          <div className="sticky top-24 space-y-8">
            <CategoryFilter
              backendCategories={categories}
              selectedCategoryId={filters.category_id}
              showAll={showAllCategories}
              onCategoryChange={handleCategoryChange}
              onToggleShowAll={() => setShowAllCategories((current) => !current)}
            />
            <div>
              <h3 className="mb-4 text-sm font-bold uppercase tracking-wider">Filter</h3>
              <div className="space-y-3">
                <select className="w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm" name="requires_prescription" value={filters.requires_prescription} onChange={handleFilterChange}>
                  <option value="">Semua Obat</option>
                  <option value="0">Obat Bebas</option>
                  <option value="1">Obat Resep</option>
                </select>
                <select className="w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm" name="sort_price" value={filters.sort_price} onChange={handleFilterChange}>
                  <option value="">Urut Nama</option>
                  <option value="asc">Harga Termurah</option>
                  <option value="desc">Harga Termahal</option>
                </select>
              </div>
            </div>
          </div>
        </aside>
        <section className="flex-grow">
          <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
              <h2 className="text-3xl font-bold">Katalog Obat</h2>
              <p className="mt-1 text-sm text-on-surface-variant">{pagination.total} obat ditemukan</p>
            </div>
          </div>

          {error && (
            <div className="mb-5 rounded-lg border border-error-container bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">
              {error}
            </div>
          )}

          {loading ? (
            <div className="rounded-xl border border-outline-variant bg-white p-8 text-center text-on-surface-variant">
              Memuat katalog obat...
            </div>
          ) : medicines.length === 0 ? (
            <div className="rounded-xl border border-outline-variant bg-white p-8 text-center text-on-surface-variant">
              Tidak ada obat yang sesuai filter.
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {medicines.map((medicine) => (
                <MedicineCard key={medicine.id} medicine={medicine} adding={addingId === medicine.id} onAdd={() => openCartModal(medicine)} />
              ))}
            </div>
          )}

          <div className="mt-8 flex items-center justify-end gap-2 text-sm">
            <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={pagination.current_page <= 1} onClick={() => fetchMedicines(pagination.current_page - 1)}>
              Sebelumnya
            </button>
            <span className="rounded-lg bg-surface-container-low px-4 py-2">{pagination.current_page} / {pagination.last_page}</span>
            <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={pagination.current_page >= pagination.last_page} onClick={() => fetchMedicines(pagination.current_page + 1)}>
              Berikutnya
            </button>
          </div>
        </section>
      </main>
      <CartModal
        error={cartModalError}
        loading={addingId === cartModalMedicine?.id}
        medicine={cartModalMedicine}
        quantity={cartQuantity}
        onBuyNow={() => addToCart({ buyNow: true })}
        onClose={() => {
          setCartModalMedicine(null)
          setCartVariantId('')
        }}
        onQuantityChange={setCartQuantity}
        onVariantChange={setCartVariantId}
        selectedVariantId={cartVariantId}
        onSubmitCart={() => addToCart({ buyNow: false })}
      />
      {toast && (
        <Toast
          actionLabel={toast.actionLabel}
          message={toast.message}
          onAction={toast.onAction}
          onClose={() => setToast(null)}
        />
      )}
      <Footer />
    </div>
  )
}

function MedicineCard({ adding, medicine, onAdd }) {
  const [imageBroken, setImageBroken] = useState(false)
  const stockAvailable = medicine.total_stock > 0

  return (
    <article className="group flex flex-col overflow-hidden rounded-xl border border-outline-variant bg-white transition-all hover:shadow-lg">
      <Link to={`/catalog/${medicine.id}`} className="relative aspect-square overflow-hidden">
        {medicine.image_url && !imageBroken ? (
          <img alt={medicine.name} src={medicine.image_url} className="h-full w-full object-contain transition-all duration-500 group-hover:scale-105" onError={() => setImageBroken(true)} />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-surface-container-low text-primary">
            <span className="material-symbols-outlined text-6xl">medication</span>
          </div>
        )}
        <div className="absolute left-3 top-3 flex flex-wrap gap-2">
          {medicine.requires_prescription && <span className="rounded-full bg-red-100 px-3 py-1 text-[10px] font-bold uppercase text-red-800">Resep Diperlukan</span>}
          <span className={`rounded-full px-3 py-1 text-[10px] font-bold uppercase ${stockAvailable ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700'}`}>
            {stockAvailable ? 'Tersedia' : 'Stok Habis'}
          </span>
        </div>
      </Link>
      <div className="flex flex-grow flex-col p-5">
        <Link to={`/catalog/${medicine.id}`} className="mb-1 text-lg font-bold hover:text-primary">{medicine.name}</Link>
        <p className="mb-2 text-xs font-semibold text-primary">{medicine.category?.name || 'Tanpa kategori'}</p>
        <p className="mb-4 line-clamp-2 flex-grow text-sm text-on-surface-variant">{medicine.description || 'Detail obat belum tersedia.'}</p>
        <div className="mb-4 flex items-baseline justify-between gap-2">
          <span className="text-2xl font-bold text-primary">{formatMedicinePrice(medicine, formatCurrency)}</span>
          <span className="text-xs text-on-surface-variant">Stok {medicine.total_stock}</span>
        </div>
        <button className="w-full rounded-lg bg-primary py-3 font-bold text-white transition-all hover:opacity-90 active:scale-95 disabled:bg-outline" type="button" disabled={!stockAvailable || adding} onClick={() => onAdd(medicine)}>
          {adding ? 'Menambahkan...' : stockAvailable ? 'Tambahkan ke Keranjang' : 'Stok Habis'}
        </button>
      </div>
    </article>
  )
}
