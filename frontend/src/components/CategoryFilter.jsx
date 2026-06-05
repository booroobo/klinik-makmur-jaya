export default function CategoryFilter({
  backendCategories,
  selectedCategoryId,
  showAll,
  onCategoryChange,
  onToggleShowAll,
}) {
  const categories = normalizeCategories(backendCategories)
  const visibleCategories = showAll ? categories : categories.slice(0, 5)

  return (
    <div>
      <h3 className="mb-4 text-sm font-bold uppercase tracking-wider">Kategori</h3>
      <div className="h-[300px] overflow-y-auto pr-1">
        <div className="space-y-2 transition-all duration-300">
          {visibleCategories.map((category) => {
            const categoryId = category.isAll ? '' : String(category.id)
            const active = String(selectedCategoryId) === categoryId

            return (
              <label
                key={category.key}
                className={`flex cursor-pointer items-center gap-3 rounded-lg p-2 transition-all hover:bg-surface-container ${active ? 'bg-secondary-container font-bold text-secondary' : ''}`}
              >
                <input
                  className="text-primary focus:ring-primary"
                  name="category_id"
                  type="radio"
                  value={categoryId}
                  checked={active}
                  onChange={() => onCategoryChange(categoryId)}
                />
                <span className={active ? 'text-secondary' : 'text-on-surface-variant'}>{category.name}</span>
              </label>
            )
          })}
          {visibleCategories.length === 0 && (
            <p className="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">
              Kategori belum tersedia.
            </p>
          )}
        </div>
      </div>
      {categories.length > 5 && (
        <button
          className="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-bold text-primary transition-all hover:bg-surface-container-low"
          type="button"
          onClick={onToggleShowAll}
        >
          {showAll ? 'Lebih Sedikit' : 'Lainnya'}
          <span className={`material-symbols-outlined text-[18px] transition-transform ${showAll ? 'rotate-180' : ''}`}>
            expand_more
          </span>
        </button>
      )}
    </div>
  )
}

function normalizeCategories(backendCategories = []) {
  const categories = backendCategories.map((category) => ({
    ...category,
    key: `category-${category.id}`,
    isAll: category.name === 'Semua Kategori',
  }))

  if (categories.some((category) => category.isAll)) {
    return categories
  }

  return [
    {
      id: '',
      key: 'category-all',
      name: 'Semua Kategori',
      isAll: true,
    },
    ...categories,
  ]
}
