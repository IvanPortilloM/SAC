/** @type {import('tailwindcss').Config} */
module.exports = {
  // Archivos donde Tailwind busca las clases que usas (para incluir solo esas).
  content: [
    './*.html',
    './js/**/*.js',
    './api/**/*.php'
  ],
  // Clases que se generan de forma DINÁMICA en JS (con `${variable}`) y que el
  // escáner no puede ver en texto. Si no están aquí, NO se incluirían en el CSS.
  // Ej: admin.html -> switchTab() arma `text-${color}-600`, `bg-${color}-50`, etc.
  safelist: [
    { pattern: /(text|border|bg)-(blue|green|purple|red|yellow|gray)-(50|100|200|300|500|600|700|800)/ },
    'opacity-0', 'opacity-50', 'opacity-60', 'opacity-100'
  ],
  theme: {
    extend: {}
  },
  plugins: []
};
