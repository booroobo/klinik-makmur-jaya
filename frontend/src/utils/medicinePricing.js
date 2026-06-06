export const activeVariants = (medicine) => (
  medicine?.has_variants ? (medicine.variants || []).filter((variant) => variant.is_active !== false) : []
)

export const variantPriceRange = (medicine) => {
  const prices = activeVariants(medicine)
    .map((variant) => Number(variant.price || 0))
    .filter((price) => price > 0)

  if (prices.length === 0) {
    return null
  }

  return {
    min: Math.min(...prices),
    max: Math.max(...prices),
  }
}

export const formatMedicinePrice = (medicine, formatCurrency) => {
  const range = variantPriceRange(medicine)

  if (!range) {
    return formatCurrency(medicine?.price)
  }

  if (range.min === range.max) {
    return formatCurrency(range.min)
  }

  return `${formatCurrency(range.min)} - ${formatCurrency(range.max)}`
}
