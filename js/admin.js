// js/admin.js

// 1. PROTECCIÓN DE RUTA (EJECUTAR INMEDIATAMENTE)
(async function verifyAdmin() {
    try {
        const res = await fetch('api/get_user_data.php');
        const data = await res.json();
        
        if (!data.success || !data.user_data || data.user_data.rol !== 'admin') {
            alert("⛔ Acceso Denegado: No tienes permisos de administrador.");
            window.location.href = 'dashboard.html';
        } else {
            document.body.style.display = 'block'; 
            loadUsers();
            loadAdminNews(); 
            loadAdminFaqs(); 
        }
    } catch (e) {
        console.error(e);
        window.location.href = 'login.html';
    }
})();

document.addEventListener('DOMContentLoaded', () => {
    
    // -- GESTIÓN DE NOTICIAS --
    const formNews = document.getElementById('form-news');
    const btnCancelEdit = document.getElementById('btn-cancel-edit');
    
    if(formNews) {
        formNews.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('news-id').value;
            const isEditing = id !== "";
            const actionText = isEditing ? "actualizar" : "publicar";
            
            if(!confirm(`¿Seguro que deseas ${actionText} esta noticia?`)) return;

            const payload = {
                id: id,
                titulo: document.getElementById('news-title').value,
                contenido: document.getElementById('news-content').value,
                imagen_url: document.getElementById('news-img').value,
                es_importante: document.getElementById('news-important').checked ? 1 : 0
            };

            const endpoint = isEditing ? 'api/admin_update_news.php' : 'api/admin_create_news.php';

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                
                if(data.success) {
                    alert(`✅ Noticia ${isEditing ? 'actualizada' : 'publicada'} exitosamente.`);
                    resetNewsForm();
                    loadAdminNews(); 
                } else {
                    alert("❌ Error: " + data.error);
                }
            } catch(err) { console.error(err); alert("Error de conexión"); }
        });

        btnCancelEdit.addEventListener('click', resetNewsForm);
    }

    // -- GESTIÓN DE NOTIFICACIONES --
    const formNotif = document.getElementById('form-notif');
    if(formNotif) {
        formNotif.addEventListener('submit', async (e) => {
            e.preventDefault();
            if(!confirm("¿Enviar esta notificación?")) return;

            const payload = {
                user_id: document.getElementById('notif-user').value,
                mensaje: document.getElementById('notif-msg').value,
                tipo: document.getElementById('notif-type').value
            };

            try {
                const res = await fetch('api/admin_create_notif.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if(data.success) {
                    alert("✅ Notificación enviada.");
                    formNotif.reset();
                } else {
                    alert("❌ Error: " + data.error);
                }
            } catch(err) { console.error(err); alert("Error de conexión"); }
        });
    }

    // -- GESTIÓN DE FAQS --
    const formFaq = document.getElementById('form-faq');
    const btnCancelFaqEdit = document.getElementById('btn-cancel-faq-edit');

    if(formFaq) {
        formFaq.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('faq-id').value;
            const isEditing = id !== "";
            const actionText = isEditing ? "actualizar" : "guardar";

            if(!confirm(`¿Seguro que deseas ${actionText} esta pregunta frecuente?`)) return;

            const payload = {
                id: id,
                categoria_id: 0, 
                pregunta: document.getElementById('faq-question').value,
                respuesta: document.getElementById('faq-answer').value
            };

            const endpoint = isEditing ? 'api/admin_update_faq.php' : 'api/admin_create_faq.php';

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                
                if(data.success) {
                    alert(`✅ Pregunta ${isEditing ? 'actualizada' : 'guardada'} exitosamente.`);
                    resetFaqForm();
                    loadAdminFaqs(); 
                } else {
                    alert("❌ Error: " + data.error);
                }
            } catch(err) { console.error(err); alert("Error de conexión"); }
        });

        btnCancelFaqEdit.addEventListener('click', resetFaqForm);
    }
});


// ==========================================
// FUNCIONES AUXILIARES (CARGA Y LISTADOS)
// ==========================================

async function loadUsers() {
    try {
        const res = await fetch('api/admin_get_users.php');
        const data = await res.json();
        if(data.success) {
            const select = document.getElementById('notif-user');
            select.innerHTML = '<option value="all">📢 ENVIAR A TODOS (Global)</option>';
            data.users.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.text = `👤 ${u.nombres} ${u.apellidos} (${u.identity_number})`;
                select.appendChild(opt);
            });
        }
    } catch(err) { console.error("Error cargando usuarios", err); }
}

