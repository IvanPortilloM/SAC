import * as API from './modules/api.js';
import * as UI from './modules/ui.js';
import * as State from './modules/state.js';
import * as Loans from './modules/loans.js';
import { formatNumber } from './modules/utils.js';
import { generateStatementPDF } from './modules/pdf.js';
import { toggleActionButtons, updateDashboardHeader, updateFinancialCards } from './modules/ui.js';

let inactivityTimer;

function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(() => {
        State.clearPollingInterval();
        window.location.href = 'logout.php?status=inactive';
    }, 600000); 
}

async function loadData(forceRefresh = false) {
    if (State.getIsFetching()) return;
    State.setIsFetching(true);
    toggleActionButtons(true);

    try {
        const data = await API.fetchUserData(forceRefresh);

        if (data.success === false && data.error === 'Usuario no autenticado.') {
            window.location.href = 'login.html?status=session_expired';
            return;
        }

        if (data.user_data) {
            State.setUserData(data.user_data);
            updateDashboardHeader(data.user_data, data);
            updateFinancialCards(data.user_data);

            if (data.user_data.rol === 'admin') {
                const btnAdmin = document.getElementById('btn-admin-sidebar');
                if (btnAdmin) {
                    btnAdmin.classList.remove('hidden');
                    btnAdmin.classList.add('flex'); 
                    
                    const newBtn = btnAdmin.cloneNode(true);
                    btnAdmin.parentNode.replaceChild(newBtn, btnAdmin);
                    
                    newBtn.addEventListener('click', () => {
                        window.location.href = 'admin.html';
                    });
                }
            }

            if (data.status === 'pending_data') {
                if (!State.getPollingInterval()) {
                    State.setPollingInterval(setInterval(() => loadData(false), 4000));
                }
            } else {
                State.clearPollingInterval();
                toggleActionButtons(false);
            }
        } else {
            toggleActionButtons(false);
        }

    } catch (error) {
        console.error("Error cargando datos:", error);
        toggleActionButtons(false);
    } finally {
        State.setIsFetching(false);
    }
}

// --- NOTIFICACIONES ---
async function loadNotifications() {
    try {
        const res = await fetch('api/get_notifications.php');
        const data = await res.json();
        
        if (data.success) {
            const lists = [
                document.getElementById('notif-list-desktop'),
                document.getElementById('notif-list-mobile')
            ];
            const badges = [
                document.getElementById('notif-badge-desktop'), 
                document.getElementById('notif-badge-mobile')
            ];
            
            if (data.unread_count > 0) {
                badges.forEach(b => {
                    if (b) {
                        b.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                        b.classList.remove('hidden');
                    }
                });
            } else {
                badges.forEach(b => { if (b) b.classList.add('hidden'); });
            }

            lists.forEach(list => {
                if (!list) return;
                
                list.innerHTML = '';
                if (data.data.length === 0) {
                    list.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm">No tienes notificaciones nuevas.</div>';
                } else {
                    data.data.forEach(notif => {
                        const item = document.createElement('div');
                        item.className = `p-3 border-b border-gray-100 hover:bg-gray-100 cursor-pointer ${notif.leido ? 'bg-gray-50 opacity-60' : 'bg-white'}`;
                        
                        const flexDiv = document.createElement('div');
                        flexDiv.className = 'flex justify-between items-start';
                        
                        const titleEl = document.createElement('h4');
                        titleEl.className = 'text-sm font-bold text-gray-800';
                        titleEl.textContent = notif.titulo;
                        
                        const dateEl = document.createElement('span');
                        dateEl.className = 'text-xs text-gray-400';
                        dateEl.textContent = new Date(notif.fecha_creacion).toLocaleDateString();
                        
                        flexDiv.appendChild(titleEl);
                        flexDiv.appendChild(dateEl);
                        
                        const msgEl = document.createElement('p');
                        msgEl.className = 'text-xs text-gray-600 mt-1';
                        // Volvemos a innerHTML para soportar el formato viejo (<br>)
                        msgEl.innerHTML = notif.mensaje; 
                        
                        item.appendChild(flexDiv);
                        item.appendChild(msgEl);
                        
                        item.addEventListener('click', () => markAsRead(notif.id, item));
                        list.appendChild(item);
                    });
                }
            });
        }
    } catch (e) { console.error("Error notificaciones", e); }
}

