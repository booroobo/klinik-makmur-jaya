import { useCallback, useEffect, useState } from 'react'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import ConfirmModal from '../../components/ConfirmModal'
import Sidebar from '../../components/Sidebar'

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
  const [blockingId, setBlockingId] = useState(null)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [confirmBlock, setConfirmBlock] = useState(null)

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

  const toggleBlockCustomer = (customer) => {
    setConfirmBlock(customer)
  }

  const confirmToggleBlock = async () => {
    if (!confirmBlock) {
      return
    }

    setBlockingId(confirmBlock.id)
    setError('')
    setMessage('')

    try {
      const response = await api.patch(`/admin/customers/${confirmBlock.id}/toggle-block`)
      setMessage(response.data.message)
      setConfirmBlock(null)
      await fetchCustomers(pagination.current_page)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal memproses blokir pelanggan.'))
    } finally {
      setBlockingId(null)
    }
  }

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="customers" />
      <main className="ml-sidebar-width flex min-w-0 flex-1 flex-col">
        <AdminHeader title="Pelanggan" subtitle="Kelola status blokir akun pelanggan Klinik Makmur Jaya." />

        <div className="space-y-6 p-6 xl:p-8">
          <section className="rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <label className="w-full text-sm font-semibold lg:max-w-md">
                Cari Pelanggan
                <input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" placeholder="Nama, email, telepon, alamat..." type="search" value={keyword} onChange={handleKeywordChange} />
              </label>
            </div>
          </section>

          {message && <div className="rounded-lg bg-secondary-container px-4 py-3 text-sm font-semibold text-secondary">{message}</div>}
          {error && <div className="rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

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
                    <th className="px-5 py-3">Status</th>
                    <th className="px-5 py-3">Order</th>
                    <th className="px-5 py-3">Dibuat</th>
                    <th className="px-5 py-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-outline-variant">
                  {loading ? (
                    <tr><td className="px-5 py-8 text-center text-on-surface-variant" colSpan="8">Memuat pelanggan...</td></tr>
                  ) : customers.length === 0 ? (
                    <tr><td className="px-5 py-8 text-center text-on-surface-variant" colSpan="8">Belum ada pelanggan sesuai filter.</td></tr>
                  ) : customers.map((customer) => (
                    <tr key={customer.id} className="hover:bg-surface-container-low">
                      <td className="px-5 py-4 font-bold">{customer.name}</td>
                      <td className="px-5 py-4">{customer.email}</td>
                      <td className="px-5 py-4">{customer.phone || '-'}</td>
                      <td className="max-w-xs px-5 py-4 text-on-surface-variant"><span className="line-clamp-2">{customer.address || '-'}</span></td>
                      <td className="px-5 py-4">
                        {customer.is_blocked ? (
                          <span className="inline-flex rounded-full bg-error-container px-2.5 py-1 text-xs font-semibold text-on-error-container">
                            Diblokir
                          </span>
                        ) : (
                          <span className="inline-flex rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                            Aktif
                          </span>
                        )}
                      </td>
                      <td className="px-5 py-4">{customer.orders_count || 0}</td>
                      <td className="px-5 py-4 text-on-surface-variant">{formatDateTime(customer.created_at)}</td>
                      <td className="px-5 py-4">
                        <div className="flex justify-end gap-2">
                          <button 
                            className={`rounded-lg border px-3 py-1.5 text-xs font-bold transition-all disabled:opacity-50 ${
                              customer.is_blocked 
                                ? 'border-primary text-primary hover:bg-primary/5' 
                                : 'border-error text-error hover:bg-error-container'
                            }`}
                            type="button" 
                            disabled={blockingId === customer.id} 
                            onClick={() => toggleBlockCustomer(customer)}
                          >
                            {customer.is_blocked ? 'Buka Blokir' : 'Blokir'}
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

      {confirmBlock && (
        <ConfirmModal
          title={confirmBlock.is_blocked ? 'Konfirmasi Buka Blokir' : 'Konfirmasi Blokir'}
          description={`Apakah Anda yakin ingin ${confirmBlock.is_blocked ? 'membuka blokir' : 'memblokir'} akun pelanggan ${confirmBlock.name}?`}
          confirmLabel={confirmBlock.is_blocked ? 'Buka Blokir' : 'Blokir'}
          variant={confirmBlock.is_blocked ? 'primary' : 'error'}
          loading={blockingId === confirmBlock.id}
          onCancel={() => setConfirmBlock(null)}
          onConfirm={confirmToggleBlock}
        />
      )}
    </div>
  )
}
