import { createRouter, createWebHashHistory } from 'vue-router';
import { getItem } from './stores/storage.js';

const routes = [
    { path: '/', redirect: '/register' },
    { path: '/login', name: 'login', component: () => import('./views/Login.vue') },
    { path: '/setup', name: 'setup', component: () => import('./views/TerminalSetup.vue') },
    { path: '/register', name: 'register', component: () => import('./views/Register.vue') },
    { path: '/kitchen', name: 'kitchen', component: () => import('./views/KitchenDisplay.vue') },
    { path: '/pay', name: 'pay', component: () => import('./views/Payment.vue') },
    { path: '/confirm', name: 'confirm', component: () => import('./views/Confirmation.vue') },
];

const router = createRouter({
    history: createWebHashHistory(),
    routes,
});

// Kitchen-only logins (e.g. a shared kitchen-display device signed in as
// the Kitchen role) have no sales.create ability -- every register action
// would 403 server-side anyway, so the register screen itself should never
// be reachable for them, not just its buttons. Older sessions saved before
// this field existed default to true rather than locking a real cashier out.
function canOperateRegister() {
    const raw = getItem('user');
    if (!raw) return true;
    try {
        const user = JSON.parse(raw);
        return user.can_operate_register ?? true;
    } catch {
        return true;
    }
}

// A waiter can operate the register (build/fire orders) but has no
// sales.checkout ability -- /pay and /confirm must stay unreachable for
// them, same reasoning as can_operate_register above.
function canCheckout() {
    const raw = getItem('user');
    if (!raw) return true;
    try {
        const user = JSON.parse(raw);
        return user.can_checkout ?? true;
    } catch {
        return true;
    }
}

const REGISTER_ONLY_ROUTES = ['register', 'pay', 'confirm'];
const CHECKOUT_ONLY_ROUTES = ['pay', 'confirm'];

// Pure decision logic, factored out of the beforeEach registration below so
// it can be unit tested without spinning up a real router/navigation.
export function resolveGuard({ toName, hasToken, hasTerminal, canOperateRegister: canOperate, canCheckout: canPay = true }) {
    if (toName !== 'login' && !hasToken) {
        return { name: 'login' };
    }
    if (hasToken && toName === 'login') {
        if (!hasTerminal) return { name: 'setup' };
        return { name: canOperate ? 'register' : 'kitchen' };
    }
    if (hasToken && !hasTerminal && toName !== 'setup') {
        return { name: 'setup' };
    }
    if (hasToken && hasTerminal && REGISTER_ONLY_ROUTES.includes(toName) && !canOperate) {
        return { name: 'kitchen' };
    }
    if (hasToken && hasTerminal && CHECKOUT_ONLY_ROUTES.includes(toName) && !canPay) {
        return { name: 'register' };
    }
    return true;
}

router.beforeEach((to) => resolveGuard({
    toName: to.name,
    hasToken: Boolean(getItem('token')),
    hasTerminal: Boolean(getItem('terminal')),
    canOperateRegister: canOperateRegister(),
    canCheckout: canCheckout(),
}));

export default router;
