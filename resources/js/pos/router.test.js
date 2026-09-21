import { describe, it, expect } from 'vitest';
import { resolveGuard } from './router.js';

describe('router guard', () => {
    it('sends an unauthenticated visitor to login', () => {
        expect(resolveGuard({ toName: 'register', hasToken: false, hasTerminal: false, canOperateRegister: true }))
            .toEqual({ name: 'login' });
    });

    it('sends a token-only visitor to setup', () => {
        expect(resolveGuard({ toName: 'register', hasToken: true, hasTerminal: false, canOperateRegister: true }))
            .toEqual({ name: 'setup' });
    });

    it('lands a register-capable login on the register after setup', () => {
        expect(resolveGuard({ toName: 'login', hasToken: true, hasTerminal: true, canOperateRegister: true }))
            .toEqual({ name: 'register' });
    });

    it('lands a kitchen-only login on the kitchen display, not the register', () => {
        expect(resolveGuard({ toName: 'login', hasToken: true, hasTerminal: true, canOperateRegister: false }))
            .toEqual({ name: 'kitchen' });
    });

    it('sends a kitchen-only user who is already set up straight to setup if no terminal yet', () => {
        expect(resolveGuard({ toName: 'login', hasToken: true, hasTerminal: false, canOperateRegister: false }))
            .toEqual({ name: 'setup' });
    });

    it.each(['register', 'pay', 'confirm'])('blocks a kitchen-only user from %s, redirecting to kitchen', (toName) => {
        expect(resolveGuard({ toName, hasToken: true, hasTerminal: true, canOperateRegister: false }))
            .toEqual({ name: 'kitchen' });
    });

    it('lets a register-capable user reach register/pay/confirm normally', () => {
        expect(resolveGuard({ toName: 'pay', hasToken: true, hasTerminal: true, canOperateRegister: true }))
            .toBe(true);
    });

    it('lets anyone reach the kitchen display once set up, regardless of register access', () => {
        expect(resolveGuard({ toName: 'kitchen', hasToken: true, hasTerminal: true, canOperateRegister: true }))
            .toBe(true);
        expect(resolveGuard({ toName: 'kitchen', hasToken: true, hasTerminal: true, canOperateRegister: false }))
            .toBe(true);
    });

    it.each(['pay', 'confirm'])('blocks a waiter (register access but no checkout) from %s, redirecting to register', (toName) => {
        expect(resolveGuard({ toName, hasToken: true, hasTerminal: true, canOperateRegister: true, canCheckout: false }))
            .toEqual({ name: 'register' });
    });

    it('lets a waiter reach the register itself', () => {
        expect(resolveGuard({ toName: 'register', hasToken: true, hasTerminal: true, canOperateRegister: true, canCheckout: false }))
            .toBe(true);
    });

    it('lets a checkout-capable user reach pay/confirm normally', () => {
        expect(resolveGuard({ toName: 'pay', hasToken: true, hasTerminal: true, canOperateRegister: true, canCheckout: true }))
            .toBe(true);
    });

    it('defaults canCheckout to true for callers that omit it, so older sessions are not locked out', () => {
        expect(resolveGuard({ toName: 'pay', hasToken: true, hasTerminal: true, canOperateRegister: true }))
            .toBe(true);
    });
});
