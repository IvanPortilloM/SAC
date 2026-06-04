// js/csrf.js
// Protección CSRF en el frontend.
// Parcha window.fetch para añadir automáticamente la cabecera X-CSRF-Token a
// TODAS las peticiones POST/PUT/DELETE del MISMO origen, sin tener que editar
// cada llamada fetch del proyecto. Las GET y las peticiones externas no se tocan.
(function () {
  if (window.__csrfPatched) return;
  window.__csrfPatched = true;

  var origFetch = window.fetch.bind(window);
  var tokenPromise = null;

  function loadToken() {
    if (!tokenPromise) {
      tokenPromise = origFetch('api/csrf_token.php', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) { return (d && d.token) ? d.token : null; })
        .catch(function () { return null; });
    }
    return tokenPromise;
  }

  // Precargar el token cuanto antes.
  loadToken();

  window.fetch = function (input, init) {
    try {
      init = init || {};
      var method = init.method || (input && typeof input !== 'string' && input.method) || 'GET';
      method = String(method).toUpperCase();

      // Solo nos interesan métodos que cambian estado.
      if (method === 'GET' || method === 'HEAD') {
        return origFetch(input, init);
      }

      // No enviar el token a dominios externos (p.ej. api.adiggm.hn).
      var url = (typeof input === 'string') ? input : (input && input.url) || '';
      var isAbsolute = /^https?:\/\//i.test(url);
      var sameOrigin = !isAbsolute || url.indexOf(window.location.origin) === 0;
      if (!sameOrigin) {
        return origFetch(input, init);
      }

      return loadToken().then(function (token) {
        if (token) {
          var headers = new Headers(init.headers || {});
          if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
          init.headers = headers;
        }
        return origFetch(input, init);
      });
    } catch (e) {
      return origFetch(input, init);
    }
  };
})();
