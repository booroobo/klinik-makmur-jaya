import { useEffect, useMemo, useState } from 'react'
import api from '../../api/axios'
import AdminHeader from '../../components/AdminHeader'
import ConfirmModal from '../../components/ConfirmModal'
import FileDropzone from '../../components/FileDropzone'
import MedicineDraftModal from '../../components/MedicineDraftModal'
import Sidebar from '../../components/Sidebar'
import Toast from '../../components/Toast'
import Tooltip from '../../components/Tooltip'
import { useAuth } from '../../context/AuthContext'

const emptyForm = {
  name: '',
  category_id: '',
  supplier_id: '',
  description: '',
  composition: '',
  dosage: '',
  side_effects: '',
  price: '',
  minimum_stock: '',
  requires_prescription: false,
  is_active: true,
}

const emptyBatchForm = {
  batch_number: '',
  expired_date: '',
  quantity: '',
  purchase_price: '',
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

const appendMedicinePayload = (payload, form, imageFile) => {
  Object.entries(form).forEach(([key, value]) => {
    if (value === null || value === '') {
      return
    }

    if (typeof value === 'boolean') {
      payload.append(key, value ? '1' : '0')
      return
    }

    payload.append(key, value)
  })

  if (imageFile instanceof File) {
    payload.append('image', imageFile)
  }
}

export default function Inventory() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'
  const [medicines, setMedicines] = useState([])
  const [categories, setCategories] = useState([])
  const [suppliers, setSuppliers] = useState([])
  const [filters, setFilters] = useState({
    search: '',
    category_id: '',
    requires_prescription: '',
    sort_price: '',
  })
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editingMedicine, setEditingMedicine] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [imageFile, setImageFile] = useState(null)
  const [imagePreview, setImagePreview] = useState('')
  const [saving, setSaving] = useState(false)
  const [selectedMedicine, setSelectedMedicine] = useState(null)
  const [batchForm, setBatchForm] = useState(emptyBatchForm)
  const [editingBatch, setEditingBatch] = useState(null)
  const [savingBatch, setSavingBatch] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [toast, setToast] = useState(null)
  const [draftModalOpen, setDraftModalOpen] = useState(false)
  const [drafts, setDrafts] = useState([])
  const [draftsLoading, setDraftsLoading] = useState(false)
  const [activeDraftId, setActiveDraftId] = useState(null)
  const [draftSaving, setDraftSaving] = useState(false)
  const [categoryModalOpen, setCategoryModalOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState(null)
  const [categoryForm, setCategoryForm] = useState({ name: '', description: '' })
  const [categorySaving, setCategorySaving] = useState(false)

  const medicineCategories = useMemo(
    () => categories.filter((category) => category.name !== 'Semua Kategori'),
    [categories],
  )

  const selectedMedicineLive = useMemo(() => {
    if (!selectedMedicine) {
      return null
    }

    return medicines.find((medicine) => medicine.id === selectedMedicine.id) || selectedMedicine
  }, [medicines, selectedMedicine])

  const showToast = (nextToast) => {
    setToast(nextToast)

    if (!nextToast.actionLabel) {
      window.setTimeout(() => setToast(null), 4500)
    }
  }

  const fetchMedicines = async (page = pagination.current_page || 1) => {
    setLoading(true)
    setError('')

    try {
      const params = {
        page,
        per_page: 10,
        search: filters.search || undefined,
        category_id: filters.category_id || undefined,
        requires_prescription: filters.requires_prescription || undefined,
        sort_price: filters.sort_price || undefined,
      }
      const response = await api.get('/medicines', { params })
      setMedicines(response.data.data || [])
      setPagination({
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        total: response.data.total || 0,
      })
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal memuat data obat.'))
    } finally {
      setLoading(false)
    }
  }

  const fetchMasterData = async () => {
    try {
      const [categoryResponse, supplierResponse] = await Promise.all([
        api.get('/categories'),
        api.get('/suppliers'),
      ])
      setCategories(categoryResponse.data.data || [])
      setSuppliers(supplierResponse.data.data || [])
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal memuat kategori atau supplier.'))
    }
  }

  const fetchDrafts = async () => {
    setDraftsLoading(true)

    try {
      const response = await api.get('/medicine-drafts')
      setDrafts(response.data.data || [])
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal memuat draft obat.'))
    } finally {
      setDraftsLoading(false)
    }
  }

  useEffect(() => {
    // Data master perlu disinkronkan saat halaman inventory pertama kali dibuka.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    fetchMasterData()
  }, [])

  useEffect(() => {
    // Filter table mengubah query API dan harus memuat ulang data halaman pertama.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    fetchMedicines(1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters])

  const handleFilterChange = (event) => {
    setFilters((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }))
  }

  const openCreateModal = () => {
    setEditingMedicine(null)
    setActiveDraftId(null)
    setForm(emptyForm)
    setImageFile(null)
    setImagePreview('')
    setModalOpen(true)
  }

  const openEditModal = (medicine) => {
    setEditingMedicine(medicine)
    setActiveDraftId(null)
    setForm({
      name: medicine.name || '',
      category_id: medicine.category_id || '',
      supplier_id: medicine.supplier_id || '',
      description: medicine.description || '',
      composition: medicine.composition || '',
      dosage: medicine.dosage || '',
      side_effects: medicine.side_effects || '',
      price: medicine.price || '',
      minimum_stock: medicine.minimum_stock || '',
      requires_prescription: Boolean(medicine.requires_prescription),
      is_active: Boolean(medicine.is_active),
    })
    setImageFile(null)
    setImagePreview(medicine.image_url || '')
    setModalOpen(true)
  }

  const handleImageSelect = (file) => {
    setImageFile(file)
    setImagePreview(file ? '' : editingMedicine?.image_url || '')
  }

  const handleFormChange = (event) => {
    const { checked, files, name, type, value } = event.target

    if (name === 'image') {
      handleImageSelect(files?.[0] || null)
      return
    }

    setForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError('')

    try {
      const payload = new FormData()
      appendMedicinePayload(payload, form, imageFile)

      if (editingMedicine) {
        payload.append('_method', 'PUT')
        await api.post(`/medicines/${editingMedicine.id}`, payload, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      } else {
        await api.post('/medicines', payload, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })

        if (activeDraftId) {
          await api.delete(`/medicine-drafts/${activeDraftId}`)
          setActiveDraftId(null)
        }
      }

      setModalOpen(false)
      showToast({ message: editingMedicine ? 'Obat berhasil diperbarui.' : 'Obat berhasil disimpan.' })
      await fetchMedicines(pagination.current_page)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menyimpan data obat.'))
    } finally {
      setSaving(false)
    }
  }

  const saveDraft = async () => {
    setDraftSaving(true)
    setError('')

    try {
      const payload = new FormData()
      payload.append('payload', JSON.stringify(form))

      if (imageFile instanceof File) {
        payload.append('image', imageFile)
      }

      if (activeDraftId) {
        payload.append('_method', 'PUT')
        await api.post(`/medicine-drafts/${activeDraftId}`, payload, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      } else {
        const response = await api.post('/medicine-drafts', payload, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        setActiveDraftId(response.data.data.id)
      }

      showToast({ message: 'Draft berhasil disimpan. Draft akan hilang otomatis setelah 7 hari.' })
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menyimpan draft obat.'))
    } finally {
      setDraftSaving(false)
    }
  }

  const openDraftModal = async () => {
    setDraftModalOpen(true)
    await fetchDrafts()
  }

  const loadDraft = (draft) => {
    setEditingMedicine(null)
    setActiveDraftId(draft.id)
    setForm({
      ...emptyForm,
      ...(draft.payload || {}),
      requires_prescription: Boolean(draft.payload?.requires_prescription),
      is_active: draft.payload?.is_active === undefined ? true : Boolean(draft.payload?.is_active),
    })
    setImageFile(null)
    setImagePreview(draft.image_url || '')
    setDraftModalOpen(false)
    setModalOpen(true)
    showToast({ message: 'Draft dimuat ke form tambah obat.' })
  }

  const deleteDraft = async (draft) => {
    try {
      await api.delete(`/medicine-drafts/${draft.id}`)
      await fetchDrafts()
      if (activeDraftId === draft.id) {
        setActiveDraftId(null)
      }
      showToast({ message: 'Draft berhasil dihapus.' })
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menghapus draft.'))
    }
  }

  const openCategoryModal = () => {
    setEditingCategory(null)
    setCategoryForm({ name: '', description: '' })
    setCategoryModalOpen(true)
  }

  const editCategory = (category) => {
    setEditingCategory(category)
    setCategoryForm({
      name: category.name || '',
      description: category.description || '',
    })
  }

  const handleCategoryFormChange = (event) => {
    setCategoryForm((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }))
  }

  const resetCategoryForm = () => {
    setEditingCategory(null)
    setCategoryForm({ name: '', description: '' })
  }

  const handleCategorySubmit = async (event) => {
    event.preventDefault()
    setCategorySaving(true)
    setError('')

    try {
      if (editingCategory) {
        await api.put(`/categories/${editingCategory.id}`, categoryForm)
        showToast({ message: 'Kategori berhasil diperbarui.' })
      } else {
        await api.post('/categories', categoryForm)
        showToast({ message: 'Kategori berhasil ditambahkan.' })
      }

      resetCategoryForm()
      await fetchMasterData()
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menyimpan kategori.'))
    } finally {
      setCategorySaving(false)
    }
  }

  const requestDeleteCategory = (category) => {
    setConfirmDelete({
      type: 'category',
      item: category,
      description: 'Apakah Anda yakin ingin menghapus kategori ini?',
    })
  }

  const handleBatchChange = (event) => {
    setBatchForm((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }))
  }

  const resetBatchForm = () => {
    setBatchForm(emptyBatchForm)
    setEditingBatch(null)
  }

  const editBatch = (batch) => {
    setEditingBatch(batch)
    setBatchForm({
      batch_number: batch.batch_number || '',
      expired_date: batch.expired_date?.slice(0, 10) || '',
      quantity: batch.quantity || '',
      purchase_price: batch.purchase_price || '',
    })
  }

  const handleBatchSubmit = async (event) => {
    event.preventDefault()

    if (!selectedMedicineLive) {
      return
    }

    setSavingBatch(true)
    setError('')

    try {
      if (editingBatch) {
        await api.put(`/medicine-batches/${editingBatch.id}`, {
          ...batchForm,
          purchase_price: batchForm.purchase_price || null,
        })
        showToast({ message: 'Batch berhasil diperbarui.' })
      } else {
        await api.post('/medicine-batches', {
          medicine_id: selectedMedicineLive.id,
          ...batchForm,
          purchase_price: batchForm.purchase_price || null,
        })
        showToast({ message: 'Batch berhasil ditambahkan.' })
      }

      resetBatchForm()
      await fetchMedicines(pagination.current_page)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menyimpan batch obat.'))
    } finally {
      setSavingBatch(false)
    }
  }

  const requestDeleteMedicine = (medicine) => {
    setConfirmDelete({
      type: 'medicine',
      item: medicine,
      description: 'Apakah Anda yakin ingin menghapus obat ini?',
    })
  }

  const requestDeleteBatch = (batch) => {
    setConfirmDelete({
      type: 'batch',
      item: batch,
      description: 'Apakah Anda yakin ingin menghapus batch ini?',
    })
  }

  const confirmDeleteItem = async () => {
    if (!confirmDelete) {
      return
    }

    setDeleteLoading(true)

    try {
      if (confirmDelete.type === 'medicine') {
        await api.delete(`/medicines/${confirmDelete.item.id}`)
        showToast({
          message: 'Obat berhasil dihapus.',
          actionLabel: 'Batalkan',
          onAction: () => restoreDeleted('medicine', confirmDelete.item.id),
        })
      } else if (confirmDelete.type === 'batch') {
        await api.delete(`/medicine-batches/${confirmDelete.item.id}`)
        showToast({
          message: 'Batch berhasil dihapus.',
          actionLabel: 'Batalkan',
          onAction: () => restoreDeleted('batch', confirmDelete.item.id),
        })
      } else {
        await api.delete(`/categories/${confirmDelete.item.id}`)
        showToast({ message: 'Kategori berhasil dihapus.' })
        await fetchMasterData()
      }

      setConfirmDelete(null)
      await fetchMedicines(pagination.current_page)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal menghapus data.'))
    } finally {
      setDeleteLoading(false)
    }
  }

  const restoreDeleted = async (type, id) => {
    try {
      if (type === 'medicine') {
        await api.post(`/medicines/${id}/restore`)
      } else {
        await api.post(`/medicine-batches/${id}/restore`)
      }

      setToast(null)
      showToast({ message: 'Data berhasil dikembalikan.' })
      await fetchMedicines(pagination.current_page)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Gagal mengembalikan data.'))
    }
  }

  return (
    <div className="flex min-h-screen bg-surface">
      <Sidebar active="inventory" />
      <main className="ml-sidebar-width flex flex-1 flex-col">
        <AdminHeader
          title="Manajemen Obat"
          subtitle="Kelola data obat, stok, batch, dan informasi kadaluarsa."
        />
        <div className="p-8">
          {error && (
            <div className="mb-5 rounded-lg border border-error-container bg-error-container px-4 py-3 text-sm font-semibold text-on-error-container">
              {error}
            </div>
          )}

          <section className="mb-6 rounded-xl border border-outline-variant bg-white p-5 shadow-sm">
            <div className="grid gap-4 lg:grid-cols-[1.5fr_1fr_1fr_1fr_auto]">
              <label className="text-sm font-semibold text-on-surface">
                Cari Obat
                <input className="mt-1 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" name="search" value={filters.search} onChange={handleFilterChange} placeholder="Nama obat..." />
              </label>
              <label className="text-sm font-semibold text-on-surface">
                Kategori
                <select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary" name="category_id" value={filters.category_id} onChange={handleFilterChange}>
                  <option value="">Semua</option>
                  {medicineCategories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                </select>
              </label>
              <label className="text-sm font-semibold text-on-surface">
                Resep
                <select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary" name="requires_prescription" value={filters.requires_prescription} onChange={handleFilterChange}>
                  <option value="">Semua</option>
                  <option value="1">Butuh resep</option>
                  <option value="0">Tanpa resep</option>
                </select>
              </label>
              <label className="text-sm font-semibold text-on-surface">
                Urut Harga
                <select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary" name="sort_price" value={filters.sort_price} onChange={handleFilterChange}>
                  <option value="">Nama A-Z</option>
                  <option value="asc">Termurah</option>
                  <option value="desc">Termahal</option>
                </select>
              </label>
              {isAdmin && (
                <div className="mt-auto flex flex-wrap gap-2">
                  <Tooltip label="Kelola Kategori">
                    <button className="flex items-center justify-center gap-2 rounded-lg border border-primary bg-white px-5 py-2.5 font-bold text-primary shadow-sm" type="button" onClick={openCategoryModal}>
                      <span className="material-symbols-outlined">category</span> Kategori
                    </button>
                  </Tooltip>
                  <Tooltip label="Tambah Obat">
                    <button className="flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-bold text-white shadow-md" type="button" onClick={openCreateModal}>
                      <span className="material-symbols-outlined">add</span> Tambah Obat
                    </button>
                  </Tooltip>
                </div>
              )}
            </div>
          </section>

          <div className="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
            <table className="w-full text-left">
              <thead className="border-b bg-surface-container-low font-bold">
                <tr><th className="px-6 py-4">Nama Obat</th><th className="px-6 py-4">Kategori</th><th className="px-6 py-4">Harga</th><th className="px-6 py-4 text-center">Stok</th><th className="px-6 py-4 text-center">Resep</th><th className="px-6 py-4 text-right">Aksi</th></tr>
              </thead>
              <tbody className="divide-y">
                {loading ? (
                  <tr><td className="px-6 py-8 text-center text-on-surface-variant" colSpan="6">Memuat data obat...</td></tr>
                ) : medicines.length === 0 ? (
                  <tr><td className="px-6 py-8 text-center text-on-surface-variant" colSpan="6">Tidak ada data obat.</td></tr>
                ) : medicines.map((medicine) => {
                  const lowStock = medicine.total_stock <= medicine.minimum_stock

                  return (
                    <tr key={medicine.id} className="hover:bg-surface-container-low/50">
                      <td className="flex items-center gap-3 px-6 py-4">
                        <MedicineImage medicine={medicine} />
                        <div><span className="font-bold">{medicine.name}</span><p className="text-xs text-on-surface-variant">{medicine.supplier?.name || 'Tanpa supplier'}</p></div>
                      </td>
                      <td className="px-6 py-4">{medicine.category?.name || '-'}</td>
                      <td className="px-6 py-4 font-bold">{formatCurrency(medicine.price)}</td>
                      <td className="px-6 py-4 text-center">
                        <Tooltip label="Kelola Batch">
                          <button type="button" onClick={() => setSelectedMedicine(medicine)}>
                            <span className={`rounded-full px-3 py-1 text-xs font-bold ${lowStock ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}`}>{medicine.total_stock} Unit</span>
                          </button>
                        </Tooltip>
                      </td>
                      <td className="px-6 py-4 text-center"><span className={`rounded-full px-3 py-1 text-xs font-bold ${medicine.requires_prescription ? 'bg-amber-100 text-amber-700' : 'bg-secondary-container text-secondary'}`}>{medicine.requires_prescription ? 'Ya' : 'Tidak'}</span></td>
                      <td className="px-6 py-4 text-right">
                        <Tooltip label="Lihat Detail">
                          <button className="p-2 hover:text-primary" type="button" onClick={() => setSelectedMedicine(medicine)}><span className="material-symbols-outlined">inventory</span></button>
                        </Tooltip>
                        {isAdmin && (
                          <>
                            <Tooltip label="Edit Obat">
                              <button className="p-2 hover:text-primary" type="button" onClick={() => openEditModal(medicine)}><span className="material-symbols-outlined">edit</span></button>
                            </Tooltip>
                            <Tooltip label="Hapus Obat">
                              <button className="p-2 hover:text-error" type="button" onClick={() => requestDeleteMedicine(medicine)}><span className="material-symbols-outlined">delete</span></button>
                            </Tooltip>
                          </>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          <div className="mt-5 flex items-center justify-between text-sm text-on-surface-variant">
            <p>Total data: {pagination.total}</p>
            <div className="flex gap-2">
              <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={pagination.current_page <= 1} onClick={() => fetchMedicines(pagination.current_page - 1)}>Sebelumnya</button>
              <span className="rounded-lg bg-surface-container-low px-4 py-2">{pagination.current_page} / {pagination.last_page}</span>
              <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 disabled:opacity-40" type="button" disabled={pagination.current_page >= pagination.last_page} onClick={() => fetchMedicines(pagination.current_page + 1)}>Berikutnya</button>
            </div>
          </div>
        </div>
      </main>

      {modalOpen && isAdmin && (
        <MedicineFormModal
          activeDraftId={activeDraftId}
          categories={medicineCategories}
          draftSaving={draftSaving}
          editingMedicine={editingMedicine}
          form={form}
          imageFile={imageFile}
          imagePreview={imagePreview}
          onChange={handleFormChange}
          onClose={() => setModalOpen(false)}
          onDraftList={openDraftModal}
          onImageSelect={handleImageSelect}
          onSaveDraft={saveDraft}
          onSubmit={handleSubmit}
          saving={saving}
          suppliers={suppliers}
        />
      )}

      {categoryModalOpen && isAdmin && (
        <CategoryModal
          categories={categories}
          editingCategory={editingCategory}
          form={categoryForm}
          onChange={handleCategoryFormChange}
          onClose={() => {
            setCategoryModalOpen(false)
            resetCategoryForm()
          }}
          onDelete={requestDeleteCategory}
          onEdit={editCategory}
          onReset={resetCategoryForm}
          onSubmit={handleCategorySubmit}
          saving={categorySaving}
        />
      )}

      {selectedMedicineLive && (
        <BatchModal
          batchForm={batchForm}
          editingBatch={editingBatch}
          isAdmin={isAdmin}
          medicine={selectedMedicineLive}
          onBatchChange={handleBatchChange}
          onClose={() => {
            setSelectedMedicine(null)
            resetBatchForm()
          }}
          onDeleteBatch={requestDeleteBatch}
          onEditBatch={editBatch}
          onResetBatch={resetBatchForm}
          onSubmitBatch={handleBatchSubmit}
          savingBatch={savingBatch}
        />
      )}

      {draftModalOpen && (
        <MedicineDraftModal
          drafts={drafts}
          loading={draftsLoading}
          onClose={() => setDraftModalOpen(false)}
          onDelete={deleteDraft}
          onLoad={loadDraft}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          description={confirmDelete.description}
          loading={deleteLoading}
          onCancel={() => setConfirmDelete(null)}
          onConfirm={confirmDeleteItem}
        />
      )}

      {toast && (
        <Toast
          actionLabel={toast.actionLabel}
          message={toast.message}
          onAction={toast.onAction}
          onClose={() => setToast(null)}
          type={toast.type}
        />
      )}
    </div>
  )
}

function CategoryModal({
  categories,
  editingCategory,
  form,
  onChange,
  onClose,
  onDelete,
  onEdit,
  onReset,
  onSubmit,
  saving,
}) {
  return (
    <div className="fixed inset-0 z-[75] flex items-center justify-center bg-black/40 p-4">
      <section className="w-full max-w-4xl overflow-hidden rounded-xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-outline-variant p-5">
          <div>
            <h2 className="text-xl font-bold">Kelola Kategori</h2>
            <p className="text-sm text-on-surface-variant">Kategori yang disimpan akan langsung tersedia di katalog pelanggan.</p>
          </div>
          <button className="rounded-full p-2 hover:bg-surface-container-low" type="button" onClick={onClose}>
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>
        <div className="grid gap-5 p-5 lg:grid-cols-[1fr_1.1fr]">
          <form className="rounded-lg border border-outline-variant bg-surface-container-low p-4" onSubmit={onSubmit}>
            <h3 className="mb-4 font-bold">{editingCategory ? 'Edit Kategori' : 'Tambah Kategori'}</h3>
            <div className="space-y-4">
              <Field label="Nama Kategori" name="name" value={form.name} onChange={onChange} required />
              <TextArea label="Deskripsi" name="description" value={form.description} onChange={onChange} />
            </div>
            <div className="mt-5 flex flex-wrap justify-end gap-2">
              {editingCategory && (
                <button className="rounded-lg border border-outline-variant bg-white px-4 py-2 text-sm font-bold" type="button" onClick={onReset}>
                  Batal Edit
                </button>
              )}
              <button className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white disabled:opacity-50" type="submit" disabled={saving}>
                {saving ? 'Menyimpan...' : editingCategory ? 'Update Kategori' : 'Tambah Kategori'}
              </button>
            </div>
          </form>
          <div className="overflow-hidden rounded-lg border border-outline-variant">
            <div className="border-b border-outline-variant bg-surface-container-low px-4 py-3 font-bold">
              Daftar Kategori
            </div>
            <div className="max-h-[420px] divide-y overflow-y-auto">
              {categories.map((category) => (
                <div key={category.id} className="flex items-start justify-between gap-4 px-4 py-3">
                  <div>
                    <p className="font-bold">{category.name}</p>
                    <p className="mt-1 text-xs text-on-surface-variant">{category.description || 'Tidak ada deskripsi.'}</p>
                  </div>
                  <div className="flex shrink-0 items-center gap-1">
                    <Tooltip label="Edit Kategori">
                      <button className="p-2 hover:text-primary" type="button" onClick={() => onEdit(category)}>
                        <span className="material-symbols-outlined">edit</span>
                      </button>
                    </Tooltip>
                    {category.name !== 'Semua Kategori' && (
                      <Tooltip label="Hapus Kategori">
                        <button className="p-2 hover:text-error" type="button" onClick={() => onDelete(category)}>
                          <span className="material-symbols-outlined">delete</span>
                        </button>
                      </Tooltip>
                    )}
                  </div>
                </div>
              ))}
              {categories.length === 0 && (
                <div className="p-6 text-center text-sm text-on-surface-variant">
                  Belum ada kategori.
                </div>
              )}
            </div>
          </div>
        </div>
      </section>
    </div>
  )
}

function MedicineImage({ medicine }) {
  const [broken, setBroken] = useState(false)

  if (!medicine.image_url || broken) {
    return (
      <div className="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-surface-container-high text-primary">
        <span className="material-symbols-outlined">medication</span>
      </div>
    )
  }

  return (
    <div className="h-12 w-12 overflow-hidden rounded-lg bg-surface-container-high">
      <img alt={medicine.name} src={medicine.image_url} className="h-full w-full object-cover" onError={() => setBroken(true)} />
    </div>
  )
}

function MedicineFormModal({
  activeDraftId,
  categories,
  draftSaving,
  editingMedicine,
  form,
  imageFile,
  imagePreview,
  onChange,
  onClose,
  onDraftList,
  onImageSelect,
  onSaveDraft,
  onSubmit,
  saving,
  suppliers,
}) {
  return (
    <div className="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4">
      <section className="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-outline-variant p-5">
          <div><h2 className="text-xl font-bold">{editingMedicine ? 'Edit Obat' : 'Tambah Obat'}</h2><p className="text-sm text-on-surface-variant">Lengkapi data master obat.</p></div>
          <button className="rounded-full p-2 hover:bg-surface-container-low" type="button" onClick={onClose}><span className="material-symbols-outlined">close</span></button>
        </div>
        <form className="grid gap-4 p-5" onSubmit={onSubmit}>
          <div className="grid gap-4 md:grid-cols-2">
            <Field label="Nama Obat" name="name" value={form.name} onChange={onChange} required />
            <Field label="Harga" name="price" type="number" value={form.price} onChange={onChange} required />
            <SelectField label="Kategori" name="category_id" value={form.category_id} onChange={onChange} required options={categories} />
            <SelectField label="Supplier" name="supplier_id" value={form.supplier_id} onChange={onChange} options={suppliers} placeholder="Tanpa supplier" />
            <Field label="Minimum Stock" name="minimum_stock" type="number" value={form.minimum_stock} onChange={onChange} />
            <Field label="Dosis" name="dosage" value={form.dosage} onChange={onChange} />
          </div>
          <TextArea label="Deskripsi" name="description" value={form.description} onChange={onChange} />
          <TextArea label="Komposisi" name="composition" value={form.composition} onChange={onChange} />
          <TextArea label="Efek Samping" name="side_effects" value={form.side_effects} onChange={onChange} />
          <FileDropzone
            accept="image/*"
            description="Tarik foto obat ke area ini atau klik untuk memilih gambar."
            icon="add_photo_alternate"
            label="Gambar Obat"
            name="image"
            previewUrl={imagePreview}
            selectedFile={imageFile}
            onFileSelect={onImageSelect}
          />
          <div className="flex flex-wrap gap-4">
            <label className="flex items-center gap-2 text-sm font-semibold"><input name="requires_prescription" type="checkbox" checked={form.requires_prescription} onChange={onChange} />Butuh resep</label>
            <label className="flex items-center gap-2 text-sm font-semibold"><input name="is_active" type="checkbox" checked={form.is_active} onChange={onChange} />Aktif</label>
          </div>
          <p className="rounded-lg bg-surface-container-low px-4 py-2 text-xs text-on-surface-variant">Draft otomatis dihapus setelah 7 hari jika tidak disimpan.</p>
          <div className="flex flex-wrap justify-between gap-3 border-t border-outline-variant pt-4">
            <div className="flex gap-3">
              {!editingMedicine && (
                <>
                  <Tooltip label="Simpan Draft">
                    <button className="rounded-lg border border-primary px-5 py-2.5 font-bold text-primary disabled:opacity-50" type="button" onClick={onSaveDraft} disabled={draftSaving}>{draftSaving ? 'Menyimpan...' : activeDraftId ? 'Update Draft' : 'Simpan sebagai Draft'}</button>
                  </Tooltip>
                  <Tooltip label="Lanjutkan Draft">
                    <button className="rounded-lg border border-outline-variant px-5 py-2.5 font-bold" type="button" onClick={onDraftList}>Lihat Draft</button>
                  </Tooltip>
                </>
              )}
            </div>
            <div className="flex gap-3">
              <button className="rounded-lg border border-outline-variant px-5 py-2.5 font-bold" type="button" onClick={onClose}>Batal</button>
              <button className="rounded-lg bg-primary px-5 py-2.5 font-bold text-white disabled:opacity-50" type="submit" disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan'}</button>
            </div>
          </div>
        </form>
      </section>
    </div>
  )
}

function BatchModal({
  batchForm,
  editingBatch,
  isAdmin,
  medicine,
  onBatchChange,
  onClose,
  onDeleteBatch,
  onEditBatch,
  onResetBatch,
  onSubmitBatch,
  savingBatch,
}) {
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4">
      <section className="w-full max-w-4xl rounded-xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-outline-variant p-5">
          <div><h2 className="text-xl font-bold">Batch Obat</h2><p className="text-sm text-on-surface-variant">{medicine.name}</p></div>
          <button className="rounded-full p-2 hover:bg-surface-container-low" type="button" onClick={onClose}><span className="material-symbols-outlined">close</span></button>
        </div>
        <div className="p-5">
          <div className="overflow-hidden rounded-lg border border-outline-variant">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-low font-bold"><tr><th className="p-3">Batch</th><th className="p-3">Kadaluarsa</th><th className="p-3">Qty</th><th className="p-3">Harga Beli</th>{isAdmin && <th className="p-3 text-right">Aksi</th>}</tr></thead>
              <tbody className="divide-y">
                {(medicine.batches || []).map((batch) => (
                  <tr key={batch.id}>
                    <td className="p-3">{batch.batch_number}</td><td className="p-3">{batch.expired_date}</td><td className="p-3">{batch.quantity}</td><td className="p-3">{formatCurrency(batch.purchase_price)}</td>
                    {isAdmin && (
                      <td className="p-3 text-right">
                        <Tooltip label="Edit Batch"><button className="p-2 hover:text-primary" type="button" onClick={() => onEditBatch(batch)}><span className="material-symbols-outlined">edit</span></button></Tooltip>
                        <Tooltip label="Hapus Batch"><button className="p-2 hover:text-error" type="button" onClick={() => onDeleteBatch(batch)}><span className="material-symbols-outlined">delete</span></button></Tooltip>
                      </td>
                    )}
                  </tr>
                ))}
                {(medicine.batches || []).length === 0 && <tr><td className="p-4 text-center text-on-surface-variant" colSpan={isAdmin ? 5 : 4}>Belum ada batch.</td></tr>}
              </tbody>
            </table>
          </div>
          {isAdmin && (
            <form className="mt-5 grid gap-3 rounded-lg bg-surface-container-low p-4 md:grid-cols-5" onSubmit={onSubmitBatch}>
              <Field label="Batch" name="batch_number" value={batchForm.batch_number} onChange={onBatchChange} required compact />
              <Field label="Kadaluarsa" min={new Date().toISOString().slice(0, 10)} name="expired_date" type="date" value={batchForm.expired_date} onChange={onBatchChange} required compact />
              <Field label="Qty" name="quantity" type="number" value={batchForm.quantity} onChange={onBatchChange} required compact />
              <Field label="Harga Beli" name="purchase_price" type="number" value={batchForm.purchase_price} onChange={onBatchChange} compact />
              <div className="mt-auto flex gap-2">
                <button className="rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" type="submit" disabled={savingBatch}>{savingBatch ? '...' : editingBatch ? 'Update' : 'Tambah'}</button>
                {editingBatch && <button className="rounded-lg border border-outline-variant px-4 py-2.5 text-sm font-bold" type="button" onClick={onResetBatch}>Batal</button>}
              </div>
            </form>
          )}
        </div>
      </section>
    </div>
  )
}

function Field({ compact = false, label, min, name, onChange, required = false, type = 'text', value }) {
  return <label className="text-sm font-semibold">{label}<input className={`mt-1 w-full rounded-lg border border-outline-variant px-4 font-normal outline-none focus:border-primary ${compact ? 'py-2' : 'py-2.5'}`} min={min} name={name} type={type} value={value} onChange={onChange} required={required} /></label>
}

function SelectField({ label, name, onChange, options, placeholder = 'Pilih', required = false, value }) {
  return <label className="text-sm font-semibold">{label}<select className="mt-1 w-full rounded-lg border border-outline-variant bg-white px-4 py-2.5 font-normal outline-none focus:border-primary" name={name} value={value} onChange={onChange} required={required}><option value="">{placeholder}</option>{options.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select></label>
}

function TextArea({ label, name, onChange, value }) {
  return <label className="text-sm font-semibold">{label}<textarea className="mt-1 min-h-20 w-full rounded-lg border border-outline-variant px-4 py-2.5 font-normal outline-none focus:border-primary" name={name} value={value} onChange={onChange} /></label>
}
