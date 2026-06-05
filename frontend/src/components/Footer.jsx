import { Link } from 'react-router-dom'

export default function Footer() {
  return (
    <footer className="mt-auto border-t border-outline-variant bg-surface-container-highest">
      <div className="mx-auto flex w-full max-w-container-max flex-col items-center justify-between px-margin-mobile py-8 md:flex-row md:px-margin-desktop">
        <div className="mb-4 md:mb-0">
          <span className="font-bold text-primary">Klinik Makmur Jaya</span>
          <p className="mt-1 text-sm text-on-surface-variant">
            Klinik Makmur Jaya, Solusi Kesehatan Terpercaya.
          </p>
        </div>
        <div className="flex flex-wrap justify-center gap-6">
          <Link className="text-sm text-on-surface-variant transition-all hover:text-primary hover:underline" to="/about-us">
            Tentang Kami
          </Link>
          <Link className="text-sm text-on-surface-variant transition-all hover:text-primary hover:underline" to="/contact-us">
            Hubungi Kami
          </Link>
        </div>
      </div>
    </footer>
  )
}
