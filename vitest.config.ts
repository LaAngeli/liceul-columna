import { resolve } from 'node:path';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

/**
 * Teste de COMPORTAMENT pentru componentele React (Vitest + Testing Library, mediu jsdom).
 *
 * Config SEPARAT de `vite.config.ts` intenționat: build-ul aplicației încarcă pluginurile Laravel
 * (fonturi Bunny, Wayfinder, Tailwind) care au nevoie de serverul PHP și n-au ce căuta într-o
 * rulare de teste. Aici rămâne doar React + aliasul `@/`.
 *
 * Rulare: `npm run test:js` (o dată) / `npm run test:js:watch` (în timpul lucrului).
 * Suita PHP (Pest) rămâne separată — vezi `php artisan test`.
 */
export default defineConfig({
    // Fără babel-plugin-react-compiler aici: compilatorul doar memoizează (semantica se păstrează),
    // iar excluderea lui ține rularea rapidă și erorile ușor de citit.
    plugins: [react()],
    resolve: {
        alias: {
            '@': resolve(import.meta.dirname, './resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./vitest.setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
        restoreMocks: true,
    },
});
