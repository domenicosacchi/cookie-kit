/*!
 * CookieKit — Blocker (head)
 * Carica in <head> prima di tutti gli script di tracciamento.
 * Espone window.CKIT.consent come oggetto e l'API per l'aggiornamento dei segnali Consent Mode v2.
 */
(function () {
	'use strict';

	var COOKIE = 'ckit_consent';

	function readCookie(name) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[$()*+./?[\\\]^{|}]/g, '\\$&') + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : null;
	}

	function parse(raw) {
		try { return JSON.parse(raw); } catch (e) { return null; }
	}

	var stored = parse(readCookie(COOKIE)) || {};
	var categories = stored.categories || {};
	var consentGiven = !!stored.consent_uuid;

	window.CKIT = window.CKIT || {};
	window.CKIT.consent = {
		given: consentGiven,
		categories: categories,
		uuid: stored.consent_uuid || null,
		policyVersion: stored.policy_version || null,
		timestamp: stored.timestamp || null
	};

	// Aggiorna Consent Mode v2 in caso di consenso esistente all'avvio.
	if (typeof window.gtag === 'function' && consentGiven) {
		var update = {
			ad_storage: categories.marketing ? 'granted' : 'denied',
			ad_user_data: categories.marketing ? 'granted' : 'denied',
			ad_personalization: categories.marketing ? 'granted' : 'denied',
			analytics_storage: categories.analytics ? 'granted' : 'denied',
			personalization_storage: categories.preferences ? 'granted' : 'denied',
			functionality_storage: 'granted',
			security_storage: 'granted'
		};
		window.gtag('consent', 'update', update);
	}

	// API per il banner (chiamata al click di Accetta/Rifiuta/Salva).
	window.CKIT.applyConsent = function (cats) {
		var update = {
			ad_storage: cats.marketing ? 'granted' : 'denied',
			ad_user_data: cats.marketing ? 'granted' : 'denied',
			ad_personalization: cats.marketing ? 'granted' : 'denied',
			analytics_storage: cats.analytics ? 'granted' : 'denied',
			personalization_storage: cats.preferences ? 'granted' : 'denied',
			functionality_storage: 'granted',
			security_storage: 'granted'
		};
		if (typeof window.gtag === 'function') {
			window.gtag('consent', 'update', update);
		}
		// Sblocca <script type="text/plain" data-ckit-category="X"> per le categorie acconsentite.
		var nodes = document.querySelectorAll('script[type="text/plain"][data-ckit-category]');
		nodes.forEach(function (node) {
			var cat = node.getAttribute('data-ckit-category');
			if (!cats[cat]) return;
			var s = document.createElement('script');
			// Copia attributi (eccetto type/data-ckit-*).
			for (var i = 0; i < node.attributes.length; i++) {
				var a = node.attributes[i];
				if (a.name === 'type' || a.name.indexOf('data-ckit-') === 0) continue;
				s.setAttribute(a.name, a.value);
			}
			var src = node.getAttribute('data-ckit-src');
			if (src) {
				s.src = src;
			} else if (node.textContent) {
				s.text = node.textContent;
			}
			node.parentNode.insertBefore(s, node);
			node.remove();
		});
		// Sblocca iframe placeholder.
		var phs = document.querySelectorAll('.ckit-iframe-placeholder');
		phs.forEach(function (ph) {
			var cat = ph.getAttribute('data-ckit-category');
			if (!cats[cat]) return;
			var src = ph.getAttribute('data-ckit-iframe-src');
			if (!src) return;
			var iframe = document.createElement('iframe');
			iframe.src = src;
			iframe.setAttribute('frameborder', '0');
			iframe.setAttribute('allowfullscreen', '');
			iframe.style.width = '100%';
			iframe.style.minHeight = '315px';
			ph.parentNode.replaceChild(iframe, ph);
		});
	};
})();
