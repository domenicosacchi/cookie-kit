/*!
 * CookieKit — Snapshot client-side multi-pagina.
 * Si carica SOLO se l'utente è admin loggato e l'URL contiene ?ckit-snapshot=1.
 * In modalità snapshot: blocker disattivato, banner non mostrato, Consent Mode v2 = granted.
 *
 * Flusso:
 *  1) attende 5s che gli script di tracking installino cookie nativi
 *  2) legge document.cookie e lo invia via REST a /cookiekit/v1/snapshot
 *  3) avanza alla pagina successiva (sessionStorage tiene traccia dell'indice)
 *  4) ripete fino all'ultima pagina, poi mostra summary
 */
(function () {
	'use strict';

	if (!window.CKIT_SNAP) return;
	var CFG = window.CKIT_SNAP;
	var STATE_KEY = 'ckit-snapshot-state';

	function el(tag, attrs) {
		var n = document.createElement(tag);
		if (attrs) for (var k in attrs) n.setAttribute(k, attrs[k]);
		return n;
	}

	function overlay() {
		var box = el('div');
		box.id = 'ckit-snap-overlay';
		box.style.cssText = 'position:fixed;top:20px;right:20px;width:360px;background:#0a3d62;color:#fff;padding:18px 22px;border-radius:10px;font-family:system-ui,-apple-system,sans-serif;font-size:13px;line-height:1.45;box-shadow:0 8px 24px rgba(0,0,0,.25);z-index:2147483647';
		box.innerHTML = '<div style="font-weight:700;font-size:14px;margin-bottom:8px;display:flex;align-items:center;gap:8px"><span class="ckit-snap-dot" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ffe066;animation:ckit-pulse 1s infinite"></span> CookieKit Snapshot</div><div id="ckit-snap-status">Avvio...</div><div id="ckit-snap-progress" style="margin-top:10px;font-size:11px;opacity:.85"></div><style>@keyframes ckit-pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>';
		document.body.appendChild(box);
		return {
			set: function (msg) { document.getElementById('ckit-snap-status').textContent = msg; },
			progress: function (i, n) { document.getElementById('ckit-snap-progress').textContent = 'Pagina ' + i + ' / ' + n; },
			done: function (msg) {
				document.getElementById('ckit-snap-status').innerHTML = '✓ ' + msg + '<br><br><a href="' + CFG.adminUrl + '" style="color:#ffe066;text-decoration:underline">Torna al pannello scanner</a>';
				box.style.background = '#1b6e3a';
				var dot = box.querySelector('.ckit-snap-dot');
				if (dot) dot.style.animation = 'none';
			},
			error: function (msg) {
				document.getElementById('ckit-snap-status').innerHTML = '⚠ ' + msg;
				box.style.background = '#a02323';
			}
		};
	}

	function readAllCookies() {
		var out = [];
		(document.cookie || '').split(';').forEach(function (kv) {
			var idx = kv.indexOf('=');
			var name = (idx === -1 ? kv : kv.slice(0, idx)).trim();
			if (name) out.push(name);
		});
		return out;
	}

	function send(cookies, pageUrl) {
		return fetch(CFG.restUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.restNonce },
			body: JSON.stringify({ cookies: cookies, page_url: pageUrl })
		}).then(function (r) { return r.json(); });
	}

	function loadState() {
		try { return JSON.parse(sessionStorage.getItem(STATE_KEY)) || null; } catch (e) { return null; }
	}
	function saveState(s) {
		try { sessionStorage.setItem(STATE_KEY, JSON.stringify(s)); } catch (e) {}
	}
	function clearState() {
		try { sessionStorage.removeItem(STATE_KEY); } catch (e) {}
	}

	function start() {
		var ui = overlay();
		var pages = (CFG.pages && CFG.pages.length) ? CFG.pages : [location.href];
		var state = loadState();
		if (!state) {
			state = { index: 0, total: pages.length, totalCookies: 0 };
			saveState(state);
		}
		var idx = state.index + 1; // 1-based for display
		ui.progress(idx, state.total);
		ui.set('Caricamento pagina ' + idx + '. Attendo ' + CFG.waitSeconds + 's per gli script di tracking...');

		// Attendi che gli script di tracking installino i loro cookie.
		var waitMs = (CFG.waitSeconds || 5) * 1000;
		var step = 1000, elapsed = 0;
		var iv = setInterval(function () {
			elapsed += step;
			ui.set('Pagina ' + idx + ' — attesa cookie (' + (elapsed / 1000) + 's / ' + (waitMs / 1000) + 's)');
			if (elapsed >= waitMs) {
				clearInterval(iv);
				var cookies = readAllCookies();
				ui.set('Pagina ' + idx + ' — trovati ' + cookies.length + ' cookie. Invio al server...');
				send(cookies, location.href).then(function (res) {
					if (res && res.ok) {
						state.totalCookies += res.count;
						state.index += 1;
						saveState(state);
						if (state.index >= state.total) {
							clearState();
							ui.done('Snapshot completato — ' + state.totalCookies + ' cookie totali salvati su ' + state.total + ' pagine.');
						} else {
							var next = pages[state.index];
							ui.set('✓ Pagina ' + idx + ' fatta. Vado alla successiva fra 1s...');
							setTimeout(function () { location.href = next; }, 1000);
						}
					} else {
						ui.error('Errore invio: ' + (res && res.error ? res.error : 'risposta non valida'));
						clearState();
					}
				}).catch(function (e) {
					ui.error('Errore di rete: ' + e.message);
					clearState();
				});
			}
		}, step);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
