import fluid, { extract } from 'fluid-tailwind'

module.exports = {
  mode: 'jit',
  content: {
    files: ["./_views/**/*.twig", './safelist.txt'],
    extract
  },
  theme: {
    screens: {
      "3xs": "20rem", /* 320px */
      "2xs": "23.4375rem", /* 375px */
      xs: "30rem", /* 480px */
      sm: "37.5rem", /* 600px */
      md: "48rem", /* 768px */
      lg: "64rem", /* 1024px */
      xl: "80rem", /* 1280px */
      "2xl": "87.5rem", /* 1400px */
      "3xl": "100rem", /* 1600px */
      "4xl": "110rem", /* 1760px */
      "5xl": "118.75rem", /* 1900px */
      "6xl": "125rem", /* 2000px */
    },
    fontFamily: {
      sans: ["Figtree", "sans-serif"],
    },
    extend: {
      colors: {
        primary: {
          light: "#ABE7E8",
          DEFAULT: "#3A5CC3",
          dark: "#122649",
        },
        secondary: {
          light: "#fe8165",
          DEFAULT: "#FF6B4A",
          dark: "#CA553B",
        },
        success: '#22c55e',
        warning: '#f97316',
        info: '#3b82f6',
        error: '#ef4444',
      },
      spacing: {
        72: "18rem",
        84: "21rem",
        96: "24rem",
        128: "32rem",
      },
      zIndex: {
        "-10": "-10",
        "-20": "-20",
      },
      inset: (theme, { negative }) => ({
        full: "100%",
        "1/2": "50%",
        ...theme("spacing"),
        ...negative(theme("spacing")),
      }),
      maxWidth: (theme) => ({
        ...theme("spacing"),
      }),
      minWidth: (theme) => ({
        ...theme("spacing"),
      }),
      minHeight: (theme) => ({
        ...theme("spacing"),
        25: "25vh",
        50: "50vh",
        75: "75vh",
      }),
      maxHeight: (theme) => ({
        ...theme("spacing"),
      }),
      screens: {
        'landscape': {'raw': '(orientation: landscape)'},
      },
      scale: {
        '103': '1.03',
      },
    },
  },
  variants: {
    backgroundColor: ['responsive', 'group-hover', 'hover', 'focus', 'group-focus'],
    textColor: ['responsive', 'group-hover', 'hover', 'focus', 'group-focus'],
    padding: ['responsive', 'group-hover', 'hover', 'focus', 'group-focus'],
  },
  plugins: [
    fluid({
      checkSC144: false // default: true
    })
   // require('@tailwindcss/forms'),
  ],
  corePlugins: {
    container: false,
  },
};