async function markAsRead(id, element) {
    if (element) {
        element.classList.remove('bg-white');
        element.classList.add('bg-gray-50', 'opacity-60');
    }
    await fetch('api/mark_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: id })
    });
    loadNotifications(); 
}

// --- NOTICIAS ---
async function loadNews() {
    try {
        const res = await fetch('api/get_news.php');
        const data = await res.json();
        const container = document.getElementById('news-container');
        const section = document.getElementById('news-section');

        if (data.success && data.data.length > 0 && container) {
            section.classList.remove('hidden');
            container.innerHTML = '';
            
            data.data.forEach(news => {
                const card = document.createElement('div');
                const borderClass = news.es_importante == 1 ? 'border-red-500' : 'border-blue-500';
                card.className = `bg-white p-4 rounded-xl shadow-sm border-l-4 ${borderClass} flex flex-col`;
                
                if (news.imagen_url) {
                    const img = document.createElement('img');
                    img.src = news.imagen_url; 
                    img.alt = 'Imagen noticia';
                    img.className = 'w-full h-48 object-cover rounded-lg mb-3 border border-gray-100';
                    card.appendChild(img);
                }

                const title = document.createElement('h3');
                title.className = 'font-bold text-gray-800 text-lg leading-tight';
                title.textContent = news.titulo; 
                card.appendChild(title);

                const content = document.createElement('p');
                content.className = 'text-gray-600 text-sm mt-2 whitespace-pre-wrap';
                // Volvemos a innerHTML
                content.innerHTML = news.contenido;
                card.appendChild(content);

                const dateDiv = document.createElement('div');
                dateDiv.className = 'mt-auto pt-3 text-xs text-gray-400 text-right font-medium';
                dateDiv.textContent = `📅 ${new Date(news.fecha_publicacion).toLocaleDateString()}`;
                card.appendChild(dateDiv);
                
                container.appendChild(card);
            });
        }
    } catch (e) { console.error("Error cargando noticias", e); }
}

// --- PREGUNTAS FRECUENTES ---
async function loadFAQs() {
    try {
        const res = await fetch('api/get_faqs.php');
        const data = await res.json();
        const list = document.getElementById('faq-list');

        if (data.success && list) {
            list.innerHTML = '';
            data.data.forEach(faq => {
                const details = document.createElement('details');
                details.className = 'group bg-gray-50 rounded-lg p-3 cursor-pointer';
                
                const summary = document.createElement('summary');
                summary.className = 'font-semibold text-gray-700 list-none flex justify-between items-center';
                
                const spanQ = document.createElement('span');
                spanQ.textContent = faq.pregunta;
                
                const spanIcon = document.createElement('span');
                spanIcon.className = 'transition-transform group-open:rotate-180';
                spanIcon.textContent = '▼';
                
                summary.appendChild(spanQ);
                summary.appendChild(spanIcon);
                
                const ansDiv = document.createElement('div');
                ansDiv.className = 'mt-3 text-sm text-gray-600 border-t pt-2 border-gray-200';
                // Volvemos a innerHTML
                ansDiv.innerHTML = faq.respuesta;
                
                details.appendChild(summary);
                details.appendChild(ansDiv);
                
                list.appendChild(details);
            });
        }
    } catch (e) { console.error("Error FAQs", e); }
}

