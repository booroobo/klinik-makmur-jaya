import { useCallback, useEffect, useState } from 'react'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import Sidebar from '../../components/Sidebar'

const emptyForm = {
  name: '',
  email: '',
  phone: '',
  address: '',
  password: '',
  password_confirmation: '',
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

export default function Customers() {
  const [customers, setCustomers] = useState([])
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [keyword, setKeyword] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [deletingId, setDeletingId] = useState(null)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editingCustomer, setEditingCustomer] = useState(null)
  const [form, setForm] = useState(emptyForm)

  const fetchCustomers = useCallback(async (page = 1, search = keyword) => {
    setLoading(true)
    setError('')

    try {
      const response = await api.get('/admin/customers', {
        params: { page, per_page: 10, keyword: search || undefined },
      })
      setCustomers(response.data.data || [])
      setPagination({
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        total: response.data.total || 0,
      })
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal memuat pelanggan.'))
    } finally {
      setLoading(false)
    }
  }, [keyword])

  useEffect(() => {
    const timer = window.setTimeout(() => fetchCustomers(1), 300)

    return () => window.clearTimeout(timer)
  }, [fetchCustomers])

  const handleKeywordChange = (event) => {
    setKeyword(event.target.value)
  }

  const openCreateForm = () => {
    setEditingCustomer(null)
    setForm(emptyForm)
    setFormOpen(true)
    setError('')
    setMessage('')
  }

  const openEditForm = (customer) => {
    setEditingCustomer(customer)
    setForm({
      name: customer.name || '',
      email: customer.email || '',
      phone: customer.phone || '',
      address: customer.address || '',
      password: '',
      password_confirmation: '',
    })
    setFormOpen(true)
    setError('')
    setMessage('')
  }

  const handleFormChange = (event) => {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  const validateForm = () => {
    if (!form.name.trim() || !form.email.trim() || !form.phone.trim() || !form.address.trim()) {
      return 'Nama, email, telepon, dan alamat wajib diisi.'
    }

    if (!/^[0-9+\-\s()]{10,50}$/.test(form.phone)) {
      return 'Telepon minimal 10 digit dan hanya boleh berisi angka/simbol telepon.'
    }

    if (!editingCustomer && form.password.length < 8) {
      return 'Password minimal 8 karakter.'
    }

    if (form.password && form.password.length < 8) {
      return 'Password minimal 8 karakter.'
    }

    if (form.password !== form.password_confirmation) {
      return 'Konfirmasi password tidak sama.'
    }

    return ''
  }

  const submitForm = async (event) => {
    event.preventDefault()
    const validationError = validateForm()

    if (validationError) {
      setError(validationError)
      return
    }

    setSaving(true)
    setError('')
    setMessage('')

    try {
      const payload = { ...form }
      if (editingCustomer && !payload.password) {
        delete payload.password
        delete payload.password_confirmation
      }

      if (editingCustomer) {
        await api.put('/admin/customers/' + editingCustomer.id, payload)
        setMessage('Pelanggan berhasil diperbarui.')
      } else {
        await api.post('/admin/customers', payload)
        setMessage('Pelanggan berhasil dibuat.')
      }

      setFormOpen(false)
      setEditingCustomer(null)
      setForm(emptyForm)
      await fetchCustomers(pagination.current_page)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menyimpan pelanggan.'))
    } finally {
      setSaving(false)
    }
  }

  const deleteCustomer = async (customer) => {
    setDeletingId(customer.id)
    setError('')
    setMessage('')

    try {
      await api.delete('/admin/customers/' + customer.id)
      setMessage('Pelanggan berhasil dihapus.')
      await fetchCustomers(pagination.current_page)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menghapus pelanggan.'))
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="customers" />
      <main className="ml-sidebar-width flex min-w-0 flex-1 flex-col">
        <AdminHeader title="Pelanggan" subtitle="Kelola data akun pelanggan Klinik Makmur Jaya." />

        <div className="space-y-6 p-6 xl:p-8">
          <section className="rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <label className="w-full text-sm font-semibold lg:max-w-md">
                Cari Pelanggan
                <input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" placeholder="Nama, email, telepon, alamat..." type="search" value={keyword} onChange={handleKeywordChange} />
              </label>
              <button className="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white" type="button" onClick={openCreateForm}>
                Tambah Pelanggan
              </button>
            </div>
          </section>

          {message && <div className="rounded-lg bg-secondary-container px-4 py-3 text-sm font-semibold text-secondary">{message}</div>}
          {error && <div className="rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

          {formOpen && (
            <section className="rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
              <div className="mb-4 flex items-center justify-between">
                <h2 className="font-bold">{editingCustomer ? 'Edit Pelanggan' : 'Tambah Pelanggan'}</h2>
                <button className="rounded-lg px-3 py-1.5 text-sm font-bold text-on-surface-variant hover:bg-surface-container-low" type="button" onClick={() => setFormOpen(false)}>
                  Tutup
                </button>
              </div>
              <form className="grid gap-4 md:grid-cols-2" onSubmit={submitForm}>
                <Input label="Nama" name="name" required value={form.name} onChange={handleFormChange} />
                <Input label="Email" name="email" required type="email" value={form.email} onChange={handleFormChange} />
                <Input label="Telepon" name="phone" required value={form.phone} onChange={handleFormChange} />
                <label className="text-sm font-semibold md:col-span-2">Alamat<textarea className="mt-1 min-h-24 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" name="address" required value={form.address} onChange={handleFormChange} /></label>
                <Input label={editingCustomer ? 'Password Baru (opsional)' : 'Password'} name="password" required={!editingCustomer} type="password" value={form.password} onChange={handleFormChange} />
                <Input label="Konfirmasi Password" name="password_confirmation" required={!editingCustomer || Boolean(form.password)} type="password" value={form.password_confirmation} onChange={handleFormChange} />
                <div className="md:col-span-2">
                  <button className="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" type="submit" disabled={saving}>
                    {saving ? 'Menyimpan...' : 'Simpan Pelanggan'}
                  </button>
                </div>
              </form>
            </section>
          )}

          <section className="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
            <div className="border-b border-outline-variant bg-surface-container-low px-5 py-4">
              <h2 className="font-bold">Daftar Pelanggan</h2>
              <p className="text-sm text-on-surface-variant">{pagination.total} pelanggan ditemukan</p>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-[980px] w-full text-left text-sm">
                <thead className="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                  <tr>
                    <th className="px-5 py-3">Nama</th>
                    <th className="px-5 py-3">Email</th>
                    <th className="px-5 py-3">Telepon</th>
                    <th className="px-5 py-3">Alamat</th>
                    <th className="px-5 py-3">Order</th>
                    <th className="px-5 py-3">Dibuat</th>
                    <th className="px-5 py-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-outline-variant">
                  {loading ? (
                    <tr><td className="px-5 py-8 text-center text-on-surface-variant" colSpan="7">Memuat pelanggan...</td></tr>
                  ) : customers.length === 0 ? (
                    <tr><td className="px-5 py-8 text-center text-on-surface-variant" colSpan="7">Belum ada pelanggan sesuai filter.</td></tr>
                  ) : customers.map((customer) => (
                    <tr key={customer.id} className="hover:bg-surface-container-low">
                      <td className="px-5 py-4 font-bold">{customer.name}</td>
                      <td className="px-5 py-4">{customer.email}</td>
                      <td className="px-5 py-4">{customer.phone || '-'}</td>
                      <td className="max-w-xs px-5 py-4 text-on-surface-variant"><span className="line-clamp-2">{customer.address || '-'}</span></td>
                      <td className="px-5 py-4">{customer.orders_count || 0}</td>
                      <td className="px-5 py-4 text-on-surface-variant">{formatDateTime(customer.created_at)}</td>
                      <td className="px-5 py-4">
                        <div className="flex justify-end gap-2">
                          <button className="rounded-lg border border-primary px-3 py-1.5 text-xs font-bold text-primary" type="button" onClick={() => openEditForm(customer)}>Edit</button>
                          <button className="rounded-lg border border-error px-3 py-1.5 text-xs font-bold text-error disabled:opacity-50" type="button" disabled={deletingId === customer.id} onClick={() => deleteCustomer(customer)}>
                            {deletingId === customer.id ? 'Hapus...' : 'Delete'}
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="flex flex-col gap-3 border-t border-outline-variant p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
              <span className="text-on-surface-variant">Halaman {pagination.current_page} dari {pagination.last_page}</span>
              <div className="flex justify-end gap-2">
                <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={loading || pagination.current_page <= 1} onClick={() => fetchCustomers(pagination.current_page - 1)}>Sebelumnya</button>
                <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={loading || pagination.current_page >= pagination.last_page} onClick={() => fetchCustomers(pagination.current_page + 1)}>Berikutnya</button>
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
