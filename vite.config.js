import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: ['src/styles.css', 'src/app.js'],
      refresh: true,
    }),
  ],
})