export default {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: '#006d37',
        'on-primary': '#ffffff',
        'primary-container': '#27ae60',
        secondary: '#006a6a',
        'secondary-container': '#90efef',
        surface: '#f8f9ff',
        'on-surface': '#0b1c30',
        'surface-container-low': '#eff4ff',
        'surface-container': '#e8f0ff',
        'surface-container-high': '#dce9ff',
        'surface-container-highest': '#d3e4fe',
        'outline-variant': '#bccabc',
        outline: '#6d7a6e',
        error: '#ba1a1a',
        'error-container': '#ffdad6',
        'on-error-container': '#93000a',
        'on-surface-variant': '#3d4a3f',
        tertiary: '#006492',
        'tertiary-container': '#35a1e0',
      },
      borderRadius: {
        DEFAULT: '0.25rem',
        lg: '0.5rem',
        xl: '0.75rem',
        full: '9999px',
      },
      spacing: {
        gutter: '24px',
        'margin-desktop': '32px',
        'sidebar-width': '260px',
        unit: '4px',
        'container-max': '1280px',
        'margin-mobile': '16px',
      },
      maxWidth: {
        'container-max': '1280px',
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
      },
    },
  },
}
