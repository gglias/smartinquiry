/*
 * SmartInquiry form. Opens from any link to #smartup-inquiry[-<service>][-<language>] or any element with the
 * class js-inquiry (optional data-service / data-lang). All content comes from window.smartInquiry.
 * Answers are option ids (not texts), so they survive a language change and are checked again on the server.
 */
(function () {
	'use strict';

	var cfg = window.smartInquiry;
	if (!cfg || !Array.isArray(cfg.services) || !cfg.services.length) return;

	var HASH = /#smartup-inquiry(?:-([a-z0-9_-]+))?$/;

	/* ---------- Icons ---------- */
	var P = {
		globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		cart: '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/>',
		phone: '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
		app: '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 9h18M8 13h4M8 16h7"/>',
		spark: '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 16l.7 2.3L22 19l-2.3.7L19 22l-.7-2.3L16 19l2.3-.7z"/>',
		chat: '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/>',
		form: '<rect x="4" y="3" width="16" height="18" rx="2.5"/><path d="M8 8h8M8 12h8M8 16h5"/>',
		brush: '<path d="M14.5 4.5l5 5-8 8H6.5v-5z"/><path d="M12 7l5 5"/>',
		shield: '<path d="M12 3l7 3v5c0 5-3.2 8.3-7 10-3.8-1.7-7-5-7-10V6z"/>',
		camera: '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
		megaphone: '<path d="M4 10v4h3l9 5V5L7 10z"/><path d="M19 9.5a3 3 0 0 1 0 5"/>',
		code: '<path d="M8 7l-5 5 5 5M16 7l5 5-5 5M13.5 5l-3 14"/>',
		check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		left: '<path d="M15 5l-7 7 7 7"/>',
		right: '<path d="M9 5l7 7-7 7"/>',
		x: '<path d="M6 6l12 12M18 6L6 18"/>',
		info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
		clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		file: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 11v6M9.5 14.5L12 17l2.5-2.5"/>',
		send: '<path d="M4 12l16-8-6 16-2.5-6.5z"/><path d="M11.5 13.5L20 4"/>',
		reset: '<path d="M4 12a8 8 0 1 0 2.4-5.7L4 8.5"/><path d="M4 3.5v5h5"/>'
	};
	function icon(n) {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (P[n] || P.form) + '</svg>';
	}
	// The "S" emblem of the Smartup logo.
	var EMBLEM = '<svg viewBox="0 0 25 31" fill="currentColor" aria-hidden="true"><rect x="6.26" width="18.74" height="6.21"/><rect y="6.21" width="6.26" height="6.21"/><rect x="6.26" y="12.37" width="12.48" height="6.21"/><rect x="18.74" y="18.58" width="6.26" height="6.21"/><rect y="24.79" width="18.74" height="6.21"/></svg>';

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}

	/* ---------- Language ---------- */
	var lang = cfg.lang;
	function tr(map) {
		if (!map || typeof map !== 'object') return map ? String(map) : '';
		var order = [lang, cfg.lang, 'en', 'el'];
		for (var i = 0; i < order.length; i++) if (map[order[i]]) return map[order[i]];
		for (var k in map) if (map[k]) return map[k];
		return '';
	}
	function t(key) { return tr(cfg.texts[key]); }
	function shortLabel(s) { return String(s).replace(/[\s?;:;]+$/, ''); }

	/* ---------- State (kept for the tab's lifetime, discarded when the form changes) ---------- */
	var KEY = 'smartinquiry-' + cfg.version;
	function fresh() { return { step: 0, service: null, a: {}, o: {}, done: false, ref: '', date: '' }; }
	var state = fresh();
	try {
		// Answers kept for an older version of the form are of no use any more.
		Object.keys(sessionStorage).forEach(function (k) {
			if (k.indexOf('smartinquiry-') === 0 && k !== KEY) sessionStorage.removeItem(k);
		});
		var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
		if (saved && typeof saved === 'object' && saved.a && saved.o) state = saved;
	} catch (e) {}
	function save() {
		try { sessionStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
	}

	function svc(id) {
		id = id || state.service;
		for (var i = 0; i < cfg.services.length; i++) if (cfg.services[i].id === id) return cfg.services[i];
		return null;
	}
	function flow() {
		var s = svc();
		var f = ['service'];
		// A step without questions is skipped.
		if (!s || (s.details && s.details.length)) f.push('details');
		if (!s || (s.features && s.features.length)) f.push('features');
		return f.concat(['plan', 'contact']);
	}
	function planQs(s) {
		return [
			{ id: 'when', type: 'single', req: 1, label: cfg.texts.q_when, opts: cfg.when },
			{ id: 'budget', type: 'single', req: 1, label: cfg.texts.q_budget, hint: cfg.texts.budget_hint, opts: s ? s.budget : [] },
			{ id: 'notes', type: 'textarea', label: cfg.texts.q_notes, hint: cfg.texts.optional, ph: cfg.texts.notes_ph }
		];
	}
	function contactQs() {
		return [
			{ id: 'name', type: 'text', req: 1, label: cfg.texts.f_name, ph: cfg.texts.f_name_ph, ac: 'name', half: 1 },
			{ id: 'email', type: 'email', req: 1, label: cfg.texts.f_email, ph: cfg.texts.f_email_ph, ac: 'email', half: 1 },
			{ id: 'phone', type: 'tel', req: 1, label: cfg.texts.f_phone, ph: cfg.texts.f_phone_ph, ac: 'tel', half: 1 },
			{ id: 'company', type: 'text', label: cfg.texts.f_company, hint: cfg.texts.optional, ph: cfg.texts.f_company_ph, ac: 'organization', half: 1 },
			{ id: 'pref', type: 'single', req: 1, label: cfg.texts.q_pref, opts: [{ id: 'phone', label: cfg.texts.pref_phone }, { id: 'email', label: cfg.texts.pref_email }] },
			{ id: 'consent', type: 'consent', req: 1 }
		];
	}
	function stepQs(key) {
		var s = svc();
		if (key === 'details' || key === 'features') return s ? (s[key] || []) : [];
		if (key === 'plan') return planQs(s);
		if (key === 'contact') return contactQs();
		return [];
	}
	function visible(q) {
		return !q.show_if || state.a[q.show_if.q] === q.show_if.opt;
	}
	function answered(q) {
		var v = state.a[q.id];
		if (q.type === 'multi') return Array.isArray(v) && v.length > 0;
		if (q.type === 'consent') return v === true;
		return typeof v === 'string' && v.trim() !== '';
	}
	function optLabel(q, id) {
		if (id === 'other') {
			var o = (state.o[q.id] || '').trim();
			return t('other') + (o ? ': ' + o : '');
		}
		for (var i = 0; i < q.opts.length; i++) if (q.opts[i].id === id) return tr(q.opts[i].label);
		return '';
	}
	function display(q) {
		var v = state.a[q.id];
		if (q.type === 'multi') return (Array.isArray(v) ? v : []).map(function (x) { return optLabel(q, x); }).filter(Boolean).join(', ');
		if (q.type === 'single') return answered(q) ? optLabel(q, v) : '';
		return v || '';
	}

	/* ---------- Colours from the settings ---------- */
	function hexToRgb(hex) {
		var m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
		if (!m) return null;
		var n = parseInt(m[1], 16);
		return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
	}
	function applyLook(root) {
		var l = cfg.look || {};
		var rgb = hexToRgb(l.accent) || [70, 233, 212];
		root.style.setProperty('--smi-accent', l.accent || '#46e9d4');
		root.style.setProperty('--smi-accent-text', l.accent_text || '#000000');
		root.style.setProperty('--smi-panel', l.panel || '#fafafa');
		root.style.setProperty('--smi-accent-soft', 'rgba(' + rgb.join(',') + ',0.14)');
		// Readable version of the accent for icons and ticks on white.
		root.style.setProperty('--smi-accent-deep', 'rgb(' + rgb.map(function (c) { return Math.round(c * 0.45); }).join(',') + ')');
	}

	/* ---------- Build the dialog once ---------- */
	var root, body, back, next, foot, lastFocus = null, token = null, tokenAt = 0, sending = false;

	function build() {
		root = document.createElement('div');
		root.id = 'smartinquiry';
		root.hidden = true;
		root.innerHTML =
			'<div class="smi-modal" role="dialog" aria-modal="true" aria-labelledby="smi-title">' +
				'<aside class="smi-side">' +
					'<div class="smi-brand"><span class="smi-mark">' + EMBLEM + '</span><div><strong data-t="brand_title"></strong><small data-t="brand_sub"></small></div></div>' +
					'<ol class="smi-steps" id="smi-steps"></ol>' +
					'<div class="smi-sum"><p class="smi-sum__h" data-t="summary"></p><div id="smi-sum"></div></div>' +
					'<p class="smi-side__foot" id="smi-reply">' + icon('clock') + '<span data-t="reply_time"></span></p>' +
				'</aside>' +
				'<section class="smi-main">' +
					'<div class="smi-top"><span class="smi-count" id="smi-count"></span>' +
						'<div class="smi-tools">' +
							'<button type="button" class="smi-clear" id="smi-clear" aria-haspopup="dialog" aria-expanded="false"></button>' +
							'<button type="button" class="smi-x" id="smi-close">' + icon('x') + '</button>' +
							'<div class="smi-confirm" id="smi-confirm" role="dialog" aria-labelledby="smi-confirm-title" hidden>' +
								'<div><strong id="smi-confirm-title" data-t="clear_title"></strong><span data-t="clear_text"></span></div>' +
								'<div class="smi-confirm__actions"><button type="button" class="smi-btn smi-btn--ghost smi-btn--sm" id="smi-no" data-t="cancel"></button><button type="button" class="smi-btn smi-btn--danger smi-btn--sm" id="smi-yes" data-t="clear_yes"></button></div>' +
							'</div>' +
						'</div></div>' +
					'<div class="smi-bar" aria-hidden="true"><span id="smi-fill"></span></div>' +
					'<div class="smi-body" id="smi-body"></div>' +
					'<div class="smi-foot" id="smi-foot"><button type="button" class="smi-btn smi-btn--ghost" id="smi-back"></button><button type="button" class="smi-btn smi-btn--primary" id="smi-next"></button></div>' +
				'</section>' +
			'</div>';
		host().appendChild(root);
		applyLook(root);
		// Smooth-scroll scripts (Lenis and the like) listen on the window and take over the mouse wheel; stopping the
		// event at the form keeps its own panels scrolling natively. data-lenis-prevent is Lenis's own opt-out.
		root.setAttribute('data-lenis-prevent', '');
		['wheel', 'touchmove'].forEach(function (type) {
			root.addEventListener(type, function (e) { e.stopPropagation(); }, { passive: true });
		});
		body = $('smi-body'); back = $('smi-back'); next = $('smi-next'); foot = $('smi-foot');
		bind();
	}
	function $(id) { return document.getElementById(id); }
	// Themes (Ohio and others) may put a transform on <body>, which would trap a fixed element; then use <html>.
	function host() {
		var cs = window.getComputedStyle(document.body);
		var trapped = (cs.transform && cs.transform !== 'none') || (cs.filter && cs.filter !== 'none') || /transform|filter/.test(cs.willChange || '');
		return trapped ? document.documentElement : document.body;
	}

	function applyTexts() {
		Array.prototype.forEach.call(root.querySelectorAll('[data-t]'), function (el) { el.textContent = t(el.getAttribute('data-t')); });
		$('smi-reply').hidden = !t('reply_time');
		$('smi-close').setAttribute('aria-label', t('close'));
		$('smi-clear').innerHTML = icon('reset') + '<span>' + esc(t('clear')) + '</span>';
		$('smi-clear').setAttribute('aria-label', t('clear_title').replace(/[;?;]$/, ''));
		root.setAttribute('lang', lang);
	}

	/* ---------- Rendering ---------- */
	function render() {
		var f = flow();
		if (state.step >= f.length) state.step = f.length - 1;
		var key = f[state.step];
		renderSide(f);
		hideConfirm();
		$('smi-clear').hidden = state.done;
		$('smi-clear').disabled = !state.service && !Object.keys(state.a).length;
		if (state.done) return renderDone();

		$('smi-count').textContent = t('step_of').replace('{n}', state.step + 1).replace('{t}', f.length);
		$('smi-fill').style.width = ((state.step + 1) / f.length * 100) + '%';
		foot.hidden = key === 'service' && !state.service;
		back.innerHTML = icon('left') + '<span>' + esc(t('back')) + '</span>';
		back.style.visibility = state.step === 0 ? 'hidden' : 'visible';
		next.innerHTML = key === 'contact' ? '<span>' + esc(t(sending ? 'sending' : 'submit')) + '</span>' + icon('send') : '<span>' + esc(t('next')) + '</span>' + icon('right');
		next.disabled = sending;

		var html = '<div class="smi-step">';
		if (key === 'service') {
			html += head(t('s1_title'), t('s1_sub')) + infoBox(t('info_service'));
			html += '<div class="smi-cards" role="radiogroup" aria-labelledby="smi-title">';
			cfg.services.forEach(function (s) {
				html += '<button type="button" class="smi-card" role="radio" data-service="' + esc(s.id) + '" aria-checked="' + (state.service === s.id) + '">' +
					'<span class="smi-card__icon">' + icon(s.icon) + '</span>' +
					'<span><strong>' + esc(tr(s.label)) + '</strong>' + (tr(s.desc) ? '<small>' + esc(tr(s.desc)) + '</small>' : '') + '</span>' +
					'<span class="smi-card__tick">' + icon('check') + '</span></button>';
			});
			html += '</div>';
		} else {
			var s = svc();
			if (key === 'details') html += head(tr(s.details_title) || tr(s.label), tr(s.details_sub));
			else if (key === 'features') html += head(tr(s.features_title) || t('step_features'), tr(s.features_sub)) + infoBox(t('info_features'));
			else if (key === 'plan') html += head(t('plan_title'), t('plan_sub')) + infoBox(t('info_plan'));
			else html += head(t('contact_title'), t('contact_sub'));
			html += '<div class="smi-qs">';
			var qs = stepQs(key), i = 0;
			while (i < qs.length) {
				if (qs[i].half) {
					html += '<div class="smi-grid2">';
					while (i < qs.length && qs[i].half) html += renderQ(qs[i++]);
					html += '</div>';
				} else {
					html += renderQ(qs[i++]);
				}
			}
			html += '</div>';
			if (key === 'contact') {
				html += infoBox(t('info_contact'));
				html += '<div class="smi-hp" aria-hidden="true"><label>Website <input type="text" id="smi-website" tabindex="-1" autocomplete="off"></label></div>';
				if (tsOn()) html += '<div class="smi-ts"><div id="smi-ts-box"></div><p class="smi-ts__err" id="smi-ts-err" role="alert" hidden></p></div>';
				html += '<div class="smi-alert" id="smi-alert" role="alert" hidden></div>';
			}
		}
		html += '</div>';
		body.innerHTML = html;
		body.scrollTop = 0;
		if (key === 'contact') tsMount();
		save();
	}

	function head(title, sub) {
		return '<div class="smi-head"><h2 id="smi-title" tabindex="-1">' + esc(title) + '</h2>' + (sub ? '<p>' + esc(sub) + '</p>' : '') + '</div>';
	}
	function infoBox(text) {
		return text ? '<div class="smi-info">' + icon('info') + '<span>' + esc(text) + '</span></div>' : '';
	}

	function renderQ(q) {
		var hidden = visible(q) ? '' : ' hidden';
		var label = esc(tr(q.label));
		var hint = tr(q.hint) ? '<small>' + esc(tr(q.hint)) + '</small>' : '';
		var err = '<p class="smi-err" id="smi-err-' + esc(q.id) + '"></p>';
		if (q.type === 'single' || q.type === 'multi') {
			var multi = q.type === 'multi';
			var cur = state.a[q.id];
			var opts = q.opts.map(function (o) { return { id: o.id, text: tr(o.label) }; });
			if (q.other) opts.push({ id: 'other', text: t('other') });
			var chips = opts.map(function (o) {
				var on = multi ? (Array.isArray(cur) && cur.indexOf(o.id) > -1) : cur === o.id;
				return '<button type="button" class="smi-chip" role="' + (multi ? 'checkbox' : 'radio') + '" aria-checked="' + on + '" data-q="' + esc(q.id) + '" data-v="' + esc(o.id) + '">' + icon('check') + '<span>' + esc(o.text) + '</span></button>';
			}).join('');
			var otherOn = multi ? (Array.isArray(cur) && cur.indexOf('other') > -1) : cur === 'other';
			var other = q.other ? '<input class="smi-field" type="text" maxlength="300" data-other="' + esc(q.id) + '" placeholder="' + esc(t('other_ph')) + '" value="' + esc(state.o[q.id] || '') + '"' + (otherOn ? '' : ' hidden') + ' aria-label="' + label + ': ' + esc(t('other')) + '">' : '';
			return '<fieldset class="smi-q" data-qwrap="' + esc(q.id) + '"' + hidden + '><legend class="smi-q__label">' + label + hint + '</legend>' +
				'<div class="smi-chips" role="' + (multi ? 'group' : 'radiogroup') + '">' + chips + '</div>' + other + err + '</fieldset>';
		}
		if (q.type === 'consent') {
			var url = cfg.privacy ? (cfg.privacy[lang] || cfg.privacy[cfg.lang] || '') : '';
			var link = url ? '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(t('privacy_link')) + '</a>' : esc(t('privacy_link'));
			return '<div class="smi-q" data-qwrap="consent"><label class="smi-consent"><input type="checkbox" id="smi-f-consent" data-field="consent"' + (state.a.consent === true ? ' checked' : '') + '>' +
				'<span>' + esc(t('consent')).replace('{link}', link) + '</span></label>' + err + '</div>';
		}
		var val = esc(state.a[q.id] || '');
		var max = q.type === 'textarea' ? 3000 : (q.id === 'phone' ? 40 : 190);
		var attrs = ' id="smi-f-' + esc(q.id) + '" data-field="' + esc(q.id) + '" maxlength="' + max + '" placeholder="' + esc(tr(q.ph)) + '"' + (q.ac ? ' autocomplete="' + q.ac + '"' : '');
		var input = q.type === 'textarea'
			? '<textarea class="smi-field"' + attrs + '>' + val + '</textarea>'
			: '<input class="smi-field" type="' + q.type + '"' + attrs + ' value="' + val + '"' + (q.type === 'tel' ? ' inputmode="tel"' : '') + '>';
		return '<div class="smi-q" data-qwrap="' + esc(q.id) + '"' + hidden + '><label class="smi-q__label" for="smi-f-' + esc(q.id) + '">' + label + hint + '</label>' + input + err + '</div>';
	}

	function summaryRows() {
		var rows = [];
		var s = svc();
		if (s) rows.push([t('step_service'), tr(s.label)]);
		var f = flow();
		['details', 'features', 'plan'].forEach(function (k) {
			if (f.indexOf(k) < 0) return;
			stepQs(k).forEach(function (q) {
				if (visible(q) && answered(q)) rows.push([shortLabel(tr(q.label)), display(q)]);
			});
		});
		return rows;
	}

	function renderSide(f) {
		var names = { service: t('step_service'), details: t('step_details'), features: t('step_features'), plan: t('step_plan'), contact: t('step_contact') };
		$('smi-steps').innerHTML = f.map(function (k, i) {
			var cls = state.done || i < state.step ? 'is-done' : (i === state.step ? 'is-current' : '');
			var n = cls === 'is-done' ? icon('check') : String(i + 1);
			var inner = '<span class="smi-n">' + n + '</span><span>' + esc(names[k]) + '</span>';
			return '<li class="' + cls + '"' + (i === state.step && !state.done ? ' aria-current="step"' : '') + '>' +
				(!state.done && i < state.step ? '<button type="button" data-jump="' + i + '">' + inner + '</button>' : inner) + '</li>';
		}).join('');
		var rows = summaryRows();
		$('smi-sum').innerHTML = rows.length
			? '<dl>' + rows.map(function (r) { return '<div><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>'; }).join('') + '</dl>'
			: '<p class="smi-sum__empty">' + esc(t('summary_empty')) + '</p>';
	}

	function renderDone() {
		$('smi-count').textContent = '';
		$('smi-fill').style.width = '100%';
		foot.hidden = true;
		var first = (state.a.name || '').trim().split(/\s+/)[0] || '';
		var where = '<strong>' + esc(state.a.pref === 'phone' ? state.a.phone : state.a.email) + '</strong>';
		body.innerHTML = '<div class="smi-done">' +
			'<span class="smi-done__icon">' + icon('check') + '</span>' +
			'<span class="smi-done__ref">' + esc(t('request')) + ' ' + esc(state.ref) + '</span>' +
			'<h2 id="smi-title" tabindex="-1">' + esc(t('thanks')) + (first ? ', ' + esc(first) : '') + '!</h2>' +
			(t('received') ? '<p>' + esc(t('received')).replace('{where}', where) + '</p>' : '') +
			(t('pdf_copy') ? '<p>' + esc(t('pdf_copy')) + '</p>' : '') +
			'<div class="smi-done__actions">' +
				'<button type="button" class="smi-btn smi-btn--primary" id="smi-pdf">' + icon('file') + '<span>' + esc(t('pdf_btn')) + '</span></button>' +
				'<button type="button" class="smi-btn smi-btn--ghost" id="smi-done-close">' + esc(t('close')) + '</button>' +
			'</div>' +
			'<button type="button" class="smi-link" id="smi-restart">' + esc(t('restart')) + '</button>' +
		'</div>';
		save();
	}

	/* ---------- PDF ---------- */
	/**
	 * The server's copy (the same file the team gets by email) when it can make one; otherwise, or if its
	 * short-lived link has expired, the browser draws it from the answers.
	 */
	function downloadPdf() {
		var fallback = function () { return window.SmartInquiryPDF.download(pdfDoc()); };
		if (!state.pdf) return fallback();
		return fetch(state.pdf, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
			var type = r.headers.get('Content-Type') || '';
			if (!r.ok || type.indexOf('application/pdf') !== 0) return fallback();
			return r.blob().then(function (blob) {
				var url = URL.createObjectURL(blob);
				var a = document.createElement('a');
				a.href = url;
				a.download = String((t('pdf_file') || 'request') + '-' + state.ref).replace(/[^A-Za-z0-9_-]+/g, '-') + '.pdf';
				document.body.appendChild(a);
				a.click();
				setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 4000);
			});
		}, fallback);
	}
	function pdfDoc() {
		var sections = [];
		var s = svc();
		sections.push({ title: t('pdf_service'), rows: [[t('pdf_need'), s ? tr(s.label) : '']] });
		var f = flow();
		[['details', t('step_details')], ['features', t('step_features')], ['plan', t('step_plan')]].forEach(function (x) {
			if (f.indexOf(x[0]) < 0) return;
			var rows = stepQs(x[0]).filter(function (q) { return visible(q) && answered(q); }).map(function (q) { return [shortLabel(tr(q.label)), display(q)]; });
			if (rows.length) sections.push({ title: x[1], rows: rows });
		});
		var pref = contactQs()[4];
		sections.push({ title: t('pdf_contact'), rows: [
			[t('f_name'), state.a.name], [t('f_email'), state.a.email], [t('f_phone'), state.a.phone],
			[t('f_company'), state.a.company || ''], [t('pdf_via'), display(pref)]
		].filter(function (r) { return r[1]; }) });
		var p = cfg.pdf || {};
		return {
			accent: cfg.look && cfg.look.accent, logo: p.logo, company: p.company || {}, lang: lang,
			title: t('pdf_title'), ref: state.ref, date: state.date, sections: sections,
			footer: tr(p.footer) || t('reply_time'), filename: (t('pdf_file') || 'request') + '-' + state.ref
		};
	}

	/* ---------- Validation ---------- */
	function validate(key) {
		var ok = true, firstBad = null;
		stepQs(key).forEach(function (q) {
			var wrap = body.querySelector('[data-qwrap="' + q.id + '"]');
			if (!wrap || wrap.hidden) return;
			var msg = '';
			if (q.req && !answered(q)) {
				msg = q.type === 'single' ? t('err_single') : q.type === 'multi' ? t('err_multi') : q.type === 'consent' ? t('err_consent') : t('err_field');
			} else if (q.type === 'email' && answered(q) && !/^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(state.a.email.trim())) {
				// Same rule as the server: Latin address, no ? & % = (they would turn a mailto link into one with hidden headers).
				msg = t('err_email');
			} else if (q.type === 'tel' && answered(q) && state.a.phone.replace(/\D/g, '').length < 10) {
				msg = t('err_phone');
			}
			wrap.classList.toggle('smi-q--err', !!msg);
			var e = $('smi-err-' + q.id);
			if (e) e.textContent = msg;
			if (msg) {
				ok = false;
				if (!firstBad) firstBad = wrap;
			}
		});
		if (firstBad) {
			firstBad.scrollIntoView({ block: 'center', behavior: 'smooth' });
			var focusable = firstBad.querySelector('input, textarea, button');
			if (focusable) focusable.focus({ preventScroll: true });
		}
		return ok;
	}
	function clearErr(id) {
		var wrap = body.querySelector('[data-qwrap="' + id + '"]');
		if (wrap) wrap.classList.remove('smi-q--err');
	}
	function refreshVisibility() {
		stepQs(flow()[state.step]).forEach(function (q) {
			var wrap = body.querySelector('[data-qwrap="' + q.id + '"]');
			if (wrap) wrap.hidden = !visible(q);
		});
	}

	/* ---------- Cloudflare Turnstile (only when switched on in the settings) ---------- */
	/**
	 * Cloudflare's script is loaded only when the visitor reaches the last step, so other pages carry nothing extra.
	 * A token works once and expires after a few minutes, so a fresh check is drawn each time the step is drawn.
	 * If the page already loads Turnstile (e.g. a Contact Form 7 form), that copy is used.
	 */
	var ts = { id: null, token: '', loading: null };
	function tsOn() { return !!(cfg.turnstile && cfg.turnstile.site); }
	function tsLoad() {
		if (window.turnstile) return Promise.resolve();
		if (ts.loading) return ts.loading;
		ts.loading = new Promise(function (resolve, reject) {
			var mine = !document.querySelector('script[src*="challenges.cloudflare.com/turnstile/"]');
			if (mine) {
				var sc = document.createElement('script');
				sc.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
				sc.async = true;
				sc.onerror = function () { ts.loading = null; reject(); };
				document.head.appendChild(sc);
			}
			var waited = 0;
			(function poll() {
				if (window.turnstile) return resolve();
				if ((waited += 100) > 15000) { ts.loading = null; return reject(); }
				setTimeout(poll, 100);
			})();
		});
		return ts.loading;
	}
	function tsError(msg) {
		var el = $('smi-ts-err');
		if (!el) return;
		el.textContent = msg || '';
		el.hidden = !msg;
		if (msg) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
	}
	function tsRemove() {
		if (ts.id !== null && window.turnstile) { try { window.turnstile.remove(ts.id); } catch (e) {} }
		ts.id = null;
		ts.token = '';
	}
	function tsMount() {
		if (!tsOn()) return;
		tsRemove();
		tsLoad().then(function () {
			var box = $('smi-ts-box');
			if (!box || box.childNodes.length) return;
			ts.id = window.turnstile.render(box, {
				sitekey: cfg.turnstile.site,
				action: 'smartinquiry',
				theme: 'light',
				size: 'flexible',
				language: 'auto',
				'refresh-expired': 'auto',
				callback: function (token) { ts.token = token; tsError(''); },
				'expired-callback': function () { ts.token = ''; },
				'error-callback': function () { ts.token = ''; }
			});
		}, function () {
			tsError(t('err_turnstile_down'));
		});
	}

	/* ---------- Sending ---------- */
	function fetchToken() {
		return fetch(cfg.rest.token, { method: 'POST', credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (d) { token = d && d.token; tokenAt = Date.now(); return token; });
	}
	function collect() {
		var s = svc();
		var answers = {}, other = {};
		['details', 'features'].forEach(function (k) {
			(s[k] || []).forEach(function (q) {
				if (!visible(q) || !answered(q)) return;
				answers[q.id] = state.a[q.id];
				if (q.other && [].concat(state.a[q.id]).indexOf('other') > -1 && state.o[q.id]) other[q.id] = state.o[q.id];
			});
		});
		var hp = $('smi-website');
		return {
			token: token, website: hp ? hp.value : '', lang: lang, service: s.id, answers: answers, other: other,
			when: state.a.when || '', budget: state.a.budget || '', notes: state.a.notes || '',
			name: state.a.name || '', email: (state.a.email || '').trim(), phone: state.a.phone || '', company: state.a.company || '',
			pref: state.a.pref || '', consent: state.a.consent === true, turnstile: ts.token
		};
	}
	function showAlert(msg) {
		var a = $('smi-alert');
		if (!a) return;
		a.textContent = msg;
		a.hidden = !msg;
		if (msg) a.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
	}
	function submit(retried) {
		sending = true;
		next.disabled = true;
		next.querySelector('span').textContent = t('sending');
		showAlert('');
		// The server wants the token to be at least a few seconds old.
		var wait = Math.max(0, 3500 - (Date.now() - tokenAt));
		var ready = token ? new Promise(function (r) { setTimeout(r, wait); }) : fetchToken().then(function () { return new Promise(function (r) { setTimeout(r, 3500); }); });
		ready.then(function () {
			return fetch(cfg.rest.submit, {
				method: 'POST',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(collect())
			});
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, d: d || {} }; });
		}).then(function (res) {
			token = null;
			if (res.d.ok) {
				sending = false;
				tsRemove();
				state.done = true;
				state.ref = res.d.ref;
				state.date = res.d.date;
				state.pdf = res.d.pdf || '';
				render();
				focusTitle();
				return;
			}
			if (res.status === 403 && !retried) {
				// Expired or already used: a fresh token, sent once it is old enough.
				return fetchToken().then(function () { submit(true); });
			}
			sending = false;
			if (res.d.code === 'turnstile' || res.d.code === 'turnstile_down') {
				// The token was spent (or refused): render() puts up a fresh check.
				render();
				tsError(t(res.d.code === 'turnstile' ? 'err_turnstile' : 'err_turnstile_down'));
				return;
			}
			if (res.status === 400 && res.d.field && showFieldError(res.d.field)) return;
			render();
			showAlert(res.status === 429 ? t('err_rate') : res.status === 400 ? t('err_changed') : t('err_send'));
		}).catch(function () {
			sending = false;
			token = null;
			render();
			showAlert(t('err_send'));
		});
	}

	/**
	 * The server refused one answer: go to its step and show the message next to it. Returns false when that
	 * question isn't in the form any more (the page was opened before the form changed).
	 */
	function showFieldError(field) {
		var f = flow();
		var at = -1;
		f.forEach(function (key, i) {
			if (key === 'service' ? field === 'service' : stepQs(key).some(function (q) { return q.id === field; })) at = i;
		});
		if (at < 0 || field === 'service') return false;
		state.step = at;
		render();
		var q = stepQs(f[at]).filter(function (x) { return x.id === field; })[0];
		var wrap = body.querySelector('[data-qwrap="' + field + '"]');
		if (!wrap || !q) return false;
		var msg = field === 'email' ? t('err_email') : field === 'phone' ? t('err_phone') : q.type === 'consent' ? t('err_consent') : q.type === 'multi' ? t('err_multi') : q.type === 'single' ? t('err_single') : t('err_field');
		wrap.classList.add('smi-q--err');
		var e = $('smi-err-' + field);
		if (e) e.textContent = msg;
		wrap.scrollIntoView({ block: 'center', behavior: 'smooth' });
		return true;
	}

	/* ---------- Navigation ---------- */
	function go(delta) {
		state.step = Math.max(0, Math.min(flow().length - 1, state.step + delta));
		render();
		focusTitle();
	}
	function focusTitle() {
		var h = $('smi-title');
		if (h) h.focus({ preventScroll: true });
	}
	function chooseService(id) {
		if (!svc(id)) return false;
		if (state.service !== id) {
			state.service = id;
			delete state.a.budget; // budget ranges differ per service
		}
		return true;
	}
	function resetForm() {
		state = fresh();
		render();
		focusTitle();
	}
	function hideConfirm() {
		$('smi-confirm').hidden = true;
		$('smi-clear').setAttribute('aria-expanded', 'false');
	}

	function bind() {
		next.addEventListener('click', function () {
			if (sending) return;
			var key = flow()[state.step];
			if (key !== 'service' && !validate(key)) return;
			if (key === 'contact' && tsOn() && !ts.token) return tsError(t('err_turnstile'));
			if (key === 'contact') return submit(false);
			go(1);
		});
		back.addEventListener('click', function () { if (!sending) go(-1); });

		body.addEventListener('click', function (e) {
			var card = e.target.closest('[data-service]');
			if (card) {
				chooseService(card.getAttribute('data-service'));
				Array.prototype.forEach.call(body.querySelectorAll('.smi-card'), function (c) { c.setAttribute('aria-checked', String(c === card)); });
				renderSide(flow());
				save();
				setTimeout(function () { if (flow()[state.step] === 'service') go(1); }, 220);
				return;
			}
			var chip = e.target.closest('.smi-chip');
			if (chip) {
				var id = chip.getAttribute('data-q'), v = chip.getAttribute('data-v');
				var multi = chip.getAttribute('role') === 'checkbox';
				if (multi) {
					var arr = Array.isArray(state.a[id]) ? state.a[id].slice() : [];
					var at = arr.indexOf(v);
					if (at > -1) arr.splice(at, 1); else arr.push(v);
					state.a[id] = arr;
					chip.setAttribute('aria-checked', String(at < 0));
				} else {
					state.a[id] = v;
					Array.prototype.forEach.call(chip.parentNode.children, function (c) { c.setAttribute('aria-checked', String(c === chip)); });
				}
				var otherInput = body.querySelector('[data-other="' + id + '"]');
				if (otherInput) {
					var on = multi ? state.a[id].indexOf('other') > -1 : state.a[id] === 'other';
					otherInput.hidden = !on;
					if (on && v === 'other') otherInput.focus();
				}
				clearErr(id);
				refreshVisibility();
				renderSide(flow());
				save();
				return;
			}
			if (e.target.closest('#smi-pdf')) {
				var b = e.target.closest('#smi-pdf');
				b.disabled = true;
				downloadPdf().then(function () { b.disabled = false; }, function () { b.disabled = false; });
				return;
			}
			if (e.target.closest('#smi-done-close')) return close();
			if (e.target.closest('#smi-restart')) return resetForm();
		});
		body.addEventListener('input', function (e) {
			var el = e.target;
			if (el.hasAttribute('data-other')) {
				state.o[el.getAttribute('data-other')] = el.value;
			} else if (el.hasAttribute('data-field')) {
				var id = el.getAttribute('data-field');
				state.a[id] = el.type === 'checkbox' ? el.checked : el.value;
				clearErr(id);
			} else {
				return;
			}
			renderSide(flow());
			save();
		});
		$('smi-steps').addEventListener('click', function (e) {
			var b = e.target.closest('[data-jump]');
			if (!b || sending) return;
			state.step = parseInt(b.getAttribute('data-jump'), 10);
			render();
			focusTitle();
		});
		$('smi-clear').addEventListener('click', function () {
			var open = $('smi-confirm').hidden;
			$('smi-confirm').hidden = !open;
			$('smi-clear').setAttribute('aria-expanded', String(open));
			if (open) $('smi-no').focus();
		});
		$('smi-no').addEventListener('click', function () { hideConfirm(); $('smi-clear').focus(); });
		$('smi-yes').addEventListener('click', resetForm);
		$('smi-close').addEventListener('click', close);
		root.addEventListener('mousedown', function (e) {
			if (e.target === root) close();
			else if (!$('smi-confirm').hidden && !e.target.closest('#smi-confirm, #smi-clear')) hideConfirm();
		});
		document.addEventListener('keydown', function (e) {
			if (root.hidden) return;
			if (e.key === 'Escape') {
				if (!$('smi-confirm').hidden) { hideConfirm(); $('smi-clear').focus(); return; }
				close();
				return;
			}
			if (e.key === 'Tab') {
				var modal = root.firstChild;
				var items = Array.prototype.filter.call(modal.querySelectorAll('button, a[href], input, textarea, [tabindex]:not([tabindex="-1"])'), function (el) {
					return !el.disabled && el.offsetParent !== null && el.style.visibility !== 'hidden' && el.getAttribute('tabindex') !== '-1';
				});
				if (!items.length) return;
				var first = items[0], last = items[items.length - 1];
				if (e.shiftKey && (document.activeElement === first || !modal.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
				else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			}
		});
	}

	/* ---------- Open / close ---------- */
	var prevOverflow = '', prevPadding = '';
	/**
	 * The page behind stops scrolling. The scrollbar's width is kept as padding, so the page (and a sticky
	 * header like Ohio's) doesn't jump sideways when the scrollbar disappears.
	 */
	function lockPage() {
		var html = document.documentElement;
		var bar = window.innerWidth - html.clientWidth;
		prevOverflow = html.style.overflow;
		prevPadding = html.style.paddingRight;
		html.style.overflow = 'hidden';
		if (bar > 0) html.style.paddingRight = bar + 'px';
	}
	function unlockPage() {
		var html = document.documentElement;
		html.style.overflow = prevOverflow;
		html.style.paddingRight = prevPadding;
	}
	function open(opts) {
		opts = opts || {};
		if (!root) build();
		lang = opts.lang && cfg.langs.indexOf(opts.lang) > -1 ? opts.lang : cfg.lang;
		applyTexts();
		if (opts.service && !state.done && chooseService(opts.service) && state.step === 0) state.step = 1;
		lastFocus = document.activeElement;
		if (root.hidden) lockPage();
		root.hidden = false;
		syncFloat();
		render();
		focusTitle();
		if (!token || Date.now() - tokenAt > 2 * 3600 * 1000) fetchToken().catch(function () { token = null; });
	}
	function close() {
		if (!root || root.hidden) return;
		root.hidden = true;
		unlockPage();
		syncFloat();
		if (state.done) {
			// A finished request starts fresh next time.
			state = fresh();
			save();
		}
		if (lastFocus && lastFocus.focus) lastFocus.focus();
	}

	/** "-eshop", "-en", "-eshop-en": each part is a service id or a language code. */
	function parseSuffix(suffix) {
		var out = {};
		(suffix || '').split('-').forEach(function (part) {
			if (!part) return;
			if (cfg.langs.indexOf(part) > -1) out.lang = part;
			else if (svc(part)) out.service = part;
		});
		// Service ids may contain dashes; try the whole suffix too.
		if (!out.service && suffix && svc(suffix)) out.service = suffix;
		return out;
	}

	/**
	 * Triggers: a link to #smartup-inquiry[-…], or any element with the class js-inquiry. WordPress puts a menu item's
	 * CSS classes on its <li>, so js-inquiry-eshop / js-inquiry-en on a menu item work too.
	 *
	 * Listening in the capture phase means the form reacts before the theme: Ohio's "One Page Menu" smooth scroll and its
	 * page transitions would otherwise try to scroll to (or fade out towards) an anchor that isn't there. The click is
	 * kept from them, except inside Ohio's mobile menu (it still needs the click to close itself) and SmartBanner
	 * (a banner button pointing here must still count as "seen").
	 */
	document.addEventListener('click', function (e) {
		if (e.button > 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || !e.target.closest) return;
		if (root && root.contains(e.target)) return;
		var el = e.target.closest('a[href*="#smartup-inquiry"], .js-inquiry, [class*="js-inquiry-"]');
		if (!el) return;
		var opts = {};
		var href = el.getAttribute('href') || '';
		var m = HASH.exec(href);
		var byClass = Array.prototype.filter.call(el.classList, function (c) { return c === 'js-inquiry' || c.indexOf('js-inquiry-') === 0; });
		if (m) {
			opts = parseSuffix(m[1]);
		} else if (!byClass.length) {
			return; // e.g. "#smartup-inquiry?x": not ours
		}
		byClass.forEach(function (c) {
			if (c.indexOf('js-inquiry-') === 0) {
				var more = parseSuffix(c.slice('js-inquiry-'.length));
				if (more.service) opts.service = more.service;
				if (more.lang) opts.lang = more.lang;
			}
		});
		if (el.getAttribute('data-service')) opts.service = el.getAttribute('data-service');
		if (el.getAttribute('data-lang')) opts.lang = el.getAttribute('data-lang');
		e.preventDefault();
		if (!e.target.closest('.menu-slide-in-overlay, .slide-in-overlay, #smartbanner')) e.stopPropagation();
		open(opts);
	}, true);

	// A link to #smartup-inquiry… from another page opens the form on arrival.
	function fromHash() {
		var m = HASH.exec(window.location.hash);
		if (!m) return;
		open(parseSuffix(m[1]));
		try { history.replaceState(null, '', window.location.pathname + window.location.search); } catch (e) {}
	}
	window.addEventListener('hashchange', fromHash);
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fromHash);
	else fromHash();

	/* ---------- Floating button ---------- */
	/**
	 * Shown once the visitor has scrolled a little and none of the page's own form buttons is on screen, e.g. after
	 * the hero's "Start a project" has scrolled away; it steps aside again when one of them comes back into view, so
	 * the two are never seen together. Buttons that are always there (header, menus, anything fixed or sticky, the
	 * SmartBanner) don't count.
	 */
	var fab = null, onScreen = [];
	var TRIGGERS = 'a[href*="#smartup-inquiry"], .js-inquiry, [class*="js-inquiry-"]';
	var ALWAYS_THERE = '#smartinquiry, header, nav, [role="navigation"], #masthead, .menu-slide-in-overlay, .slide-in-overlay, #smartbanner';

	function stuck(el) {
		for (var n = el; n && n !== document.body && n !== document.documentElement; n = n.parentElement) {
			var p = window.getComputedStyle(n).position;
			if (p === 'fixed' || p === 'sticky') return true;
		}
		return false;
	}
	function syncFloat() {
		if (!fab) return;
		var y = window.pageYOffset || document.documentElement.scrollTop || 0;
		fab.classList.toggle('is-shown', y > window.innerHeight * 0.4 && !onScreen.length && (!root || root.hidden));
	}
	function buildFloat() {
		var f = cfg.float;
		var label = tr(f.label) || 'Start a project';
		fab = document.createElement('button');
		fab.type = 'button';
		fab.id = 'smartinquiry-float';
		fab.className = 'smi-float--' + (f.position === 'right' ? 'right' : 'left') + ' smi-float--m-' + f.mobile;
		fab.textContent = label;
		if (hexToRgb(f.bg)) fab.style.setProperty('--smi-float-bg', f.bg);
		if (hexToRgb(f.color)) fab.style.setProperty('--smi-float-text', f.color);
		host().appendChild(fab);
		fab.addEventListener('click', function () { open(); });

		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					var i = onScreen.indexOf(en.target);
					if (en.isIntersecting && i < 0) onScreen.push(en.target);
					else if (!en.isIntersecting && i > -1) onScreen.splice(i, 1);
				});
				syncFloat();
			});
			var watch = function () {
				Array.prototype.forEach.call(document.querySelectorAll(TRIGGERS), function (el) {
					if (el !== fab && !el.closest(ALWAYS_THERE) && !stuck(el)) io.observe(el);
				});
			};
			watch();
			// Page builders and lazy sections may add buttons after the scripts ran; observing twice is harmless.
			window.addEventListener('load', watch);
		}
		window.addEventListener('scroll', syncFloat, { passive: true });
		window.addEventListener('resize', syncFloat, { passive: true });
		syncFloat();
	}
	if (cfg.float) buildFloat();

	window.SmartInquiry = { open: open, close: close };
})();
