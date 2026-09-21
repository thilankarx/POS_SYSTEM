import { getItem } from '../stores/storage.js';

export async function downloadReceipt(saleId) {
    const token = getItem('token');
    const response = await fetch(`/api/v1/sales/${saleId}/receipt`, {
        headers: { Authorization: `Bearer ${token}` },
    });

    if (!response.ok) {
        throw new Error('Failed to download receipt');
    }

    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `receipt-${saleId}.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}
