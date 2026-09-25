import { describe, expect, it, vi } from 'vitest';
import { randomUuid } from './uuid.js';

describe('randomUuid', () => {
    it('uses native randomUUID when the browser provides it', () => {
        const nativeUuid = '123e4567-e89b-42d3-a456-426614174000';
        const provider = { randomUUID: vi.fn(() => nativeUuid) };

        expect(randomUuid(provider)).toBe(nativeUuid);
        expect(provider.randomUUID).toHaveBeenCalledOnce();
    });

    it('creates a valid version 4 UUID when randomUUID is unavailable', () => {
        const provider = {
            getRandomValues(bytes) {
                bytes.forEach((_, index) => {
                    bytes[index] = index;
                });
                return bytes;
            },
        };

        expect(randomUuid(provider)).toBe('00010203-0405-4607-8809-0a0b0c0d0e0f');
    });

    it('retains the UUID shape in the last-resort fallback', () => {
        expect(randomUuid(null)).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
        );
    });
});
