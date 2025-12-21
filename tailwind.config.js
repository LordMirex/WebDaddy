/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./**/*.php",
    "./assets/js/**/*.js",
  ],
  theme: {
    extend: {
      colors: {
        navy: "#1a2332",
        "navy-light": "#2d3e50",
        gold: "#d97706",
        "gold-dark": "#b45309",
      },
      fontFamily: {
        sans: ["Inter", "system-ui", "sans-serif"],
        mono: ["Fira Code", "monospace"],
      },
    },
  },
  plugins: [],
};
