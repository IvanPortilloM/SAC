// js/modules/loans.js
import { formatNumber } from './utils.js';

export function calculateAmortization(amount, capitalizations, n_payments, annual_rate_percent) {
    if (amount <= 0 || annual_rate_percent <= 0 || n_payments <= 0) return null;

    const periodic_rate = (annual_rate_percent / 100) / capitalizations;
    const level_payment = amount * (periodic_rate / (1 - Math.pow(1 + periodic_rate, -n_payments)));
    
    let balance = amount;
    let total_interest = 0;
    const schedule = [];
    
    let current_date = new Date();
    let payment_date;

    // LÓGICA CORREGIDA: Detectar si es pago Quincenal (24) o Mensual (12)
    const esQuincenal = (capitalizations === 24);

    if (esQuincenal) {
        // Inicia el 15 o el último día del mes actual
        let first_payment_day = (current_date.getDate() < 15) ? 15 : new Date(current_date.getFullYear(), current_date.getMonth() + 1, 0).getDate();
        payment_date = new Date(current_date.getFullYear(), current_date.getMonth(), first_payment_day);
    } else {
        // Inicia el último día del mes actual (si ya pasó el 15) o el último día del siguiente mes. 
        // Asumiendo que los pagos mensuales se hacen a fin de mes:
        payment_date = new Date(current_date.getFullYear(), current_date.getMonth() + 1, 0);
    }

    for (let i = 0; i < n_payments; i++) {
        const nextDate = new Date(payment_date);
        
        const interest_payment = balance * periodic_rate;
        let principal_payment = level_payment - interest_payment;
        let current_payment_val = level_payment;

        if (i === n_payments - 1) { // Ajuste final
            principal_payment = balance;
            current_payment_val = principal_payment + interest_payment;
        }

        balance -= principal_payment;
        total_interest += interest_payment;

        schedule.push({
            number: i + 1,
            date: nextDate.toLocaleDateString('es-HN'),
            payment: current_payment_val,
            interest: interest_payment,
            principal: principal_payment,
            balance: Math.abs(balance)
        });

        // Calcular siguiente fecha según el tipo de capitalización
        if (esQuincenal) {
            if (payment_date.getDate() === 15) {
                // Si es 15, salta al final del mes
                payment_date = new Date(payment_date.getFullYear(), payment_date.getMonth() + 1, 0);
            } else {
                // Si es fin de mes, salta al 15 del siguiente mes
                payment_date.setMonth(payment_date.getMonth() + 1, 15);
            }
        } else {
            // Si es mensual, simplemente salta al último día del siguiente mes
            payment_date = new Date(payment_date.getFullYear(), payment_date.getMonth() + 2, 0);
        }
    }

    return {
        level_payment,
        total_interest,
        total_payment: amount + total_interest,
        schedule
    };
}

export function getSuggestedInstallments(amount) {
    if (amount < 1) return 0;
    if (amount <= 10000) return 24;
    if (amount <= 25000) return 48;
    if (amount <= 45000) return 72;
    if (amount <= 70000) return 96;
    if (amount <= 100000) return 120;
    if (amount <= 300000) return 144;
    if (amount <= 500000) return 168;
    return 192;
}