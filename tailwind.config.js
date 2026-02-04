module.exports = {
  content: ['./templates/**/*.twig'],
  darkMode: 'class',
  theme: {
    extend: {
      aspectRatio: {
        21: '21',
      },
      fontFamily: {
        round: ['Nunito', 'sans-serif'],
      },
      maxWidth: {
        960: '60rem',
      },
      colors: {
        roze: {
          DEFAULT: '#e6007e',
        },
        groen: {
          DEFAULT: '#00de01',
        },
        blauw: {
          DEFAULT: '#009fe3',
        },
        // Social media brand colors
        facebook: '#3B5999',
        whatsapp: '#25D366',
        linkedin: '#0A66C2',
      },
    },
  },
  plugins: [
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/typography'),
  ],
};
