export function formatMoney(amount, currency = 'USD') {
    const value = Number(amount ?? 0);
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(value);
    } catch {
        return value.toFixed(2);
    }
}
