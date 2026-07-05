import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    // ── Vitest (Fase 11) ────────────────────────────────────────────────────────
    // Hereda resolve.alias de abajo → @ive/ resuelve correctamente en tests.
    // Environment 'node': los tests de pipeline no necesitan DOM.
    // Para tests de componentes React añadir environment: 'jsdom' (npm i -D jsdom).
    test: {
        globals:     true,        // describe/it/expect sin imports explícitos
        environment: 'node',
        include:     ['resources/js/ive/__tests__/**/*.test.js'],
    },
    plugins: [
        // React plugin: habilita JSX transform + Fast Refresh en dev
        react(),

        laravel({
            input: [
                // ── App principal Laravel/Blade ─────────────────────────
                'resources/css/app.css',
                'resources/js/app.js',

                // ── Infrastructure Visualization Engine (IVE) ──────────
                'resources/js/ive/main.jsx',
            ],
            refresh: true,
        }),
    ],

    resolve: {
        alias: {
            // @ive/ → resources/js/ive/
            // Permite: import { useIveStore } from '@ive/core/store/useIveStore'
            '@ive': path.resolve(__dirname, 'resources/js/ive'),
        },
    },

    optimizeDeps: {
        // Three.js y sus dependencias son CJS; Vite los pre-bundlea para ESM
        include: [
            'three',
            '@react-three/fiber',
            '@react-three/drei',
            'zustand',
            'troika-three-text',
        ],
    },
});
