import { useEffect, useState } from 'react'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import Sidebar from '../../components/Sidebar'

const statusLabels = {
  queued: 'Dalam antrean',
  processing: 'Diproses',
  completed: 'Selesai',
  failed: 'Gagal',
}

export default function MedicineImport() {
  const [file, setFile] = useState(null)
  const [importJob, setImportJob] = useState(null)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  useEffect(() => {
    if (!importJob || ['completed', 'failed'].includes(importJob.status)) {
      return undefined
    }

    const intervalId = window.setInterval(async () => {
      try {
        const response = await api.get('/admin/medicines/imports/' + importJob.id)
        setImportJob(response.data.data)
      } catch (err) {
        setError(err.response?.data?.message || 'Gagal memuat status import.')
      }
    }, 2000)

    return () => window.clearInterval(intervalId)
  }, [importJob])

  const submitImport = async (event) => {
    event.preventDefault()
    setError('')
    setMessage('')

    if (!(file instanceof File)) {
      setError('Pilih file CSV/XLSX terlebih dahulu.')
      return
    }

    const payload = new FormData()
    payload.append('file', file)
    setUploading(true)

    try {
      const response = await api.post('/admin/medicines/import', payload)
      setImportJob(response.data.data)
      setMessage('File import masuk antrean queue.')
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal upload file import.')
    } finally {
      setUploading(false)
    }
  }

  const progress = importJob?.total_rows > 0
    ? Math.round(((importJob.processed_rows + importJob.failed_rows) / importJob.total_rows) * 100)
    : 0

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="medicine_import" />
      <main className="ml-sidebar-width flex min-w-0 flex-1 flex-col">
        <AdminHeader title="Import Obat" subtitle="Upload CSV/XLSX untuk import obat melalui queue job." />

        <div className="space-y-6 p-6 xl:p-8">
          <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
            <h2 className="mb-2 text-xl font-bold">Format CSV</h2>
            <p className="text-sm text-on-surface-variant">
              Kolom wajib: <strong>name, category</strong>. Untuk obat tanpa varian, isi <strong>price</strong>. Untuk obat bervarian, isi <strong>has_variants, variant_name, variant_price</strong>. Kolom opsional: supplier, description, composition, dosage, side_effects, minimum_stock, requires_prescription, is_active, variant_sku, variant_is_active, variant_sort_order, batch_number, expired_date, quantity, purchase_price.
            </p>
            <form className="mt-5 flex flex-col gap-4 md:flex-row md:items-end" onSubmit={submitImport}>
              <label className="flex-1 text-sm font-semibold">
                File CSV/XLSX
                <input className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal" accept=".csv,.txt,.xlsx" type="file" onChange={(event) => setFile(event.target.files?.[0] || null)} />
              </label>
              <button className="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" type="submit" disabled={uploading}>
                {uploading ? 'Mengupload...' : 'Upload Import'}
              </button>
            </form>
          </section>

          {message && <div className="rounded-lg bg-secondary-container px-4 py-3 text-sm font-semibold text-secondary">{message}</div>}
          {error && <div className="rounded-lg bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">{error}</div>}

          <section className="rounded-xl border border-outline-variant bg-white p-6 shadow-sm">
            <h2 className="mb-4 text-xl font-bold">Status Import</h2>
            {!importJob ? (
              <div className="rounded-lg border border-dashed border-outline-variant p-8 text-center text-on-surface-variant">Belum ada import aktif.</div>
            ) : (
              <div className="space-y-4">
                <div className="grid gap-4 md:grid-cols-4">
                  <Info label="File" value={importJob.original_filename} />
                  <Info label="Status" value={statusLabels[importJob.status] || importJob.status} />
                  <Info label="Diproses" value={importJob.processed_rows || 0} />
                  <Info label="Gagal" value={importJob.failed_rows || 0} />
                </div>
                <div>
                  <div className="mb-2 flex justify-between text-sm font-semibold"><span>Progress</span><span>{progress}%</span></div>
                  <div className="h-3 overflow-hidden rounded-full bg-surface-container-high"><div className="h-full bg-primary" style={{ width: progress + '%' }}></div></div>
                </div>
                {Array.isArray(importJob.errors) && importJob.errors.length > 0 && (
                  <div className="rounded-lg bg-error-container p-4 text-sm text-on-error-container">
                    <p className="mb-2 font-bold">Error import:</p>
                    <ul className="space-y-1">
                      {importJob.errors.slice(0, 10).map((item, index) => <li key={index}>Row {item.row || '-'}: {item.message}</li>)}
                    </ul>
                  </div>
                )}
              </div>
            )}
          </section>
        </div>
      </main>
    </div>
  )
}

function Info({ label, value }) {
  return (
    <div className="rounded-lg border border-outline-variant p-4">
      <p className="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{label}</p>
      <p className="mt-1 break-words font-semibold">{value || '-'}</p>
    </div>
  )
}
