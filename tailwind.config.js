/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
    "./src/**/*.{html,js}",
  ],
  theme: {
    extend: {
      colors: {
        primary: 'var(--color-primary, #034737)',
        secondary: 'var(--color-secondary, #008000)',
      },
      fontFamily: {
        sans: ['Ping AR LT', 'Tajawal', 'Outfit', 'Helvetica', 'Arial', 'sans-serif'],
        Tajawal: ['Ping AR LT', 'Tajawal', 'Outfit', 'Helvetica', 'Arial', 'sans-serif'],
        Outfit: ['Ping AR LT', 'Tajawal', 'Outfit', 'Helvetica', 'Arial', 'sans-serif'],
      },
      lineHeight: {
        '15': '3rem',  // Custom line height class
        '20': '65px',     // Custom line height class
        // Add more custom line height classes as needed
      },
    },  
  },
  plugins: [],
  darkMode: "class"
}
