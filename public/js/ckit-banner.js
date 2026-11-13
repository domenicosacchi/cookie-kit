/*!
 * CookieKit — Banner (footer)
 * Renders the banner, the preferences modal and the floating footer button.
 * Stores the consent in the ckit_consent cookie and sends it to the REST endpoint /cookiekit/v1/consent.
 */
(function () {
	'use strict';

	var CFG = window.CKIT_CFG || {};
	var COOKIE = CFG.cookieName || 'ckit_consent';
	var REPROMPT_DAYS = CFG.repromptDays || 180;
	var TEXTS = CFG.texts || {};
	var CATS = CFG.categories || {};
	var I18N = CFG.i18n || {};

	function uuid() {
		if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
			return v.toString(16);
		});
	}

	function readCookie(name) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[$()*+./?[\\\]^{|}]/g, '\\$&') + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : null;
	}

	function setCookie(name, value, days) {
		var d = new Date();
		d.setTime(d.getTime() + days * 86400000);
		document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
	}

	function parse(raw) {
		try { return JSON.parse(raw); } catch (e) { return null; }
	}

	function loadStored() {
		var raw = readCookie(COOKIE);
		var data = parse(raw);
		if (!data || !data.consent_uuid) return null;
		// If the policy version changed, ignore the previous consent.
		if (data.policy_version && CFG.policyVersion && data.policy_version !== CFG.policyVersion) {
			return null;
		}
		// Re-prompt period expired?
		if (data.expires_at && (new Date(data.expires_at)).getTime() < Date.now()) {
			return null;
		}
		return data;
	}

	function persist(action, categories) {
		var consent = {
			consent_uuid: uuid(),
			action: action,
			categories: categories,
			policy_version: CFG.policyVersion,
			timestamp: new Date().toISOString(),
			expires_at: new Date(Date.now() + REPROMPT_DAYS * 86400000).toISOString()
		};
		setCookie(COOKIE, JSON.stringify(consent), REPROMPT_DAYS);
		// REST log (best effort, never blocks the UI).
		try {
			fetch(CFG.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.restNonce },
				body: JSON.stringify({
					action: action,
					categories: Object.keys(categories).filter(function (k) { return categories[k]; }),
					page_url: location.href,
					language: CFG.lang || (navigator.language || '').slice(0, 2),
					consent_uuid: consent.consent_uuid
				})
			});
		} catch (e) { /* swallow */ }
		// Update window.CKIT and Consent Mode (via ckit-blocker.js).
		window.CKIT = window.CKIT || {};
		window.CKIT.consent = { given: true, categories: categories, uuid: consent.consent_uuid, policyVersion: CFG.policyVersion };
		if (typeof window.CKIT.applyConsent === 'function') {
			window.CKIT.applyConsent(categories);
		}
	}

	function el(tag, props, children) {
		var n = document.createElement(tag);
		if (props) {
			for (var k in props) {
				if (k === 'class') n.className = props[k];
				else if (k === 'html') n.innerHTML = props[k];
				else if (k === 'on' && typeof props[k] === 'object') {
					for (var ev in props[k]) n.addEventListener(ev, props[k][ev]);
				} else n.setAttribute(k, props[k]);
			}
		}
		(children || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
		return n;
	}

	// Simple HTML escaping to prevent XSS when a text is rendered as HTML.
	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	// Converts [text](url) syntax into safe <a> links (target=_blank rel=noopener).
	// Everything else is escaped to prevent XSS.
	function richText(text) {
		if (!text) return '';
		var safe = escapeHtml(text);
		// Pattern: [link text](https://example.com) — only http(s) or root-relative URLs.
		return safe.replace(/\[([^\]]+)\]\(((?:https?:\/\/|\/)[^)\s]+)\)/g, function (m, label, url) {
			return '<a href="' + url + '" target="_blank" rel="noopener">' + label + '</a>';
		});
	}

	function renderBanner(onChoice) {
		var root = document.getElementById('ckit-root');
		if (!root) return;
		root.setAttribute('aria-hidden', 'false');
		root.innerHTML = '';

		var pos = (CFG.banner && CFG.banner.position) || 'bottom';
		var layout = (CFG.banner && CFG.banner.layout) || 'box';
		var theme = CFG.theme || 'light';

		var hasLogo = CFG.showLogo && CFG.logoUrl;
		var box = el('div', {
			class: 'ckit-banner ckit-banner--' + pos + ' ckit-banner--' + layout + ' ckit-theme--' + theme + (hasLogo ? ' ckit-banner--with-logo' : ''),
			role: 'dialog',
			'aria-live': 'polite',
			'aria-label': TEXTS.banner_title || ''
		});

		// Close button (X), absolutely positioned top right. Closing the banner counts as a rejection:
		// no consent is given and only necessary cookies are used.
		box.appendChild(el('button', {
			type: 'button',
			class: 'ckit-btn ckit-btn--close',
			'aria-label': TEXTS.btn_close || I18N.close || 'Close',
			on: { click: function () { onChoice('reject_all'); } }
		}, ['×']));

		// Horizontal flex wrapper: [logo | content | actions].
		var inner = el('div', { class: 'ckit-banner__inner' });

		// Left column: logo (if enabled) + vertical separator (CSS border-right).
		if (hasLogo) {
			var logoCol = el('div', { class: 'ckit-banner__logo-col' });
			var logoImg = el('img', { src: CFG.logoUrl, alt: TEXTS.banner_title || I18N.logo || 'Logo', class: 'ckit-banner__logo' });
			if (CFG.logoMaxHeight) logoImg.style.maxHeight = CFG.logoMaxHeight + 'px';
			logoCol.appendChild(logoImg);
			inner.appendChild(logoCol);
		}

		// Center column: title + text + links.
		var content = el('div', { class: 'ckit-banner__content' });
		content.appendChild(el('h2', { class: 'ckit-banner__title' }, [TEXTS.banner_title || '']));
		var msgWrap = el('div', { class: 'ckit-banner__msg' });
		msgWrap.appendChild(el('p', { html: richText(TEXTS.banner_message || '') }));
		msgWrap.appendChild(el('p', { class: 'ckit-banner__links' }, [
			el('a', { href: CFG.privacyPolicyUrl || '#', target: '_blank', rel: 'noopener' }, [TEXTS.privacy_link_text || I18N.privacyPolicy || 'Privacy Policy']),
			document.createTextNode(' · '),
			el('a', { href: CFG.cookiePolicyUrl || '#', target: '_blank', rel: 'noopener' }, [TEXTS.cookies_link_text || I18N.cookiePolicy || 'Cookie Policy'])
		]));
		content.appendChild(msgWrap);
		inner.appendChild(content);

		// Right column: action buttons (stacked on desktop, in a row on mobile).
		var actions = el('div', { class: 'ckit-banner__actions' });
		actions.appendChild(el('button', { type: 'button', class: 'ckit-btn ckit-btn--accept', on: { click: function () { onChoice('accept_all'); } } }, [TEXTS.btn_accept_all || I18N.acceptAll || 'Accept all']));
		actions.appendChild(el('button', { type: 'button', class: 'ckit-btn ckit-btn--reject', on: { click: function () { onChoice('reject_all'); } } }, [TEXTS.btn_reject_all || I18N.rejectAll || 'Reject all']));
		actions.appendChild(el('button', { type: 'button', class: 'ckit-btn ckit-btn--customize', on: { click: function () { onChoice('customize'); } } }, [TEXTS.btn_customize || I18N.customize || 'Customize']));
		inner.appendChild(actions);

		box.appendChild(inner);

		root.appendChild(box);
	}

	function renderModal(currentCategories, onSave) {
		var root = document.getElementById('ckit-root');
		root.innerHTML = '';
		root.setAttribute('aria-hidden', 'false');

		var overlay = el('div', { class: 'ckit-modal-overlay' });
		var modal = el('div', { class: 'ckit-modal ckit-theme--' + (CFG.theme || 'light'), role: 'dialog', 'aria-modal': 'true' });
		// Site logo in the modal (optional, same setting as the banner).
		if (CFG.showLogo && CFG.logoUrl) {
			var modalLogo = el('img', { src: CFG.logoUrl, alt: TEXTS.banner_title || I18N.logo || 'Logo', class: 'ckit-modal__logo' });
			if (CFG.logoMaxHeight) modalLogo.style.maxHeight = CFG.logoMaxHeight + 'px';
			modal.appendChild(modalLogo);
		}
		// Modal title: modal_title if set, otherwise banner_title.
		modal.appendChild(el('h2', { class: 'ckit-modal__title' }, [TEXTS.modal_title || TEXTS.banner_title || I18N.yourPreferences || 'Your preferences']));
		// Modal intro with inline [text](url) links: modal_intro if set, otherwise banner_message.
		modal.appendChild(el('p', { class: 'ckit-modal__intro', html: richText(TEXTS.modal_intro || TEXTS.banner_message || '') }));
		// Privacy/Cookie policy links always visible in the modal.
		modal.appendChild(el('p', { class: 'ckit-modal__links' }, [
			el('a', { href: CFG.privacyPolicyUrl || '#', target: '_blank', rel: 'noopener' }, [TEXTS.privacy_link_text || I18N.privacyPolicy || 'Privacy Policy']),
			document.createTextNode(' · '),
			el('a', { href: CFG.cookiePolicyUrl || '#', target: '_blank', rel: 'noopener' }, [TEXTS.cookies_link_text || I18N.cookiePolicy || 'Cookie Policy'])
		]));

		var list = el('div', { class: 'ckit-modal__cats' });
		var checkboxes = {};
		var COOKIES_BY_CAT = CFG.cookiesByCategory || {};
		Object.keys(CATS).forEach(function (id) {
			var cat = CATS[id];
			var checked = id === 'necessary' || cat.required == 1 || !!currentCategories[id];
			var disabled = id === 'necessary' || cat.required == 1;
			var cb = el('input', { type: 'checkbox', id: 'ckit-cat-' + id });
			cb.checked = checked;
			cb.disabled = disabled;
			checkboxes[id] = cb;

			var row = el('div', { class: 'ckit-modal__cat' }, [
				el('div', { class: 'ckit-modal__cat-head' }, [
					el('label', { for: 'ckit-cat-' + id }, [cat.name]),
					cb
				]),
				el('p', { class: 'ckit-modal__cat-desc' }, [cat.description || ''])
			]);

			// Cookie list under each category (accordion).
			var cookies = COOKIES_BY_CAT[id] || [];
			if (cookies.length) {
				var details = el('details', { class: 'ckit-modal__cat-cookies' });
				var summary = el('summary', { class: 'ckit-modal__cat-cookies-toggle' });
				summary.appendChild(el('span', { class: 'ckit-arrow', 'aria-hidden': 'true' }, ['▸']));
				summary.appendChild(document.createTextNode(' ' + (TEXTS.show_cookies_label || I18N.showCookies || 'Show cookies') + ' (' + cookies.length + ')'));
				details.appendChild(summary);

				var table = el('table', { class: 'ckit-modal__cookies-table' });
				var thead = el('thead', null, [el('tr', null, [
					el('th', null, [TEXTS.col_name || I18N.colName || 'Name']),
					el('th', null, [TEXTS.col_vendor || I18N.colVendor || 'Provider']),
					el('th', null, [TEXTS.col_duration || I18N.colDuration || 'Duration']),
					el('th', null, [TEXTS.col_description || I18N.colDescription || 'Purpose'])
				])]);
				table.appendChild(thead);
				var tbody = el('tbody');
				cookies.forEach(function (c) {
					var nameCell = el('td', { class: 'ckit-cookie-name' }, []);
					var code = el('code', null, [c.name]);
					nameCell.appendChild(code);
					if (c.party) {
						var badge = el('span', { class: 'ckit-cookie-party ckit-cookie-party--' + c.party }, [c.party === 'first' ? (I18N.firstParty || '1st party') : (I18N.thirdParty || '3rd party')]);
						nameCell.appendChild(badge);
					}
					tbody.appendChild(el('tr', null, [
						nameCell,
						el('td', null, [c.vendor || '—']),
						el('td', null, [c.duration || '—']),
						el('td', { class: 'ckit-cookie-desc' }, [c.description || '—'])
					]));
				});
				table.appendChild(tbody);
				details.appendChild(table);
				row.appendChild(details);
			}

			list.appendChild(row);
		});
		modal.appendChild(list);

		var actions = el('div', { class: 'ckit-modal__actions' });
		actions.appendChild(el('button', { type: 'button', class: 'ckit-btn ckit-btn--reject', on: { click: function () { onSave('reject_all', allFalse()); } } }, [TEXTS.btn_reject_all || I18N.rejectAll || 'Reject all']));
		actions.appendChild(el('button', { type: 'button', class: 'ckit-btn ckit-btn--accept', on: { click: function () { onSave('accept_all', allTrue()); } } }, [TEXTS.btn_accept_all || I18N.acceptAll || 'Accept all']));
		actions.appendChild(el('button', { type: 'button', class: 'ckit-btn ckit-btn--save', on: { click: function () { onSave('customize', readCheckboxes()); } } }, [TEXTS.btn_save || I18N.savePreferences || 'Save preferences']));
		modal.appendChild(actions);

		overlay.appendChild(modal);
		root.appendChild(overlay);

		function allFalse() {
			var o = {};
			Object.keys(CATS).forEach(function (k) { o[k] = (k === 'necessary' || CATS[k].required == 1); });
			return o;
		}
		function allTrue() {
			var o = {};
			Object.keys(CATS).forEach(function (k) { o[k] = true; });
			return o;
		}
		function readCheckboxes() {
			var o = {};
			Object.keys(checkboxes).forEach(function (k) { o[k] = !!checkboxes[k].checked; });
			return o;
		}
	}

	function clearRoot() {
		var root = document.getElementById('ckit-root');
		if (root) {
			root.innerHTML = '';
			root.setAttribute('aria-hidden', 'true');
		}
	}

	function renderFloatingButton() {
		if (!CFG.showFloating) return;
		if (document.getElementById('ckit-floating')) return;
		var btn = el('button', {
			id: 'ckit-floating',
			class: 'ckit-floating ckit-floating--' + (CFG.floatingPos || 'bottom-left'),
			type: 'button',
			'aria-label': TEXTS.floating_button || I18N.manageCookies || 'Manage cookies',
			on: { click: function (e) { e.preventDefault(); openPreferences(); } }
		}, []);
		btn.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10c0-.39-.04-.77-.08-1.15a3.5 3.5 0 0 1-4.55-4.55A10 10 0 0 0 12 2zm-1.5 5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-3 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm6 3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-5 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>';
		document.body.appendChild(btn);
	}

	function openPreferences() {
		var stored = loadStored() || { categories: {} };
		renderModal(stored.categories || {}, function (action, cats) {
			persist(action, cats);
			clearRoot();
		});
	}

	function start() {
		// Global hook: clicking any element with data-ckit-open-prefs opens the modal.
		// Clicking [data-ckit-load-iframe] in a placeholder loads that single iframe once.
		document.addEventListener('click', function (e) {
			var t = e.target.closest('[data-ckit-open-prefs]');
			if (t) { e.preventDefault(); openPreferences(); }
			var ift = e.target.closest('[data-ckit-load-iframe]');
			if (ift) {
				var ph = ift.closest('.ckit-iframe-placeholder');
				if (ph) {
					var src = ph.getAttribute('data-ckit-iframe-src');
					var iframe = document.createElement('iframe');
					iframe.src = src;
					iframe.setAttribute('frameborder', '0');
					iframe.setAttribute('allowfullscreen', '');
					iframe.style.width = '100%';
					iframe.style.minHeight = '315px';
					ph.parentNode.replaceChild(iframe, ph);
				}
			}
		});

		renderFloatingButton();

		var stored = loadStored();
		if (stored && stored.consent_uuid) {
			// Consent already given: just apply it.
			if (typeof window.CKIT !== 'undefined' && typeof window.CKIT.applyConsent === 'function') {
				window.CKIT.applyConsent(stored.categories || {});
			}
			return;
		}

		renderBanner(function (action) {
			if (action === 'accept_all') {
				var all = {};
				Object.keys(CATS).forEach(function (k) { all[k] = true; });
				persist('accept_all', all);
				clearRoot();
			} else if (action === 'reject_all') {
				var none = {};
				Object.keys(CATS).forEach(function (k) { none[k] = (k === 'necessary' || CATS[k].required == 1); });
				persist('reject_all', none);
				clearRoot();
			} else if (action === 'customize') {
				renderModal({}, function (act, cats) {
					persist(act, cats);
					clearRoot();
				});
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