document.addEventListener('DOMContentLoaded', () => {
    loadData(false);
    loadNotifications();
    loadNews();
    
    // Años Dinámicos en Beneficios
    const selectAnio = document.getElementById('beneficios-anio');
    if (selectAnio) {
        const anioActual = new Date().getFullYear();
        selectAnio.innerHTML = `
            <option value="${anioActual}">${anioActual}</option>
            <option value="${anioActual - 1}">${anioActual - 1}</option>
        `;
    }

    // MODO PRIVACIDAD MEJORADO
    document.getElementById('btn-toggle-privacy')?.addEventListener('click', () => {
        // Guardamos el estado en el body para que updateFinancialCards lo respete al recargar
        document.body.classList.toggle('privacy-mode');
        const isPrivacy = document.body.classList.contains('privacy-mode');
        
        const elementos = document.querySelectorAll('.saldo-privado');
        elementos.forEach(el => {
            if (isPrivacy) {
                if (el.textContent !== '***') el.dataset.valorReal = el.textContent;
                el.textContent = '***';
            } else {
                el.textContent = el.dataset.valorReal || '0.00';
            }
        });
    });

    setInterval(loadNotifications, 60000);

    ['mousemove','keydown','click','scroll'].forEach(evt => document.addEventListener(evt, resetInactivityTimer));
    resetInactivityTimer();

    document.querySelectorAll('.sidebar-item').forEach(item => {
        const specialIds = ['btn-profile-sidebar', 'btn-faqs-sidebar', 'btn-faqs-mobile', 'logout-btn-mobile', 'logout-btn-desktop', 'btn-admin-sidebar'];
        if (specialIds.includes(item.id)) return;
        
        item.addEventListener('click', (e) => {
            const view = e.currentTarget.dataset.view;
            if (!view) return;
            
            document.querySelectorAll('[data-view]').forEach(el => el.classList.remove('active'));
            document.querySelectorAll(`[data-view="${view}"]`).forEach(el => el.classList.add('active'));
            
            document.querySelectorAll('.content-view').forEach(v => v.classList.add('hidden'));
            const content = document.getElementById(`${view}-content`);
            if (content) content.classList.remove('hidden');
        });
    });

    const cards = {
        'savings-card': 'PATRIMONIALES',
        'voluntary-savings-card': 'AHORROS  VOLUNTARIOS',
        'credits-card': 'CREDITOS'
    };
    Object.keys(cards).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', () => UI.showDetailModal(cards[id], State.getUserData()));
    });
    
    document.getElementById('refresh-data-btn')?.addEventListener('click', () => {
        State.clearPollingInterval();
        loadData(true);
    });
    
    document.getElementById('download-pdf-btn')?.addEventListener('click', () => {
        const userData = State.getUserData();
        if (!userData) return alert("Espera a que carguen los datos.");
        generateStatementPDF(userData, userData);
    });

    const btnExportCsv = document.getElementById('btn-export-csv');
    if (btnExportCsv) {
        btnExportCsv.addEventListener('click', () => {
            const rows = document.querySelectorAll('#amortization-table-body tr');
            if (rows.length === 0) return alert("Por favor, calcula la amortización primero para generar los datos.");
            
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Cuota,Fecha,Pago,Intereses,Capital,Saldo\n";
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td');
                const rowData = Array.from(cols).map(c => c.innerText.replace(/L/g, '').replace(/,/g, '').trim()).join(',');
                csvContent += rowData + "\n";
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "Amortizacion_ADIGGM.csv");
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
    }

    const logoutAction = () => {
        State.clearPollingInterval();
        window.location.href = 'logout.php';
    };
    document.getElementById('logout-btn-desktop')?.addEventListener('click', logoutAction);
    document.getElementById('logout-btn-mobile')?.addEventListener('click', logoutAction);

    const setupNotifToggle = (btnId, dropId) => {
        const btn = document.getElementById(btnId);
        const drop = document.getElementById(dropId);
        if (btn && drop) {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                drop.classList.toggle('hidden');
            });
            document.addEventListener('click', (e) => {
                if (!btn.contains(e.target)) drop.classList.add('hidden');
            });
        }
    };
    setupNotifToggle('notif-btn-desktop', 'notif-dropdown-desktop');
    setupNotifToggle('notif-btn-mobile', 'notif-dropdown-mobile');
    
    document.querySelectorAll('.mark-all-read-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await fetch('api/mark_read.php', { method: 'POST', body: JSON.stringify({}) });
            loadNotifications();
        });
    });

    const openFaqs = () => {
        document.getElementById('faq-modal').classList.remove('hidden');
        loadFAQs();
    };
    document.getElementById('btn-faqs-sidebar')?.addEventListener('click', openFaqs);
    document.getElementById('btn-faqs-mobile')?.addEventListener('click', openFaqs);

    const loanAmount = document.getElementById('loan-amount');
    if (loanAmount) {
        loanAmount.addEventListener('input', () => {
            const val = parseFloat(loanAmount.value);
            const sugg = Loans.getSuggestedInstallments(val);
            UI.setElementText('suggested-installments', sugg);
            
            const installInput = document.getElementById('installments');
            if (installInput && !installInput.value) installInput.value = sugg;
        });
    }

    const calcBtn = document.getElementById('calculate-loan-btn');
    if (calcBtn) {
        calcBtn.addEventListener('click', () => {
            const amount = parseFloat(document.getElementById('loan-amount').value) || 0;
            const caps = parseInt(document.getElementById('capitalizations').value);
            const payments = parseInt(document.getElementById('installments').value) || 0;
            
            const rateSelect = document.getElementById('interest-rate');
            let rate = parseFloat(rateSelect.value);
            if (rateSelect.value === 'custom') {
                rate = parseFloat(document.getElementById('interest-rate-custom').value);
            }

            const result = Loans.calculateAmortization(amount, caps, payments, rate);
            
            if (result) {
                document.getElementById('loan-results').classList.remove('hidden');
                UI.setElementText('summary-amount', `L${formatNumber(amount)}`);
                UI.setElementText('summary-interest', `L${formatNumber(result.total_interest)}`);
                UI.setElementText('summary-total', `L${formatNumber(result.total_payment)}`);
                UI.setElementText('summary-payment', `L${formatNumber(result.level_payment)}`);

                const tbody = document.getElementById('amortization-table-body');
                tbody.innerHTML = '';
                result.schedule.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-gray-50';
                    tr.innerHTML = `
                        <td class="px-6 py-4 text-sm text-gray-900">${row.number}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">${row.date}</td>
                        <td class="px-6 py-4 text-right text-sm text-gray-500">L${formatNumber(row.payment)}</td>
                        <td class="px-6 py-4 text-right text-sm text-gray-500">L${formatNumber(row.interest)}</td>
                        <td class="px-6 py-4 text-right text-sm text-gray-500">L${formatNumber(row.principal)}</td>
                        <td class="px-6 py-4 text-right text-sm font-bold text-gray-700">L${formatNumber(row.balance)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        });
    }

    const loanType = document.getElementById('loan-type');
    if (loanType) {
        loanType.addEventListener('change', () => UI.renderRefinanceTable(State.getUserData()));
    }
    document.getElementById('refinance-table-body')?.addEventListener('change', (e) => {
        if (e.target.classList.contains('refinance-check')) {
            let total = 0;
            document.querySelectorAll('.refinance-check:checked').forEach(chk => {
                total += parseFloat(chk.dataset.saldo);
            });
            UI.setElementText('refinance-total', `L${formatNumber(total)}`);
        }
    });

    const btnExcedentes = document.getElementById('btn-consulta-excedentes');
    const btnRifa = document.getElementById('btn-consulta-rifa');
    const resultadoDiv = document.getElementById('resultado-beneficios');

    async function consultarBeneficio(tipo) {
        if (!resultadoDiv) return;
        const anioEl = document.getElementById('beneficios-anio');
        const anio = anioEl ? anioEl.value : new Date().getFullYear();
        
        resultadoDiv.innerHTML = '<div class="text-center text-gray-500">Consultando...</div>';
        try {
            const html = await API.fetchBeneficios(tipo, anio);
            resultadoDiv.innerHTML = html;
        } catch (error) {
            console.error(error);
            resultadoDiv.innerHTML = `<div class="text-center text-red-500 p-4">Error: ${error.message}</div>`;
        }
    }
    if (btnExcedentes) btnExcedentes.addEventListener('click', () => consultarBeneficio('excedentes'));
    if (btnRifa) btnRifa.addEventListener('click', () => consultarBeneficio('rifa'));

    const reqBtn = document.getElementById('btn-request-loan');
    if (reqBtn) {
        reqBtn.addEventListener('click', async () => {
            const userData = State.getUserData();
            if (!userData) return alert("⚠️ Espera a que carguen los datos del usuario.");
            
            const amountEl = document.getElementById('loan-amount');
            const amount = parseFloat(amountEl.value) || 0;
            
            let montoRefinanciar = 0;
            const selectedLoans = [];
            document.querySelectorAll('.refinance-check:checked').forEach(chk => {
                montoRefinanciar += parseFloat(chk.dataset.saldo) || 0;
                selectedLoans.push(chk.dataset.id);
            });

            const netoARecibir = amount - montoRefinanciar;

            if (montoRefinanciar === 0 && amount < 2000) return alert("⚠️ El monto mínimo es de L 2,000.00");
            if (montoRefinanciar > 0 && amount <= montoRefinanciar) return alert(`⚠️ El monto (L ${formatNumber(amount)}) es insuficiente para cubrir la deuda (L ${formatNumber(montoRefinanciar)}).`);
            if (montoRefinanciar > 0 && netoARecibir < 2000) return alert(`⚠️ El desembolso neto es muy bajo (L ${formatNumber(netoARecibir)}). Mínimo permitido: L 2,000.00.`);

            const disponibleBase = parseFloat(userData.credito_disponible) || 0;
            const disponibleTotal = disponibleBase + montoRefinanciar;
            if (amount > (disponibleTotal + 1)) return alert(`⚠️ Excedes tu capacidad máxima.\n\nDisponible Base: L ${formatNumber(disponibleBase)}\n+ Liberado: L ${formatNumber(montoRefinanciar)}\n= Capacidad Total: L ${formatNumber(disponibleTotal)}`);

            let mensajeConfirm = `Estás solicitando: L ${formatNumber(amount)}\n`;
            if (montoRefinanciar > 0) {
                mensajeConfirm += `- Se pagarán deudas: L ${formatNumber(montoRefinanciar)}\n`;
                mensajeConfirm += `= RECIBIRÁS: L ${formatNumber(netoARecibir)}\n`;
            }
            mensajeConfirm += `\n¿Deseas enviar la solicitud?`;
            
            if (!confirm(mensajeConfirm)) return;

            const rateSelect = document.getElementById('interest-rate');
            let rate = parseFloat(rateSelect.value);
            if (rateSelect.value === 'custom') rate = parseFloat(document.getElementById('interest-rate-custom').value);

            const quotaTxt = document.getElementById('summary-payment').textContent;
            const quota = parseFloat(quotaTxt.replace(/[^\d.]/g, '')) || 0;

            const payload = {
                monto: amount,
                plazo: parseInt(document.getElementById('installments').value),
                tasa: rate,
                cuota: quota,
                motivo: document.getElementById('loan-reason').value,
                tipo_solicitud: document.getElementById('loan-type').value,
                refinanciar_ids: selectedLoans
            };

            reqBtn.textContent = 'Enviando...';
            reqBtn.disabled = true;
            reqBtn.classList.add('opacity-75', 'cursor-not-allowed');

            try {
                const res = await API.postLoanRequest(payload);
                if (res.success) {
                    alert("✅ Solicitud enviada correctamente.");
                    document.getElementById('loan-results').classList.add('hidden');
                    amountEl.value = '';
                } else {
                    alert("⚠️ " + (res.error || "Error al procesar solicitud."));
                }
            } catch (error) {
                console.error(error);
                alert("❌ Error de conexión.");
            } finally {
                reqBtn.textContent = '📝 Solicitar este Préstamo';
                reqBtn.disabled = false;
                reqBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
        });
    }

    const openProfile = () => {
        const u = State.getUserData();
        if (u) {
            document.getElementById('profile-nombre').value = `${u.nombres} ${u.apellidos}`;
            document.getElementById('profile-identidad').value = u.identity_number;
            document.getElementById('profile-email').value = u.email || '';
            document.getElementById('profile-telefono').value = u.telefono || '';
            document.getElementById('profile-direccion').value = u.direccion || '';
            document.getElementById('profile-modal').classList.remove('hidden');
        }
    };
    document.getElementById('btn-profile-sidebar')?.addEventListener('click', openProfile);
    document.getElementById('btn-profile-mobile')?.addEventListener('click', openProfile);
    document.getElementById('close-profile-modal')?.addEventListener('click', () => document.getElementById('profile-modal').classList.add('hidden'));
    
    const profForm = document.getElementById('profile-form');
    if (profForm) {
        profForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = profForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Guardando...';
            
            const payload = {
                email: document.getElementById('profile-email').value,
                telefono: document.getElementById('profile-telefono').value,
                direccion: document.getElementById('profile-direccion').value
            };
            
            const res = await API.postUpdateProfile(payload);
            if (res.success) {
                alert("Perfil actualizado.");
                const u = State.getUserData();
                if (u) {
                    u.email = payload.email;
                    u.telefono = payload.telefono;
                    u.direccion = payload.direccion;
                    State.setUserData(u);
                }
                document.getElementById('profile-modal').classList.add('hidden');
            } else {
                alert("Error: " + res.error);
            }
            btn.disabled = false;
            btn.textContent = 'Guardar Datos Personales';
        });
    }

    const passForm = document.getElementById('password-form');
    if (passForm) {
        passForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const curr = document.getElementById('current-password').value;
            const newP = document.getElementById('new-password').value;
            const conf = document.getElementById('confirm-password').value;

            if (newP !== conf) {
                alert("❌ Las nuevas contraseñas no coinciden.");
                return;
            }
            
            const btn = passForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Verificando...';

            try {
                const res = await fetch('api/change_password.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ current_password: curr, new_password: newP })
                });
                const data = await res.json();
                
                if (data.success) {
                    alert("✅ Contraseña actualizada correctamente.");
                    passForm.reset();
                    document.getElementById('security-form-container').classList.add('hidden');
                } else {
                    alert("❌ " + data.error);
                }
            } catch (e) {
                alert("Error de conexión");
            } finally {
                btn.disabled = false;
                btn.textContent = 'Actualizar Contraseña';
            }
        });
    }

    const track = document.getElementById('ads-track');
    if (track) {
        let idx = 0;
        setInterval(() => {
            idx = (idx + 1) % track.children.length;
            track.style.transform = `translateX(-${idx * 100}%)`;
        }, 5000);
    }
    
    const intRateSel = document.getElementById('interest-rate');
    if (intRateSel) {
        intRateSel.addEventListener('change', (e) => {
             const customInp = document.getElementById('interest-rate-custom');
             if (e.target.value === 'custom') {
                 customInp.classList.remove('hidden');
                 customInp.focus();
             } else {
                 customInp.classList.add('hidden');
                 customInp.value = e.target.value;
             }
        });
    }
    
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (!localStorage.getItem('pwa_rechazada')) {
            mostrarNotificacionInstalar();
        }
    });

    function mostrarNotificacionInstalar() {
        const div = document.createElement('div');
        div.id = 'pwa-toast';
        div.className = 'fixed bottom-4 left-4 right-4 bg-white p-4 rounded-lg shadow-2xl border border-gray-200 z-50 flex flex-col gap-3 animate-fade-in-up';
        div.innerHTML = `
            <div class="flex items-center gap-3">
                <img src="assets/img/icon-192.png" class="w-12 h-12 rounded-lg" onerror="this.style.display='none'">
                <div>
                    <h4 class="font-bold text-gray-800">Instalar App ADIGGM</h4>
                    <p class="text-sm text-gray-600">Accede más rápido.</p>
                </div>
            </div>
            <div class="flex gap-2 mt-1">
                <button id="btn-pwa-install" class="flex-1 bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">Instalar</button>
                <button id="btn-pwa-cancel" class="flex-1 bg-gray-100 text-gray-700 py-2 rounded font-medium hover:bg-gray-200">Cancelar</button>
            </div>
        `;
        document.body.appendChild(div);

        document.getElementById('btn-pwa-install').addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
            }
            document.getElementById('pwa-toast').remove();
        });

        document.getElementById('btn-pwa-cancel').addEventListener('click', () => {
            localStorage.setItem('pwa_rechazada', 'true');
            document.getElementById('pwa-toast').remove();
        });
    }
});