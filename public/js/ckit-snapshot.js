/*!
 * CookieKit — Multi-page client-side snapshot.
 * Loaded ONLY for a logged-in administrator when the URL contains ?ckit-snapshot=1.
 * In snapshot mode the blocker is disabled and Consent Mode v2 defaults are "granted".
 *
 * Flow:
 *  1) wait 5s for tracking scripts to set their cookies
 *  2) read document.cookie and send the names via REST to /cookiekit/v1/snapshot
 *  3) move to the next page (sessionStorage keeps track of the index)
 *  4) repeat until the last page, then show a summary
 */
(function () {
	'use strict';

	if (!window.CKIT_SNAP) return;
	var CFG = window.CKIT_SNAP;
	var STATE_KEY = 'ckit-snapshot-state';
	var T = CFG.i18n || {};

	// Minimal sprintf: replaces %s and %1$s-style placeholders.
	function fmt(str) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return String(str || '').replace(/%(?:(\d+)\$)?s/g, function (m, n) {
			var v = n ? args[parseInt(n, 10) - 1] : args[i++];
			return v === undefined ? '' : String(v);
		});
	}

	function el(tag, attrs) {
		var n = document.createElement(tag);
		if (attrs) for (var k in attrs) n.setAttribute(k, attrs[k]);
		return n;
	}

	function overlay() {
		var box = el('div');
		box.id = 'ckit-snap-overlay';
		box.style.cssText = 'position:fixed;top:20px;right:20px;width:360px;background:#0a3d62;color:#fff;padding:18px 22px;border-radius:10px;font-family:system-ui,-apple-system,sans-serif;font-size:13px;line-height:1.45;box-shadow:0 8px 24px rgba(0,0,0,.25);z-index:2147483647';
		box.innerHTML = '<div style="font-weight:700;font-size:14px;margin-bottom:8px;display:flex;align-items:center;gap:8px"><span class="ckit-snap-dot" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ffe066;animation:ckit-pulse 1s infinite"></span> CookieKit Snapshot</div><div id="ckit-snap-status"></div><div id="ckit-snap-progress" style="margin-top:10px;font-size:11px;opacity:.85"></div><style>@keyframes ckit-pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>';
		document.body.appendChild(box);
		document.getElementById('ckit-snap-status').textContent = T.starting || 'Starting...';
		return {
			set: function (msg) { document.getElementById('ckit-snap-status').textContent = msg; },
			progress: function (i, n) { document.getElementById('ckit-snap-progress').textContent = fmt(T.progress || 'Page %1$s / %2$s', i, n); },
			done: function (msg) {
				var status = document.getElementById('ckit-snap-status');
				status.textContent = '✓ ' + msg;
				status.appendChild(document.createElement('br'));
				status.appendChild(document.createElement('br'));
				var link = document.createElement('a');
				link.href = CFG.adminUrl;
				link.style.cssText = 'color:#ffe066;text-decoration:underline';
				link.textContent = T.backToPanel || 'Back to the scanner panel';
				status.appendChild(link);
				box.style.background = '#1b6e3a';
				var dot = box.querySelector('.ckit-snap-dot');
				if (dot) dot.style.animation = 'none';
			},
			error: function (msg) {
				document.getElementById('ckit-snap-status').textContent = '⚠ ' + msg;
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
		var idx = state.index + 1; // 1-based for display.
		ui.progress(idx, state.total);
		ui.set(fmt(T.loading || 'Loading page %1$s. Waiting %2$ss for tracking scripts...', idx, CFG.waitSeconds));

		// Wait for tracking scripts to set their cookies.
		var waitMs = (CFG.waitSeconds || 5) * 1000;
		var step = 1000, elapsed = 0;
		var iv = setInterval(function () {
			elapsed += step;
			ui.set(fmt(T.waiting || 'Page %1$s — waiting for cookies (%2$ss / %3$ss)', idx, elapsed / 1000, waitMs / 1000));
			if (elapsed >= waitMs) {
				clearInterval(iv);
				var cookies = readAllCookies();
				ui.set(fmt(T.found || 'Page %1$s — found %2$s cookies. Sending to the server...', idx, cookies.length));
				send(cookies, location.href).then(function (res) {
					if (res && res.ok) {
						state.totalCookies += res.count;
						state.index += 1;
						saveState(state);
						if (state.index >= state.total) {
							clearState();
							ui.done(fmt(T.done || 'Snapshot completed — %1$s cookies saved in total across %2$s pages.', state.totalCookies, state.total));
						} else {
							var next = pages[state.index];
							ui.set('✓ ' + fmt(T.next || 'Page %s done. Moving to the next one in 1s...', idx));
							setTimeout(function () { location.href = next; }, 1000);
						}
					} else {
						ui.error(fmt(T.sendError || 'Send error: %s', (res && res.error) ? res.error : (T.badResponse || 'invalid response')));
						clearState();
					}
				}).catch(function (e) {
					ui.error(fmt(T.netError || 'Network error: %s', e.message));
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
