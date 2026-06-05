import { useEffect, useId, useMemo, useState } from 'react'

const fileMatchesAccept = (file, accept = '') => {
  if (!accept) {
    return true
  }

  const acceptedTypes = accept
    .split(',')
    .map((item) => item.trim().toLowerCase())
    .filter(Boolean)

  if (acceptedTypes.length === 0) {
    return true
  }

  const fileName = file.name.toLowerCase()
  const fileType = file.type.toLowerCase()

  return acceptedTypes.some((acceptedType) => {
    if (acceptedType.startsWith('.')) {
      return fileName.endsWith(acceptedType)
    }

    if (acceptedType.endsWith('/*')) {
      return fileType.startsWith(acceptedType.replace('/*', '/'))
    }

    return fileType === acceptedType
  })
}

export default function FileDropzone({
  accept,
  description,
  icon = 'upload_file',
  label,
  maxSizeMb,
  name,
  onFileSelect,
  previewUrl,
  selectedFile,
}) {
  const inputId = useId()
  const [dragging, setDragging] = useState(false)
  const [error, setError] = useState('')

  const helperText = description || 'Tarik file ke area ini atau klik untuk memilih file.'
  const selectedName = selectedFile?.name
  const selectedType = selectedFile?.type || ''
  const selectedPreviewUrl = useMemo(() => {
    if (!selectedFile || !selectedType.startsWith('image/')) {
      return ''
    }

    return URL.createObjectURL(selectedFile)
  }, [selectedFile, selectedType])
  const effectivePreview = selectedPreviewUrl || previewUrl

  useEffect(() => {
    if (!selectedPreviewUrl) {
      return undefined
    }

    return () => URL.revokeObjectURL(selectedPreviewUrl)
  }, [selectedPreviewUrl])

  const selectFile = (file) => {
    setError('')

    if (!file) {
      onFileSelect(null)
      return
    }

    if (!fileMatchesAccept(file, accept)) {
      setError('Format file tidak didukung.')
      return
    }

    if (maxSizeMb && file.size > maxSizeMb * 1024 * 1024) {
      setError(`Ukuran file maksimal ${maxSizeMb} MB.`)
      return
    }

    onFileSelect(file)
  }

  const handleDrop = (event) => {
    event.preventDefault()
    setDragging(false)
    selectFile(event.dataTransfer.files?.[0] || null)
  }

  return (
    <div className="space-y-2">
      <p className="text-sm font-semibold text-on-surface">{label}</p>
      <label
        className={`flex min-h-40 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed bg-white px-5 py-6 text-center transition-all focus-within:ring-2 focus-within:ring-primary/20 ${
          dragging
            ? 'border-primary bg-primary-container/10'
            : 'border-outline-variant hover:border-primary hover:bg-surface-container-low'
        }`}
        htmlFor={inputId}
        onDragEnter={(event) => {
          event.preventDefault()
          setDragging(true)
        }}
        onDragLeave={(event) => {
          event.preventDefault()
          setDragging(false)
        }}
        onDragOver={(event) => event.preventDefault()}
        onDrop={handleDrop}
      >
        {effectivePreview ? (
          <div className="mb-3 h-28 w-full max-w-56 overflow-hidden rounded-lg border border-outline-variant bg-surface-container-low">
            <img alt="Preview upload" className="h-full w-full object-cover" src={effectivePreview} />
          </div>
        ) : (
          <span className="material-symbols-outlined mb-3 text-4xl text-primary">{icon}</span>
        )}
        <span className="text-sm font-bold text-on-surface">
          {selectedName || 'Pilih file atau drag and drop'}
        </span>
        <span className="mt-1 max-w-md text-xs text-on-surface-variant">{helperText}</span>
        <input
          accept={accept}
          className="sr-only"
          id={inputId}
          name={name}
          type="file"
          onChange={(event) => selectFile(event.target.files?.[0] || null)}
        />
      </label>
      {error && <p className="rounded-lg bg-error-container px-3 py-2 text-xs font-semibold text-on-error-container">{error}</p>}
    </div>
  )
}
