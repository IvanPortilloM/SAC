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
            loadAdminNews(); // Cargamos la lista de noticias al iniciar
        }
    } catch (e) {
        console.error(e);
        window.location.href = 'login.html';
    }
})();

document.addEventListener('DOMContentLoaded', () => {
    
    // 2. GESTIÓN DE NOTICIAS (CREAR / EDITAR)
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
                id: id, // Solo será tomado en cuenta en el update
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
                    loadAdminNews(); // Recargar tabla
                } else {
                    alert("❌ Error: " + data.error);
                }
            } catch(err) { console.error(err); alert("Error de conexión"); }
        });

        btnCancelEdit.addEventListener('click', resetNewsForm);
    }

    // 3. NOTIFICACIONES
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

    // 4. FAQS
    const formFaq = document.getElementById('form-faq');
    if(formFaq) {
        formFaq.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                categoria_id: parseInt(document.getElementById('faq-category')?.value) || 0,
                pregunta: document.getElementById('faq-question').value,
                respuesta: document.getElementById('faq-answer').value
            };

            try {
                const res = await fetch('api/admin_create_faq.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if(data.success) {
                    alert("✅ Pregunta guardada exitosamente.");
                    formFaq.reset();
                } else {
                    alert("❌ Error: " + data.error);
                }
            } catch(err) { console.error(err); alert("Error de conexión"); }
        });
    }
});

// -- FUNCIONES AUXILIARES --

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
                // Almacenamos el objeto crudo en un atributo de datos para usarlo en la edición
                const item = document.createElement('div');
                item.className = "flex justify-between items-center p-3 bg-gray-50 border rounded-lg";
                item.innerHTML = `
                    <div>
                        <h3 class="font-bold text-gray-800 ${news.es_importante == 1 ? 'text-blue-600' : ''}">
                            ${news.es_importante == 1 ? '⭐ ' : ''}${news.titulo}
                        </h3>
                        <p class="text-xs text-gray-500">${news.fecha_publicacion.split(' ')[0]}</p>
                    </div>
                    <div class="flex gap-2">
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
    
    // Scroll al formulario
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.deleteNews = async function(id) {
    if(!confirm("⚠️ ¿Estás seguro de que deseas eliminar esta noticia? Esta acción no se puede deshacer.")) return;
    
    try {
        const res = await fetch('api/admin_delete_news.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        
        if(data.success) {
            alert("🗑️ Noticia eliminada.");
            loadAdminNews(); // Recargar lista
        } else {
            alert("❌ Error: " + data.error);
        }
    } catch(err) { console.error(err); alert("Error de conexión"); }
};

function resetNewsForm() {
    document.getElementById('form-news').reset();
    document.getElementById('news-id').value = "";
    document.getElementById('news-form-title').innerText = "Crear Noticia";
    document.getElementById('btn-save-news').innerText = "Publicar Noticia";
    document.getElementById('btn-cancel-edit').classList.add('hidden');
}