/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./resources/**/*.antlers.html",
    "./resources/**/*.blade.php",
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require("@tailwindcss/forms")({
      strategy: 'class'
    }),
  ],
}

