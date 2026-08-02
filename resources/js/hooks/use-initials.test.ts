import { describe, expect, it } from 'vitest';
import { getInitials } from './use-initials';

/**
 * OGLINDA testului PHP `tests/Feature/InitialsTest.php` — aceleași cazuri, ca regula inițialelor
 * să nu poată diverge între panou (backend) și cabinet (frontend). Dacă schimbi regula într-o
 * parte, una dintre cele două suite pică.
 */
describe('getInitials — o singură regulă, panou și cabinet', () => {
    it.each([
        ['Ursu Valentin', 'UV'],
        ['[DEMO] Ursu Valentin', 'UV'],
        ['[DEMO] Cojocaru Alexandru', 'CA'],
        ['Bujor-Cobili Carolina Maria', 'BC'],
        ['Bujor-Cobili Carolina', 'BC'],
        ['Madonna', 'M'],
        ['Șerban Ștefan', 'ȘȘ'],
        ['— Popescu Ion', 'PI'],
        ['  Ursu   Valentin  ', 'UV'],
        ['[DEMO]', ''],
        ['', ''],
    ])('„%s" → „%s"', (input, expected) => {
        expect(getInitials(input)).toBe(expected);
    });
});
