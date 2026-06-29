// js/sw-register.js
// Registra el Service Worker y, cuando detecta una versión nueva del sitio,
// muestra un aviso "Actualizar". Al tocarlo, recarga con el código nuevo.
// Así los cambios que subes llegan a los dispositivos SIN borrar caché.

(function () {
  if (!('serviceWorker' in navigator)) return;

  let updateConfirmed = false;

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('service-worker.js').then((reg) => {

      // Revisar si hay versión nueva al cargar y cada vez que se vuelve a la pestaña.
      reg.update();
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') reg.update();
      });

      const promptUpdate = (worker) => showUpdateToast(() => {
        updateConfirmed = true;
        worker.postMessage({ type: 'SKIP_WAITING' });
      });

      // Ya había una versión esperando de una visita anterior.
      if (reg.waiting && navigator.serviceWorker.controller) {
        promptUpdate(reg.waiting);
      }

      // Se detecta una versión nueva en vivo.
      reg.addEventListener('updatefound', () => {
        const nw = reg.installing;
        if (!nw) return;
        nw.addEventListener('statechange', () => {
          // 'installed' + ya existe un controlador  =>  es una ACTUALIZACIÓN (no la 1ª vez).
          if (nw.state === 'installed' && navigator.serviceWorker.controller) {
            promptUpdate(nw);
          }
        });
      });
    }).catch((err) => console.warn('Service Worker no registrado:', err));

    // Cuando el SW nuevo toma el control recargamos UNA sola vez,
    // y solo si el usuario lo confirmó (evita recargas en la primera visita).
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (!updateConfirmed) return;
      updateConfirmed = false;
      window.location.reload();
    });
  });

  function showUpdateToast(onConfirm) {
    if (document.getElementById('sw-update-toast')) return;

    const bar = document.createElement('div');
    bar.id = 'sw-update-toast';
    bar.style.cssText =
      'position:fixed;left:1rem;right:1rem;bottom:1rem;z-index:99999;background:#1f2937;' +
      'color:#fff;padding:14px 16px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.25);' +
      'display:flex;align-items:center;justify-content:space-between;gap:12px;' +
      'font-family:Inter,system-ui,sans-serif;max-width:480px;margin:0 auto;';

    const txt = document.createElement('span');
    txt.style.cssText = 'font-size:14px';
    txt.textContent = '🔄 Hay una versión nueva disponible.';

    const btn = document.createElement('button');
    btn.textContent = 'Actualizar';
    btn.style.cssText =
      'background:#22c55e;color:#fff;border:none;padding:8px 16px;border-radius:8px;' +
      'font-weight:600;cursor:pointer;white-space:nowrap;';
    btn.addEventListener('click', () => {
      btn.disabled = true;
      btn.textContent = 'Actualizando…';
      onConfirm();
    });

    bar.appendChild(txt);
    bar.appendChild(btn);
    document.body.appendChild(bar);
  }
})();
