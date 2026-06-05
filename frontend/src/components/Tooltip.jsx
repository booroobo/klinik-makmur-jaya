export default function Tooltip({ children, label }) {
  return (
    <span className="group relative inline-flex">
      {children}
      <span className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-on-surface px-2.5 py-1 text-xs font-semibold text-white opacity-0 shadow-lg transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
        {label}
      </span>
    </span>
  )
}
