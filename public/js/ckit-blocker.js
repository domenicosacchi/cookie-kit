/*!
 * CookieKit — Blocker (head)
 * Loaded in <head>, before tracking scripts.
 * Exposes window.CKIT.consent and window.CKIT.applyConsent(), which sends the Consent Mode v2
 * "update" and re-activates blocked scripts and iframes for the accepted categories.
 */
(function () {
	'use strict';

	var BCFG = window.CKIT_BLOCKER_CFG || {};
	var COOKIE = BCFG.cookieName || 'ckit_consent';

	function readCookie(name) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[$()*+./?[\\\]^{|}]/g, '\\$&') + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : null;
	}

	function parse(raw) {
		try { return JSON.parse(raw); } catch (e) { return null; }
	}

	var stored = parse(readCookie(COOKIE)) || {};
	// Same validity rules as the banner: a consent given for an older policy version
	// or past its re-prompt date is treated as missing.
	if (stored.policy_version && BCFG.policyVersion && stored.policy_version !== BCFG.policyVersion) {
		stored = {};
	} else if (stored.expires_at && (new Date(stored.expires_at)).getTime() < Date.now()) {
		stored = {};
	}
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

	// On page load, send the Consent Mode v2 update if a valid consent already exists.
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

	// API used by the banner (called on Accept / Reject / Save).
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
		// Unblock <script type="text/plain" data-ckit-category="X"> for the accepted categories.
		var nodes = document.querySelectorAll('script[type="text/plain"][data-ckit-category]');
		nodes.forEach(function (node) {
			var cat = node.getAttribute('data-ckit-category');
			if (!cats[cat]) return;
			var s = document.createElement('script');
			// Copy attributes (except type and data-ckit-*).
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
		// Unblock iframe placeholders.
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
