module.exports = {
  purge: [
    './templates/**/*.twig'
  ],
  darkMode: false, // or 'media' or 'class'
  theme: {
    extend: {
      fontFamily: {
        round: ['Nunito', 'sans-serif'],
      },
      maxWidth: {
        '960': '60rem',
      },
      colors: {
        roze: {
          DEFAULT: '#e6007e'
        },
        groen: {
          DEFAULT: '#00de01'
        },
        blauw: {
          DEFAULT: '#009fe3'
        }
      }
    },
  },
  variants: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/typography'),
  ],
}
