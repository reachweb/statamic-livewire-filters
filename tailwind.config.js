/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./resources/**/*.{html,php}",
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}

