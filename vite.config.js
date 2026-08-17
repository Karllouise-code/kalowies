import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
        VitePWA({
            strategies: 'generateSW',
            injectRegister: null,
            registerType: 'autoUpdate',
            manifest: {
                name: 'KaloWies',
                short_name: 'KaloWies',
                description: 'See your calories clearly.',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                theme_color: '#0d9488',
                background_color: '#f8fafc',
                icons: [
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                ],
            },
            workbox: {
                navigateFallback: '/',
                additionalManifestEntries: [
                    { url: '/', revision: '1' },
                    { url: '/icons/icon-192.png', revision: '1' },
                    { url: '/icons/icon-512.png', revision: '1' },
                ],
                globPatterns: ['**/*.{js,css,svg,png,ico,woff2}'],
            },
        }),
    ],
})