// -- LÓGICA DE NOTICIAS --
async function loadAdminNews() {
    const listContainer = document.getElementById('admin-news-list');
    try {
        const res = await fetch('api/get_news.php');
        const data = await res.json();
        
        if(data.success) {
            listContainer.innerHTML = '';
            if(data.data.length === 0) {
                listContainer.innerHTML = '<p class="text-gray-500 text-sm">No hay noticias publicadas activas.</p>';
                return;
            }

            data.data.forEach(news => {
                const item = document.createElement('div');
                // CAMBIO VISUAL: gap-4, items-start
                item.className = "flex justify-between items-start p-3 bg-gray-50 border rounded-lg gap-4";
                // CAMBIO VISUAL: flex-1 min-w-0 para el texto, flex-shrink-0 para los botones
                item.innerHTML = `
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-gray-800 truncate ${news.es_importante == 1 ? 'text-blue-600' : ''}">
                            ${news.es_importante == 1 ? '⭐ ' : ''}${news.titulo}
                        </h3>
                        <p class="text-xs text-gray-500 mt-1">${news.fecha_publicacion.split(' ')[0]}</p>
                    </div>
                    <div class="flex-shrink-0 flex gap-2">
                        <button onclick='editNews(${JSON.stringify(news).replace(/'/g, "&#39;")})' class="px-3 py-1 bg-yellow-100 text-yellow-700 text-sm rounded hover:bg-yellow-200">Editar</button>
                        <button onclick="deleteNews(${news.id})" class="px-3 py-1 bg-red-100 text-red-700 text-sm rounded hover:bg-red-200">Borrar</button>
                    </div>
                `;
                listContainer.appendChild(item);
            });
        }
    } catch(err) {
        console.error("Error cargando noticias", err);
        listContainer.innerHTML = '<p class="text-red-500 text-sm">Error cargando lista de noticias.</p>';
    }
}

window.editNews = function(news) {
    document.getElementById('news-id').value = news.id;
    document.getElementById('news-title').value = news.titulo;
    document.getElementById('news-content').value = news.contenido;
    document.getElementById('news-img').value = news.imagen_url || '';
    document.getElementById('news-important').checked = news.es_importante == 1;

    document.getElementById('news-form-title').innerText = "✏️ Editar Noticia";
    document.getElementById('btn-save-news').innerText = "Actualizar Noticia";
    document.getElementById('btn-cancel-edit').classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.deleteNews = async function(id) {
    if(!confirm("⚠️ ¿Estás seguro de que deseas eliminar esta noticia?")) return;
    try {
        const res = await fetch('api/admin_delete_news.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if(data.success) {
            alert("🗑️ Noticia eliminada.");
            loadAdminNews();
        } else alert("❌ Error: " + data.error);
    } catch(err) { console.error(err); alert("Error de conexión"); }
};

function resetNewsForm() {
    document.getElementById('form-news').reset();
    document.getElementById('news-id').value = "";
    document.getElementById('news-form-title').innerText = "Crear Noticia";
    document.getElementById('btn-save-news').innerText = "Publicar Noticia";
    document.getElementById('btn-cancel-edit').classList.add('hidden');
}

// -- LÓGICA DE FAQS --
async function loadAdminFaqs() {
    const listContainer = document.getElementById('admin-faq-list');
    try {
        const res = await fetch('api/get_faqs.php');
        const data = await res.json();
        
        if(data.success) {
            listContainer.innerHTML = '';
            if(data.data.length === 0) {
                listContainer.innerHTML = '<p class="text-gray-500 text-sm">No hay FAQs registradas.</p>';
                return;
            }

            data.data.forEach(faq => {
                const item = document.createElement('div');
                // CAMBIO VISUAL: gap-4, items-start
                item.className = "flex justify-between items-start p-3 bg-gray-50 border rounded-lg gap-4";
                // CAMBIO VISUAL: flex-1 min-w-0 para el texto, line-clamp-3 para limitar la altura visual, flex-shrink-0 para botones
                item.innerHTML = `
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-gray-800 text-sm truncate">${faq.pregunta}</h3>
                        <div class="text-xs text-gray-500 mt-1 line-clamp-3 overflow-hidden break-words">${faq.respuesta}</div>
                    </div>
                    <div class="flex-shrink-0 flex gap-2">
                        <button onclick='editFaq(${JSON.stringify(faq).replace(/'/g, "&#39;")})' class="px-3 py-1 bg-yellow-100 text-yellow-700 text-sm rounded hover:bg-yellow-200">Editar</button>
                        <button onclick="deleteFaq(${faq.id})" class="px-3 py-1 bg-red-100 text-red-700 text-sm rounded hover:bg-red-200">Borrar</button>
                    </div>
                `;
                listContainer.appendChild(item);
            });
        }
    } catch(err) {
        console.error("Error cargando FAQs", err);
        listContainer.innerHTML = '<p class="text-red-500 text-sm">Error cargando lista de FAQs.</p>';
    }
}

window.editFaq = function(faq) {
    document.getElementById('faq-id').value = faq.id;
    document.getElementById('faq-question').value = faq.pregunta;
    document.getElementById('faq-answer').value = faq.respuesta;

    document.getElementById('faq-form-title').innerText = "✏️ Editar Pregunta Frecuente";
    document.getElementById('btn-save-faq').innerText = "Actualizar Pregunta";
    document.getElementById('btn-cancel-faq-edit').classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.deleteFaq = async function(id) {
    if(!confirm("⚠️ ¿Estás seguro de que deseas eliminar esta pregunta frecuente?")) return;
    try {
        const res = await fetch('api/admin_delete_faq.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if(data.success) {
            alert("🗑️ FAQ eliminada.");
            loadAdminFaqs();
        } else alert("❌ Error: " + data.error);
    } catch(err) { console.error(err); alert("Error de conexión"); }
};

function resetFaqForm() {
    document.getElementById('form-faq').reset();
    document.getElementById('faq-id').value = "";
    document.getElementById('faq-form-title').innerText = "Añadir Pregunta Frecuente";
    document.getElementById('btn-save-faq').innerText = "Guardar Pregunta";
    document.getElementById('btn-cancel-faq-edit').classList.add('hidden');
}