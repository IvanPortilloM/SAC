// js/modules/ui.js
import { formatNumber } from './utils.js';

export function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/[&<>'"]/g, match => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    })[match]);
}

export function setElementText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

export function updateDashboardHeader(user, data) {
    const welcomeMsg = document.getElementById('welcome-message');
    if (welcomeMsg) {
        let saludo = "Bienvenido";
        if (user.fecha_nacimiento) {
            const [year, month, day] = user.fecha_nacimiento.split('-');
            const birthDate = new Date(year, month - 1, day); 
            const today = new Date();
            if (today.getDate() === birthDate.getDate() && today.getMonth() === birthDate.getMonth()) {
                saludo = `¡Feliz Cumpleaños, ${user.nombres}! 🎉🎂`;
            } else {
                saludo = `Hola, ${user.nombres}`;
            }
        }
        welcomeMsg.textContent = saludo;
    }

    setElementText('dashboard-user-name', `${user.nombres || ''} ${user.apellidos || ''}`.trim());
    setElementText('dashboard-user-name-main', `${user.nombres || ''} ${user.apellidos || ''}`.trim());
    setElementText('current-date-time', user.fecha_procesado || new Date().toLocaleString('es-HN'));

    const statusMsg = document.getElementById('data-status-message');
    if (statusMsg) {
        if (data.status === 'pending_data' || data.status === 'no_financial_records') {
            statusMsg.className = 'mt-4 p-3 rounded-md text-center bg-yellow-100 text-yellow-800';
            statusMsg.textContent = data.message;
            statusMsg.classList.remove('hidden');
        } else {
            statusMsg.classList.add('hidden');
        }
    }
}

export function updateFinancialCards(user) {
    const totalSavings = parseFloat(user.total_ahorros) || 0;
    const totalCredits = parseFloat(user.total_creditos) || 0;
    const availableCredit = parseFloat(user.credito_disponible) || 0;

    // Verificar si el usuario activó el Modo Privacidad
    const isPrivacy = document.body.classList.contains('privacy-mode');

    // Función auxiliar para actualizar saldos respetando la privacidad
    const updateField = (id, val) => {
        const el = document.getElementById(id);
        if (el) {
            el.dataset.valorReal = formatNumber(val);
            el.textContent = isPrivacy ? '***' : formatNumber(val);
        }
    };

    updateField('available-credit', availableCredit);
    updateField('total-savings', totalSavings);
    updateField('total-credits', totalCredits);

    let creditsPercentage = totalSavings > 0 ? (totalCredits / totalSavings) * 100 : 0;
    let availablePercentage = totalSavings > 0 ? (availableCredit / totalSavings) * 100 : 0;

    const credBar = document.getElementById('credits-progress-bar-segment');
    const availBar = document.getElementById('available-progress-bar-segment');
    if(credBar) credBar.style.width = `${creditsPercentage}%`;
    if(availBar) availBar.style.width = `${availablePercentage}%`;
    
    setElementText('credits-percentage', `${formatNumber(creditsPercentage)}%`);
    setElementText('available-percentage', `${formatNumber(availablePercentage)}%`);
}

