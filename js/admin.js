// js/admin.js

// 1. PROTECCIÓN DE RUTA (EJECUTAR INMEDIATAMENTE)
(async function verifyAdmin() {
    try {
        const res = await fetch('api/get_user_data.php');
        const data = await res.json();
        
        // Si no es exitoso O el rol no es admin, fuera
        if (!data.success || !data.user_data || data.user_data.rol !== 'admin') {
            alert("⛔ Acceso Denegado: No tienes permisos de administrador.");
            window.location.href = 'dashboard.html';
        } else {
            // Si es admin, cargamos la lista de usuarios
            loadUsers();
            // Mostramos el contenido (puedes tener el body hidden por defecto en css y mostrarlo aqui)
            document.body.style.display = 'block'; 
        }
    } catch (e) {
        console.error(e);
        window.location.href = 'login.html';
    }
})();

document.addEventListener('DOMContentLoaded', () => {
    
    // 2. PUBLICAR NOTICIA
    const formNews = document.getElementById('form-news');
    if(formNews) {
        formNews.addEventListener('submit', async (e) => {
            e.preventDefault();
            if(!confirm("¿Publicar esta noticia en el portal?")) return;

            const payload = {
                titulo: document.getElementById('news-title').value,
                contenido: document.getElementById('news-content').value,
                imagen: document.getElementById('news-img').value,
                importante: document.getElementById('news-important').checked ? 1 : 0
            };

            try {
                const res = await fetch('api/admin_create_news.php', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                
                if(data.success) {
                    alert("✅ Noticia publicada correctamente.");
                    formNews.reset();
                } else {
                    alert("❌ Error: " + data.error);
                }
            } catch(err) { console.error(err); alert("Error de conexión"); }
        });
    }

    // 3. ENVIAR NOTIFICACIÓN
    const formNotif = document.getElementById('form-notif');
    if(formNotif) {
        formNotif.addEventListener('submit', async (e) => {
            e.preventDefault();
            const userSelect = document.getElementById('notif-user');
            const userText = userSelect.options[userSelect.selectedIndex].text;
            
            if(!confirm(`¿Enviar notificación a: ${userText}?`)) return;

            const payload = {
                user_id: userSelect.value === 'all' ? null : userSelect.value,
                titulo: document.getElementById('notif-title').value,
                mensaje: document.getElementById('notif-message').value
            };

            try {
                const res = await fetch('api/admin_create_notif.php', {
                    method: 'POST',
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

    // 4. GUARDAR FAQ
    const formFaq = document.getElementById('form-faq');
    if(formFaq) {
        formFaq.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                categoria: document.getElementById('faq-category').value,
                orden: parseInt(document.getElementById('faq-order').value) || 0,
                pregunta: document.getElementById('faq-question').value,
                respuesta: document.getElementById('faq-answer').value
            };

            try {
                const res = await fetch('api/admin_create_faq.php', {
                    method: 'POST',
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

async function loadUsers() {
    try {
        const res = await fetch('api/admin_get_users.php');
        const data = await res.json();
        if(data.success) {
            const select = document.getElementById('notif-user');
            // Limpiamos excepto la primera opción
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