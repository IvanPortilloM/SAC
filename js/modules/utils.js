// js/modules/utils.js

export function formatNumber(num) {
    const number = parseFloat(num);
    if (isNaN(number)) return '0.00';
    return number.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}