export function showDetailModal(groupName, userData) {
    const modal = document.getElementById('detail-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalTableContent = document.getElementById('modal-table-content');

    if (!userData || !userData.detalle_grupos) {
        if(modalTitle) modalTitle.textContent = 'Error: No datos.';
        if(modal) modal.classList.remove('hidden');
        return;
    }

    const targetGroup = userData.detalle_grupos.find(g => g.nombre.toUpperCase().trim() === groupName.toUpperCase().trim());

    if (targetGroup) {
        modalTitle.textContent = `Detalle de ${escapeHTML(targetGroup.nombre)}`;
        const isCredit = groupName.toUpperCase().includes('CREDITOS');
        
        let html = '<table class="modal-table"><thead><tr><th>Operación</th><th>Descrip.</th><th class="text-right">Principal</th>';
        if (isCredit) html += '<th class="text-right">Saldo</th><th class="text-right">Cuota</th><th class="text-center">Pagos</th><th class="text-center">N°Cuotas</th>';
        else html += '<th class="text-center">Movimientos</th>';
        html += '</tr></thead><tbody>';

        targetGroup.registros.forEach(record => {
            html += `<tr><td>${escapeHTML(record.Operacion)}</td><td>${escapeHTML(record.Descripci)}</td><td class="text-right">L.${formatNumber(record.Principal)}</td>`;
            if (isCredit) {
                html += `<td class="text-right">L.${formatNumber(record.saldo)}</td><td class="text-right">L.${formatNumber(record.Cuota)}</td><td class="text-center">${escapeHTML(record.Pagos)}</td><td class="text-center">${escapeHTML(record.N_Cuotas)}</td>`;
            } else {
                html += `<td class="text-center">${escapeHTML(record.Pagos)}</td>`;
            }
            html += '</tr>';
        });

        html += '</tbody><tfoot><tr><td colspan="2" class="font-bold">TOTAL</td>';
        html += `<td class="text-right font-bold">L.${formatNumber(targetGroup.subPrincipal)}</td>`;
        if (isCredit) {
            html += `<td class="text-right font-bold">L.${formatNumber(targetGroup.subSaldo)}</td><td class="text-right font-bold">L.${formatNumber(targetGroup.subCuota)}</td><td colspan="2"></td>`;
        } else html += '<td></td>';
        
        html += '</tr></tfoot></table>';
        modalTableContent.innerHTML = html;
    } else {
        modalTitle.textContent = `No se encontraron detalles para ${escapeHTML(groupName)}.`;
        modalTableContent.innerHTML = '<p>No hay registros.</p>';
    }
    modal.classList.remove('hidden');
}

export function renderRefinanceTable(userData) {
    const tableBody = document.getElementById('refinance-table-body');
    const section = document.getElementById('refinance-section');
    const loanType = document.getElementById('loan-type').value;
    
    const tiposRefinanciamiento = ['REFINANCIAMIENTO', 'NETEO DE DEUDAS', 'NETEO DE PRÉSTAMO'];
    
    if (!tiposRefinanciamiento.includes(loanType)) {
        if(section) section.classList.add('hidden');
        return;
    }

    if (!userData || !userData.detalle_grupos) return;
    const creditosGroup = userData.detalle_grupos.find(g => g.nombre.toUpperCase().includes('CREDITOS'));
    
    if(section) section.classList.remove('hidden');
    if(tableBody) {
        tableBody.innerHTML = '';
        if (!creditosGroup || !creditosGroup.registros || creditosGroup.registros.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="3" class="text-center p-2 text-gray-500">No tienes créditos activos.</td></tr>`;
            return;
        }

        creditosGroup.registros.forEach((credito, index) => {
            const saldo = parseFloat(credito.saldo) || 0;
            const idUnico = escapeHTML(credito.Operacion || `tmp_${index}`);
            const desc = escapeHTML(credito.Descripci || 'Crédito');

            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="p-2 text-center">
                    <input type="checkbox" class="refinance-check" 
                        data-saldo="${saldo}" 
                        data-id="${idUnico}">
                </td>
                <td class="p-2">${desc}</td>
                <td class="p-2 text-right">L ${formatNumber(saldo)}</td>
            `;
            tableBody.appendChild(row);
        });
    }
}

export function toggleActionButtons(isDisabled) {
    const refreshBtn = document.getElementById('refresh-data-btn');
    const pdfBtn = document.getElementById('download-pdf-btn');
    const spinner = document.getElementById('refresh-spinner');
    const btnText = document.getElementById('refresh-button-text');

    const classes = ['opacity-50', 'cursor-not-allowed'];

    if (refreshBtn) {
        refreshBtn.disabled = isDisabled;
        if (isDisabled) {
            refreshBtn.classList.add(...classes);
            if(btnText) btnText.textContent = 'Actualizando...';
            if(spinner) spinner.classList.remove('hidden');
        } else {
            refreshBtn.classList.remove(...classes);
            if(btnText) btnText.textContent = 'Actualizar Información';
            if(spinner) spinner.classList.add('hidden');
        }
    }

    if (pdfBtn) {
        pdfBtn.disabled = isDisabled;
        if (isDisabled) {
            pdfBtn.classList.add(...classes);
        } else {
            pdfBtn.classList.remove(...classes);
        }
    }
}