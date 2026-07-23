/**
 * Matcherii `@testing-library/jest-dom` (`toBeInTheDocument`, `toBeEmptyDOMElement` etc.) sunt
 * înregistrați la RULARE în `vitest.setup.ts`. Fișierul acela e în afara `include`-ului din
 * tsconfig (care acoperă doar `resources/js/**`), deci augmentarea de tipuri nu ajungea la
 * `tsc --noEmit` → matcherii apăreau ca inexistenți. Referința de aici o aduce înăuntru.
 */
import '@testing-library/jest-dom/vitest';
