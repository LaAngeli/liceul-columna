import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Testing Library nu curăță singură între teste decât cu globals activate — o facem explicit,
// altfel DOM-ul unui test se scurge în următorul și interogările devin ambigue.
afterEach(() => {
    cleanup();
});
