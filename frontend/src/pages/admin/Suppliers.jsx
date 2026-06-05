import { useCallback, useEffect, useMemo, useState } from 'react'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import Sidebar from '../../components/Sidebar'
import { useAuth } from '../../context/AuthContext'

const emptyForm = {
  name: '',
  email: '',
  phone: '',
  address: '',
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID')
}

const getApiErrorMessage = (error, fallback) => {
  const errors = error.response?.data?.errors
  if (errors) {
    const first = Object.values(errors)[0]
    if (Array.isArray(first) && first[0]) return first[0]
  }

  return error.response?.data?.message || fallback
}

export default function Suppliers() {
  const { user } = useAuth()
  const isAdmin = user?.role?.toLowerCase() === 'admin'
  const [suppliers, setSuppliers] = useState([])
  const [keyword, setKeyword] = useState('')
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [deletingId, setDeletingId] = useState(null)
  const [deletedSupplier, setDeletedSupplier] = useState(null)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editingSupplier, setEditingSupplier] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const perPage = 10

  const fetchSuppliers = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const response = await api.get('/suppliers')
      setSuppliers(response.data.data || [])
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal memuat supplier.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    // Daftar supplier dimuat dari API saat halaman dibuka.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    fetchSuppliers()
  }, [fetchSuppliers])

  const filteredSuppliers = useMemo(() => {
    const value = keyword.trim().toLowerCase()
    if (!value) return suppliers

    return suppliers.filter((supplier) => [
      supplier.name,
      supplier.email,
      supplier.phone,
      supplier.address,
    ].some((field) => String(field || '').toLowerCase().includes(value)))
  }, [keyword, suppliers])

  const lastPage = Math.max(1, Math.ceil(filteredSuppliers.length / perPage))
  const visibleSuppliers = filteredSuppliers.slice((page - 1) * perPage, page * perPage)

  const handleKeywordChange = (event) => {
    setKeyword(event.target.value)
    setPage(1)
  }

  const openCreateForm = () => {
    setEditingSupplier(null)
    setForm(emptyForm)
    setFormOpen(true)
    setError('')
    setMessage('')
  }

  const openEditForm = (supplier) => {
    setEditingSupplier(supplier)
    setForm({
      name: supplier.name || '',
      email: supplier.email || '',
      phone: supplier.phone || '',
      address: supplier.address || '',
    })
    setFormOpen(true)
    setError('')
    setMessage('')
  }

  const handleFormChange = (event) => {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  const submitForm = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError('')
    setMessage('')

    try {
      if (editingSupplier) {
        await api.put('/suppliers/' + editingSupplier.id, form)
        setMessage('Supplier berhasil diperbarui.')
      } else {
        await api.post('/suppliers', form)
        setMessage('Supplier berhasil dibuat.')
      }

      setFormOpen(false)
      setEditingSupplier(null)
      setForm(emptyForm)
      await fetchSuppliers()
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menyimpan supplier.'))
    } finally {
      setSaving(false)
    }
  }

  const deleteSupplier = async (supplier) => {
    if (!window.confirm(`Hapus supplier ${supplier.name}?`)) return
    setDeletingId(supplier.id)
    setError('')
    setMessage('')

    try {
      await api.delete('/suppliers/' + supplier.id)
      setDeletedSupplier(supplier)
      setSuppliers((current) => current.filter((item) => item.id !== supplier.id))
      setMessage('Supplier berhasil dihapus.')
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menghapus supplier.'))
    } finally {
      setDeletingId(null)
    }
  }

  const undoDelete = async () => {
    if (!deletedSupplier) return
    const supplier = deletedSupplier
    setDeletedSupplier(null)
    setError('')

    try {
      const response = await api.post('/suppliers/' + supplier.id + '/restore')
      setSuppliers((current) => [...current, response.data.data].sort((a, b) => a.name.localeCompare(b.name)))
      setMessage('Penghapusan supplier dibatalkan.')
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal mengembalikan supplier.'))
    }
  }

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="suppliers" />
      <main className="ml-sidebar-width flex min-w-0 flex-1 flex-col">
        <AdminHeader
          title="Supplier"
          subtitle="Kelola data supplier obat dan kontak pemasok."
        />

        <div className="space-y-6 p-6 xl:p-8">
          <section className="rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div className="w-full lg:max-w-md">
                <label className="text-sm font-semibold text-on-surface" htmlFor="supplier_search">Cari Supplier</label>
                <input
                  className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm outline-none focus:border-primary"
                  id="supplier_search"
                  placeholder="Cari nama, email, telepon, alamat..."
                  type="search"
                  value={keyword}
                  onChange={handleKeywordChange}
                />
              </div>
              {isAdmin && (
                <button className="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white" type="button" onClick={openCreateForm}>
                  Tambah Supplier
                </button>
              )}
            </div>
          </section>

          {message && <div className="flex items-center justify-between gap-4 rounded-lg bg-secondary-container px-4 py-3 text-sm font-semibold text-secondary"><span>{message}</span>{deletedSupplier && <button className="rounded-lg border border-secondary px-3 py-1.5 font-bold" type="button" onClick={undoDelete}>Undo</button>}</div>}
          {error && <div className="rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

          {formOpen && isAdmin && (
            <section className="rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
              <div className="mb-4 flex items-center justify-between">
                <h2 className="font-bold">{editingSupplier ? 'Edit Supplier' : 'Tambah Supplier'}</h2>
                <button className="rounded-lg px-3 py-1.5 text-sm font-bold text-on-surface-variant hover:bg-surface-container-low" type="button" onClick={() => setFormOpen(false)}>
                  Tutup
                </button>
              </div>
              <form className="grid gap-4 md:grid-cols-2" onSubmit={submitForm}>
                <Input label="Nama Supplier" name="name" required value={form.name} onChange={handleFormChange} />
                <Input label="Email" name="email" type="email" value={form.email} onChange={handleFormChange} />
                <Input label="Telepon" name="phone" value={form.phone} onChange={handleFormChange} />
                <label className="text-sm font-semibold md:col-span-2">Alamat<textarea className="mt-1 min-h-24 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" name="address" value={form.address} onChange={handleFormChange} /></label>
                <div className="md:col-span-2">
                  <button className="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" type="submit" disabled={saving}>
                    {saving ? 'Menyimpan...' : 'Simpan Supplier'}
                  </button>
                </div>
              </form>
            </section>
          )}

          <section className="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-outline-variant bg-surface-container-low px-5 py-4">
              <div>
                <h2 className="font-bold">Daftar Supplier</h2>
                <p className="text-sm text-on-surface-variant">{filteredSuppliers.length} supplier ditemukan</p>
              </div>
              {!isAdmin && <span className="rounded-full bg-surface-container-high px-3 py-1 text-xs font-bold text-on-surface-variant">Read-only</span>}
            </div>

            <div className="overflow-x-auto">
              <table className="min-w-[900px] w-full text-left text-sm">
                <thead className="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                  <tr>
                    <th className="px-5 py-3">Nama Supplier</th>
                    <th className="px-5 py-3">Kontak</th>
                    <th className="px-5 py-3">Alamat</th>
                    <th className="px-5 py-3">Dibuat</th>
                    <th className="px-5 py-3">Diupdate</th>
                    <th className="px-5 py-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-outline-variant">
                  {loading ? (
                    <tr><td className="px-5 py-8 text-center text-on-surface-variant" colSpan="6">Memuat supplier...</td></tr>
                  ) : visibleSuppliers.length === 0 ? (
                    <tr><td className="px-5 py-8 text-center text-on-surface-variant" colSpan="6">Belum ada supplier sesuai filter.</td></tr>
                  ) : visibleSuppliers.map((supplier) => (
                    <tr key={supplier.id} className="hover:bg-surface-container-low">
                      <td className="px-5 py-4 font-bold text-on-surface">{supplier.name}</td>
                      <td className="px-5 py-4">
                        <p>{supplier.phone || '-'}</p>
                        <p className="text-xs text-on-surface-variant">{supplier.email || '-'}</p>
                      </td>
                      <td className="max-w-xs px-5 py-4 text-on-surface-variant"><span className="line-clamp-2">{supplier.address || '-'}</span></td>
                      <td className="px-5 py-4 text-on-surface-variant">{formatDateTime(supplier.created_at)}</td>
                      <td className="px-5 py-4 text-on-surface-variant">{formatDateTime(supplier.updated_at)}</td>
                      <td className="px-5 py-4">
                        <div className="flex justify-end gap-2">
                          {isAdmin ? (
                            <>
                              <button className="rounded-lg border border-primary px-3 py-1.5 text-xs font-bold text-primary" type="button" onClick={() => openEditForm(supplier)}>
                                Edit
                              </button>
                              <button className="rounded-lg border border-error px-3 py-1.5 text-xs font-bold text-error disabled:opacity-50" type="button" disabled={deletingId === supplier.id} onClick={() => deleteSupplier(supplier)}>
                                {deletingId === supplier.id ? 'Hapus...' : 'Delete'}
                              </button>
                            </>
                          ) : (
                            <span className="text-xs font-semibold text-on-surface-variant">Tidak tersedia</span>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex flex-col gap-3 border-t border-outline-variant p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
              <span className="text-on-surface-variant">Halaman {page} dari {lastPage}</span>
              <div className="flex justify-end gap-2">
                <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={page <= 1 || loading} onClick={() => setPage((current) => current - 1)}>
                  Sebelumnya
                </button>
                <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={page >= lastPage || loading} onClick={() => setPage((current) => current + 1)}>
                  Berikutnya
                </button>
              </div>
            </div>
          </section>
        </div>
      </main>
    </div>
  )
}

function Input({ label, name, type = 'text', value, onChange, required = false }) {
  return (
    <label className="text-sm font-semibold">
      {label}
      <input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" name={name} required={required} type={type} value={value} onChange={onChange} />
    </label>
  )
}
