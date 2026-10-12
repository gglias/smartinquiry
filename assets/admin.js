/*
 * SmartInquiry admin screen. Data comes from window.smiAdmin (settings, texts) and admin-ajax (requests).
 * The settings screens edit one working copy ("draft"); "Save" sends it whole and the server cleans it.
 */
(function () {
	'use strict';

	var B = window.smiAdmin;
	var mount = document.getElementById('smi-admin');
	if (!B || !mount) return;

	var I = B.i18n || {};
	function t(k) { return I[k] != null ? I[k] : k; }
	/**
	 * t() with %s / %d (in order) or %1$s / %2$d (by position, so translations can reorder them) filled in.
	 * When the first value is 1 and a "<key>1" text exists, that singular form is used.
	 */
	function tf(k) {
		var args = Array.prototype.slice.call(arguments, 1), i = 0;
		if (args[0] === 1 && I[k + '1'] != null) k = k + '1';
		return t(k).replace(/%(?:(\d)\$)?[sd]/g, function (m, pos) {
			var v = pos ? args[+pos - 1] : args[i++];
			return v != null ? v : '';
		});
	}

	/* ---------- Icons ---------- */
	var P = {
		inbox: '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5.5 5h13L21 13v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5z"/>',
		form: '<rect x="4" y="3" width="16" height="18" rx="2.5"/><path d="M8 8h8M8 12h8M8 16h5"/>',
		text: '<path d="M5 6V4.5h14V6M12 4.5v15M9 19.5h6"/>',
		bell: '<path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
		file: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
		brush: '<path d="M14.5 4.5l5 5-8 8H6.5v-5z"/><path d="M12 7l5 5"/>',
		shield: '<path d="M12 3l7 3v5c0 5-3.2 8.3-7 10-3.8-1.7-7-5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
		search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		filter: '<path d="M4 6h16M7 12h10M10 18h4"/>',
		download: '<path d="M12 4v11M7.5 10.5L12 15l4.5-4.5M5 19h14"/>',
		trash: '<path d="M4 7h16M9 7V4.5h6V7M6.5 7l1 13h9l1-13"/>',
		dots: '<circle cx="12" cy="5.5" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="12" cy="18.5" r="1.2"/>',
		chev: '<path d="M6 9l6 6 6-6"/>',
		up: '<path d="M6 15l6-6 6 6"/>',
		left: '<path d="M15 5l-7 7 7 7"/>',
		right: '<path d="M9 5l7 7-7 7"/>',
		first: '<path d="M11 5l-7 7 7 7M19 5l-7 7 7 7"/>',
		last: '<path d="M13 5l7 7-7 7M5 5l7 7-7 7"/>',
		x: '<path d="M6 6l12 12M18 6L6 18"/>',
		check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		grip: '<circle cx="9" cy="6" r="1.1"/><circle cx="15" cy="6" r="1.1"/><circle cx="9" cy="12" r="1.1"/><circle cx="15" cy="12" r="1.1"/><circle cx="9" cy="18" r="1.1"/><circle cx="15" cy="18" r="1.1"/>',
		copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
		mail: '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M4 7l8 6 8-6"/>',
		phonecall: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
		building: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/>',
		upload: '<path d="M12 16V5M7.5 9.5L12 5l4.5 4.5M5 19h14"/>',
		calendar: '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
		trend: '<path d="M4 17l5.5-5.5 4 4L20 9"/><path d="M15 9h5v5"/>',
		warn: '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
		ok: '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
		info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
		globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		cart: '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/>',
		phone: '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
		app: '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 9h18M8 13h4M8 16h7"/>',
		spark: '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 16l.7 2.3L22 19l-2.3.7L19 22l-.7-2.3L16 19l2.3-.7z"/>',
		chat: '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/>',
		camera: '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
		megaphone: '<path d="M4 10v4h3l9 5V5L7 10z"/><path d="M19 9.5a3 3 0 0 1 0 5"/>',
		code: '<path d="M8 7l-5 5 5 5M16 7l5 5-5 5M13.5 5l-3 14"/>',
		layers: '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
		belloff: '<path d="M6 16V11a6 6 0 0 1 9.5-4.9M18 11v5l1.5 2H8"/><path d="M10 20.5a2 2 0 0 0 4 0M4 4l16 16"/>',
		users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
		key: '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l2 2M18.5 4.5l2 2"/>',
		logout: '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M9 16l-4-4 4-4M5 12h11"/>',
		link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>'
	};
	function icon(n) {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (P[n] || P.form) + '</svg>';
	}
	var EMBLEM = '<svg viewBox="0 0 25 31" fill="currentColor" aria-hidden="true"><rect x="6.26" width="18.74" height="6.21"/><rect y="6.21" width="6.26" height="6.21"/><rect x="6.26" y="12.37" width="12.48" height="6.21"/><rect x="18.74" y="18.58" width="6.26" height="6.21"/><rect y="24.79" width="18.74" height="6.21"/></svg>';

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}
	function clone(x) { return JSON.parse(JSON.stringify(x)); }
	function $(id) { return document.getElementById(id); }

	var LANGS = B.langs;                          // [{code, name}]
	var UI = B.uiLang;
	function tr(map, lang) {
		if (!map || typeof map !== 'object') return map ? String(map) : '';
		var order = [lang || UI, B.siteLang, 'en', 'el'];
		for (var i = 0; i < order.length; i++) if (map[order[i]]) return map[order[i]];
		for (var k in map) if (map[k]) return map[k];
		return '';
	}

	/* ---------- Toast ---------- */
	var toastEl = document.createElement('div');
	toastEl.className = 'smi-toast';
	toastEl.setAttribute('role', 'status');
	toastEl.setAttribute('aria-live', 'polite');
	document.body.appendChild(toastEl);
	var toastTimer;
	function toast(msg, isError) {
		toastEl.innerHTML = icon(isError ? 'warn' : 'check') + '<span>' + esc(msg) + '</span>';
		toastEl.classList.toggle('is-error', !!isError);
		toastEl.classList.add('is-show');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.classList.remove('is-show'); }, Math.max(2600, msg.length * 55));
	}

	/* ---------- AJAX ---------- */
	function ajax(action, data) {
		var fd = new FormData();
		fd.set('action', action);
		fd.set('nonce', B.nonce);
		Object.keys(data || {}).forEach(function (k) { fd.set(k, data[k]); });
		return fetch(B.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { return { success: false }; }); })
			.then(function (res) {
				if (!res || !res.success) {
					var e = new Error((res && res.data && res.data.message) || t('errGeneric'));
					e.data = res && res.data;
					throw e;
				}
				return res.data;
			});
	}

	/* ---------- State ---------- */
	var S = clone(B.settings);          // draft of the settings
	var savedJson = JSON.stringify(S);
	var logoUrl = B.logoUrl;
	function dirty() { return JSON.stringify(S) !== savedJson; }

	var STATUSES = ['new', 'contact', 'offer', 'won', 'lost'];
	function statusLabel(s) { return t('st_' + s); }

	var ui = {
		view: 'requests', tab: '', q: '', svc: '', lang: '', page: 1, sel: {}, filterOpen: false,
		editSvc: S.form.services.length ? S.form.services[0].id : '__common',
		editLang: LANGS.some(function (l) { return l.code === UI; }) ? UI : LANGS[0].code,
		openQ: null, drawer: null
	};
	var list = { rows: [], total: 0, counts: null, loading: false, req: 0 };

	// The settings screens only for people allowed to change settings; Access only for administrators.
	var CAN = B.can || { settings: true, access: false };
	var VIEWS = [{ id: 'requests', icon: 'inbox' }].concat(CAN.settings ? [
		{ id: 'form', icon: 'form' }, { id: 'texts', icon: 'text' },
		{ id: 'notify', icon: 'bell' }, { id: 'pdf', icon: 'file' }, { id: 'look', icon: 'brush' }, { id: 'privacy', icon: 'shield' }
	] : []).concat(CAN.access ? [{ id: 'access', icon: 'users' }] : []);
	var myRole = B.only ? (B.roleNames[B.myRole] || '') : t('administrator');

	/* ---------- Frame ---------- */
	mount.innerHTML =
		'<div class="smi-app">' +
			'<aside class="smi-nav">' +
				'<div class="smi-nav__brand"><span class="smi-brand"><span class="smi-brand__mark">' + EMBLEM + '</span>SmartInquiry</span>' +
					'<span class="smi-uilang" role="group" aria-label="' + esc(t('screenLang')) + '"><button type="button" data-ui="en" aria-pressed="' + (UI === 'en') + '">EN</button><button type="button" data-ui="el" aria-pressed="' + (UI === 'el') + '">ΕΛ</button></span></div>' +
				'<ul class="smi-nav__list" id="smi-nav"></ul>' +
				'<div class="smi-nav__svc"><div class="smi-nav__label"><span>' + esc(t('byService')) + '</span><button type="button" id="smi-svc-all" hidden>' + esc(t('all')) + '</button></div><ul class="smi-nav__list" id="smi-svcnav"></ul></div>' +
				'<div class="smi-nav__foot"><span class="smi-avatar smi-avatar--brand">' + EMBLEM + '</span><div><strong>' + esc(B.user || 'Smartup') + '</strong><small>' + esc(myRole) + '</small></div></div>' +
				'<div class="smi-nav__me"><a href="' + esc(B.profileUrl) + '">' + icon('key') + '<span>' + esc(t('myPassword')) + '</span></a><a href="' + esc(B.logoutUrl) + '">' + icon('logout') + '<span>' + esc(t('logOut')) + '</span></a></div>' +
			'</aside>' +
			'<main class="smi-main" id="smi-main"></main>' +
		'</div>';
	var main = $('smi-main');
	// The screen language may differ from WordPress's (uppercase Greek then drops its accents correctly).
	mount.setAttribute('lang', UI);

	var scrim = document.createElement('div');
	scrim.className = 'smi-scrim';
	scrim.hidden = true;
	var drawer = document.createElement('aside');
	drawer.className = 'smi-drawer';
	drawer.hidden = true;
	drawer.setAttribute('role', 'dialog');
	drawer.setAttribute('aria-modal', 'true');
	drawer.setAttribute('aria-labelledby', 'smi-d-title');
	drawer.setAttribute('lang', UI);
	document.body.appendChild(scrim);
	document.body.appendChild(drawer);

	function renderNav() {
		var n = list.counts ? list.counts.cards.new : 0;
		$('smi-nav').innerHTML = VIEWS.map(function (v) {
			var extra = v.id === 'requests' && n ? '<span class="smi-nav__badge num">' + n + '</span>' : '';
			return '<li><button type="button" class="smi-nav__item" data-view="' + v.id + '"' + (ui.view === v.id ? ' aria-current="page"' : '') + '>' + icon(v.icon) + '<span class="t">' + esc(t('view_' + v.id)) + '</span>' + extra + '</button></li>';
		}).join('');
		var counts = list.counts ? list.counts.service : {};
		$('smi-svcnav').innerHTML = S.form.services.map(function (s) {
			var c = counts[s.id] || 0;
			return '<li><button type="button" class="smi-nav__item" data-svcnav="' + esc(s.id) + '"' + (ui.view === 'requests' && ui.svc === s.id ? ' aria-current="page"' : '') + '>' + icon(s.icon) + '<span class="t">' + esc(tr(s.label)) + '</span><span class="smi-nav__count num">' + c + '</span></button></li>';
		}).join('');
		$('smi-svc-all').hidden = !(ui.view === 'requests' && ui.svc);
	}

	function head(title, sub, actions) {
		return '<div class="smi-head"><div><h2>' + esc(title) + '</h2>' + (sub ? '<p>' + esc(sub) + '</p>' : '') + '</div><div class="smi-head__actions">' + (actions || '') + '</div></div>';
	}
	function saveActions() {
		var d = dirty();
		return '<span class="smi-savestate" id="smi-savestate" data-state="' + (d ? 'dirty' : 'saved') + '">' + esc(t(d ? 'unsaved' : 'allSaved')) + '</span>' +
			'<button type="button" class="smi-btn smi-btn--primary" id="smi-save">' + esc(t('save')) + '</button>';
	}
	function updateSaveState() {
		var el = $('smi-savestate');
		if (!el) return;
		var d = dirty();
		el.setAttribute('data-state', d ? 'dirty' : 'saved');
		el.textContent = t(d ? 'unsaved' : 'allSaved');
	}

	function render() {
		renderNav();
		var v = ui.view;
		if (v === 'requests') return renderRequests();
		main.innerHTML = v === 'form' ? viewForm() : v === 'texts' ? viewTexts() : v === 'notify' ? viewNotify() : v === 'pdf' ? viewPdf() : v === 'look' ? viewLook() : v === 'access' ? viewAccess() : viewPrivacy();
	}

	/* ====================================================================== */
	/* Requests                                                                */
	/* ====================================================================== */

	function renderRequests() {
		main.innerHTML = head(t('view_requests'), t('requestsSub'), deskButton() + '<button type="button" class="smi-btn" id="smi-export-all">' + icon('download') + '<span>' + esc(t('exportCsv')) + '</span></button>') +
			'<div class="smi-stats" id="smi-stats"></div>' +
			'<h3 class="smi-section-title">' + esc(t('allRequests')) + '</h3>' +
			'<div class="smi-toolbar"><div class="smi-tabs" role="tablist" id="smi-tabs" aria-label="' + esc(t('status')) + '"></div>' +
				'<div class="smi-toolbar__right"><span id="smi-chips"></span>' +
					'<label class="smi-search">' + icon('search') + '<input class="smi-input" type="search" id="smi-q" placeholder="' + esc(t('searchPh')) + '" value="' + esc(ui.q) + '" aria-label="' + esc(t('search')) + '"></label>' +
					'<div class="smi-filterwrap"><button type="button" class="smi-btn" id="smi-filter" aria-expanded="false">' + icon('filter') + '<span>' + esc(t('filters')) + '</span></button><div class="smi-pop" id="smi-pop" hidden></div></div>' +
				'</div></div>' +
			'<div id="smi-bulk"></div>' +
			'<div class="smi-tablewrap" id="smi-tablewrap"></div>' +
			'<div class="smi-pager" id="smi-pager"></div>';
		paintRequests();
		loadList();
	}

	/* ---------- Desktop notifications (per person; while this screen is open in a tab, even in the background) ---------- */
	var desk = {
		on: !!(B.desk && B.desk.on),
		sound: !(B.desk && B.desk.sound === false),
		tone: (B.desk && B.desk.tone) || 'confirm',
		buffer: null,
		open: false, latest: 0, ready: false, timer: null, unseen: 0, title: document.title, audio: null, icon: ''
	};
	/** 'granted' | 'default' | 'denied' | 'insecure' (http) | 'unsupported'. */
	function deskState() {
		if (!('Notification' in window)) return 'unsupported';
		if (!window.isSecureContext) return 'insecure';
		return Notification.permission;
	}
	function deskButton() {
		var live = desk.on && deskState() === 'granted';
		return '<div class="smi-desk"><button type="button" class="smi-btn" id="smi-desk-btn" aria-expanded="' + desk.open + '" aria-controls="smi-desk-pop">' + icon(live ? 'bell' : 'belloff') +
			'<span>' + esc(t('deskBtn')) + '</span><span class="smi-desk__dot' + (live ? ' is-on' : '') + '" aria-hidden="true"></span></button>' +
			'<div class="smi-desk__pop smi-pop" id="smi-desk-pop"' + (desk.open ? '' : ' hidden') + '>' + deskPop() + '</div></div>';
	}
	function deskPop() {
		var s = deskState();
		var note = s === 'unsupported' ? t('deskUnsupported') : s === 'insecure' ? t('deskInsecure') : s === 'denied' ? t('deskBlocked') : (desk.on && s !== 'granted') ? t('deskNeedsOk') : '';
		return '<label class="smi-toggle-line"><span class="smi-switch"><input type="checkbox" id="smi-desk-on"' + (desk.on && s === 'granted' ? ' checked' : '') + (s === 'unsupported' || s === 'insecure' ? ' disabled' : '') + '><span></span></span><span><strong>' + esc(t('deskOn')) + '</strong><small>' + esc(t('deskOnSub')) + '</small></span></label>' +
			'<label class="smi-toggle-line"><span class="smi-switch"><input type="checkbox" id="smi-desk-sound"' + (desk.sound ? ' checked' : '') + '><span></span></span><span><strong>' + esc(t('deskSound')) + '</strong><small>' + esc(t('deskSoundSub')) + '</small></span></label>' +
			(desk.sound ? '<div class="smi-desk__tones" role="radiogroup" aria-label="' + esc(t('deskTone')) + '">' + TONES.map(function (k) {
				return '<div class="smi-desk__tone"><label><input type="radio" name="smi-tone" value="' + k + '"' + (desk.tone === k ? ' checked' : '') + '><span>' + esc(t('tone_' + k)) + '</span></label>' +
					'<button type="button" class="smi-desk__play" data-tone-play="' + k + '" aria-label="' + esc(t('deskPlay') + ': ' + t('tone_' + k)) + '">▶</button></div>';
			}).join('') + '</div>' : '') +
			(note ? '<p class="smi-desk__note">' + icon('warn') + '<span>' + esc(note) + '</span></p>' : '') +
			(desk.on && s === 'granted' ? '<button type="button" class="smi-btn smi-btn--sm" id="smi-desk-test">' + esc(t('deskTest')) + '</button>' : '') +
			'<p class="smi-desk__tip">' + esc(t('deskTip')) + '</p>';
	}
	function deskPaint() {
		var w = main.querySelector('.smi-desk');
		if (w) w.outerHTML = deskButton();
	}
	function deskSave() {
		return ajax('smi_desk', { on: desk.on ? '1' : '0', sound: desk.sound ? '1' : '0', tone: desk.tone }).catch(function (e) { toast(e.message, true); });
	}
	/** The Smartup "S" on the accent colour, drawn once, for the notification's icon. */
	function deskIcon() {
		if (desk.icon) return desk.icon;
		try {
			var c = document.createElement('canvas');
			c.width = c.height = 128;
			var x = c.getContext('2d');
			x.fillStyle = (S.look && S.look.accent) || '#46e9d4';
			x.beginPath();
			x.arc(64, 64, 64, 0, Math.PI * 2);
			x.fill();
			x.fillStyle = '#042727';
			var k = 2.3, ox = 64 - 12.5 * k, oy = 64 - 15.5 * k;
			[[6.26, 0, 18.74, 6.21], [0, 6.21, 6.26, 6.21], [6.26, 12.37, 12.48, 6.21], [18.74, 18.58, 6.26, 6.21], [0, 24.79, 18.74, 6.21]].forEach(function (r) {
				x.fillRect(ox + r[0] * k, oy + r[1] * k, r[2] * k, r[3] * k);
			});
			desk.icon = c.toDataURL('image/png');
		} catch (e) {}
		return desk.icon;
	}
	/*
	 * Sounds. "confirm" (default) is Kenney's confirmation_002 from "Interface Sounds" (CC0, assets/sounds), "chime" is
	 * drawn on the spot. Both go through one AudioContext: browsers let a page play sound once it has been clicked, and
	 * that permission then holds while the tab sits in the background.
	 */
	var TONES = ['confirm', 'chime'];
	function deskAudio() {
		var Ctx = window.AudioContext || window.webkitAudioContext;
		if (!Ctx) return null;
		desk.audio = desk.audio || new Ctx();
		if (desk.audio.state === 'suspended') desk.audio.resume();
		return desk.audio;
	}
	['pointerdown', 'keydown'].forEach(function (type) {
		document.addEventListener(type, function unlock() {
			if (desk.on && desk.sound) deskAudio();
			document.removeEventListener(type, unlock, true);
		}, true);
	});
	function deskSound(tone) {
		try {
			var a = deskAudio();
			if (!a) return;
			if ((tone || desk.tone) !== 'confirm') return deskChime(a);
			desk.buffer = desk.buffer || fetch(B.soundsUrl + 'confirmation.ogg', { credentials: 'same-origin' })
				.then(function (r) { return r.arrayBuffer(); })
				.then(function (buf) { return new Promise(function (ok, no) { a.decodeAudioData(buf, ok, no); }); });
			desk.buffer.then(function (buf) {
				var src = a.createBufferSource(), g = a.createGain();
				g.gain.value = 0.8;
				src.buffer = buf;
				src.connect(g);
				g.connect(a.destination);
				src.start();
			}, function () {
				// Read from another address (a CDN) or not decodable: an <audio> plays it anyway, else the chime.
				desk.buffer = null;
				try {
					var el = new Audio(B.soundsUrl + 'confirmation.ogg');
					el.volume = 0.8;
					var p = el.play();
					if (p && p.catch) p.catch(function () { deskChime(a); });
				} catch (e) {
					deskChime(a);
				}
			});
		} catch (e) {}
	}
	/** A short two-note chime, made on the spot (no sound file). */
	function deskChime(a) {
		try {
			a = a || deskAudio();
			if (!a) return;
			[[880, 0], [1318.5, 0.13]].forEach(function (n) {
				var o = a.createOscillator(), g = a.createGain(), t0 = a.currentTime + n[1];
				o.type = 'sine';
				o.frequency.value = n[0];
				g.gain.setValueAtTime(0.0001, t0);
				g.gain.exponentialRampToValueAtTime(0.16, t0 + 0.02);
				g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.4);
				o.connect(g);
				g.connect(a.destination);
				o.start(t0);
				o.stop(t0 + 0.45);
			});
		} catch (e) {}
	}
	function deskNotify(item) {
		if (deskState() !== 'granted') return;
		try {
			var n = new Notification(tf('deskTitle', item.ref), {
				body: [item.svcName, item.name, item.company].filter(Boolean).join(' · '),
				icon: deskIcon(),
				tag: 'smartinquiry-' + item.id, // several open tabs show it once
				lang: UI
			});
			n.onclick = function () {
				window.focus();
				n.close();
				if (!item.id) return;
				if (ui.view !== 'requests') { ui.view = 'requests'; render(); }
				openDrawer(item.id);
			};
		} catch (e) {}
	}
	function deskTitle() {
		document.title = (desk.unseen ? '(' + desk.unseen + ') ' : '') + desk.title;
	}
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden && desk.unseen) { desk.unseen = 0; deskTitle(); }
	});
	/** Every 30 seconds (browsers slow background tabs to about once a minute, which is fine for this). */
	function deskPoll() {
		ajax('smi_poll', { since: desk.latest, init: desk.ready ? '0' : '1' }).then(function (d) {
			var fresh = desk.ready ? (d.items || []) : [];
			desk.ready = true;
			desk.latest = Math.max(desk.latest, d.latest || 0);
			if (!fresh.length) return;
			if (desk.on) {
				fresh.slice(-3).forEach(deskNotify);
				if (desk.sound) deskSound();
			}
			if (document.hidden) { desk.unseen += fresh.length; deskTitle(); }
			loadList();
		}).catch(function () {}).then(function () {
			clearTimeout(desk.timer);
			desk.timer = setTimeout(deskPoll, 30000);
		});
	}

	function loadList() {
		var my = ++list.req;
		list.loading = true;
		var w = $('smi-tablewrap');
		if (w) w.classList.add('is-loading');
		return ajax('smi_list', { status: ui.tab, service: ui.svc, lang: ui.lang, q: ui.q, page: ui.page }).then(function (d) {
			if (my !== list.req) return;
			list.rows = d.rows;
			list.total = d.total;
			list.counts = d.counts;
			list.loading = false;
			var pages = Math.max(1, Math.ceil(d.total / B.perPage));
			if (ui.page > pages) { ui.page = pages; return loadList(); }
			renderNav();
			paintRequests();
		}).catch(function (e) {
			list.loading = false;
			if (w) w.classList.remove('is-loading');
			toast(e.message, true);
		});
	}

	function stat(ic, label, value, accent) {
		return '<div class="smi-stat' + (accent ? ' smi-stat--accent' : '') + '"><span class="smi-stat__icon">' + icon(ic) + '</span><div><span class="smi-stat__label">' + esc(label) + '</span><span class="smi-stat__value num">' + value + '</span></div></div>';
	}

	function selectedIds() { return Object.keys(ui.sel).filter(function (k) { return ui.sel[k]; }); }

	function paintRequests() {
		if (ui.view !== 'requests' || !$('smi-stats')) return;
		var c = list.counts;
		if (c) {
			var decided = c.cards.won + c.cards.lost;
			var rate = decided ? Math.round(c.cards.won / decided * 100) : 0;
			$('smi-stats').innerHTML =
				stat('inbox', t('cardNew'), c.cards.new, true) +
				stat('calendar', t('cardWeek'), c.cards.week) +
				stat('trend', t('cardMonth'), c.cards.month) +
				stat('check', t('cardWon'), c.cards.won + (decided ? ' <small>(' + rate + '%)</small>' : ''));
			var all = STATUSES.reduce(function (n, s) { return n + c.status[s]; }, 0);
			$('smi-tabs').innerHTML = tab('', t('all'), all) + STATUSES.map(function (s) { return tab(s, statusLabel(s), c.status[s]); }).join('');
		}
		var chips = '';
		if (ui.svc) {
			var s = svcById(ui.svc);
			chips += '<span class="smi-chipf">' + esc(t('service')) + ': ' + esc(s ? tr(s.label) : ui.svc) + '<button type="button" data-clear="svc" aria-label="' + esc(t('removeFilter')) + '">' + icon('x') + '</button></span> ';
		}
		if (ui.lang) chips += '<span class="smi-chipf">' + esc(t('formLang')) + ': ' + esc(langName(ui.lang)) + '<button type="button" data-clear="lang" aria-label="' + esc(t('removeFilter')) + '">' + icon('x') + '</button></span>';
		$('smi-chips').innerHTML = chips;

		paintBulk();

		var rows = list.rows;
		var html = '<table class="smi-t"><thead><tr>' +
			'<th class="w-check"><input type="checkbox" class="smi-check" id="smi-sel-all" aria-label="' + esc(t('selectAll')) + '"' + (rows.length && rows.every(function (r) { return ui.sel[r.id]; }) ? ' checked' : '') + '></th>' +
			'<th>' + esc(t('customer')) + '</th><th>' + esc(t('serviceBudget')) + '</th><th>' + esc(t('status')) + '</th><th>' + esc(t('date')) + '</th><th><span class="screen-reader-text">' + esc(t('actions')) + '</span></th></tr></thead><tbody>';
		if (!rows.length) {
			var any = ui.tab || ui.svc || ui.lang || ui.q;
			html += '<tr><td colspan="6"><div class="smi-empty">' + (list.loading && !list.counts ? esc(t('loading')) : '<strong>' + esc(t(any ? 'emptyFiltered' : 'emptyAll')) + '</strong>' + esc(t(any ? 'emptyFilteredSub' : 'emptyAllSub'))) + '</div></td></tr>';
		}
		rows.forEach(function (r) {
			var s = svcById(r.service);
			html += '<tr tabindex="0" data-row="' + r.id + '"' + (ui.sel[r.id] ? ' class="is-selected"' : '') + '>' +
				'<td class="w-check"><input type="checkbox" class="smi-check" data-sel="' + r.id + '" aria-label="' + esc(tf('selectX', r.name)) + '"' + (ui.sel[r.id] ? ' checked' : '') + '></td>' +
				'<td><div class="smi-who"><span class="smi-avatar">' + esc(initials(r.name)) + '</span><div><div class="smi-who__name">' + (r.isRead ? '' : '<span class="smi-unread" title="' + esc(t('unread')) + '"></span>') + esc(r.name) + '<span class="smi-lang" title="' + esc(t('formLang')) + '">' + esc(r.lang.toUpperCase()) + '</span></div><div class="smi-who__meta">' + esc(r.company || r.email) + ' · <span class="num">' + esc(r.ref) + '</span></div></div></div></td>' +
				'<td><div class="smi-svccell"><span class="smi-svcicon">' + icon(s ? s.icon : 'form') + '</span><div><div>' + esc(r.svcName) + '</div><div class="smi-who__meta num">' + esc(r.budget) + '</div></div></div></td>' +
				'<td><span class="smi-pill smi-pill--' + r.status + '">' + esc(statusLabel(r.status)) + '</span></td>' +
				'<td class="smi-muted num" style="white-space:nowrap">' + esc(fmtDate(r.date)) + '</td>' +
				'<td style="width:48px"><button type="button" class="smi-rowmenu" data-open="' + r.id + '" aria-label="' + esc(tf('openX', r.name)) + '">' + icon('dots') + '</button></td></tr>';
		});
		html += '</tbody></table>';
		var w = $('smi-tablewrap');
		w.innerHTML = html;
		w.classList.toggle('is-loading', list.loading);

		var pages = Math.max(1, Math.ceil(list.total / B.perPage));
		var p = '<span>' + tf('pageOf', '<strong class="num">' + ui.page + '</strong>', '<span class="num">' + pages + '</span>') + ' · ' + esc(tf('nRequests', list.total)) + '</span>';
		if (pages > 1) {
			p += '<div class="smi-pager__pages">' +
				pbtn(1, 'first', t('firstPage'), ui.page === 1) + pbtn(ui.page - 1, 'left', t('prevPage'), ui.page === 1);
			pageWindow(ui.page, pages).forEach(function (n) {
				p += n === '…' ? '<span class="smi-muted">…</span>' : '<button type="button" class="pg num" data-page="' + n + '"' + (n === ui.page ? ' aria-current="page"' : '') + '>' + n + '</button>';
			});
			p += pbtn(ui.page + 1, 'right', t('nextPage'), ui.page === pages) + pbtn(pages, 'last', t('lastPage'), ui.page === pages) + '</div>';
		}
		$('smi-pager').innerHTML = p;
	}
	/** The bar for selected rows, repainted alone so ticking a box never rebuilds the table (focus stays put). */
	function paintBulk() {
		var sel = selectedIds();
		var box = $('smi-bulk');
		if (!box) return;
		var bar = box.querySelector('.smi-bulk');
		if (sel.length && bar) {
			bar.querySelector('span').textContent = tf('nSelected', sel.length);
			return;
		}
		box.innerHTML = sel.length ?
			'<div class="smi-bulk"><span>' + esc(tf('nSelected', sel.length)) + '</span><span class="smi-bulk__spacer"></span>' +
				'<select class="smi-select" id="smi-bulk-status" aria-label="' + esc(t('changeStatus')) + '"><option value="">' + esc(t('changeStatus')) + '…</option>' + STATUSES.map(function (s) { return '<option value="' + s + '">' + esc(statusLabel(s)) + '</option>'; }).join('') + '</select>' +
				'<button type="button" class="smi-btn smi-btn--sm" id="smi-bulk-export">' + icon('download') + '<span>' + esc(t('export')) + '</span></button>' +
				'<button type="button" class="smi-btn smi-btn--sm smi-btn--danger" id="smi-bulk-delete">' + icon('trash') + '<span>' + esc(t('delete')) + '</span></button></div>' : '';
	}
	function syncSelection() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-sel]'), function (b) {
			var on = !!ui.sel[b.getAttribute('data-sel')];
			b.checked = on;
			b.closest('tr').classList.toggle('is-selected', on);
		});
		var all = $('smi-sel-all');
		if (all) all.checked = list.rows.length > 0 && list.rows.every(function (r) { return ui.sel[r.id]; });
		paintBulk();
	}
	function tab(id, label, n) {
		return '<button type="button" role="tab" data-tab="' + id + '" aria-selected="' + (ui.tab === id) + '">' + esc(label) + ' <span class="c num">' + n + '</span></button>';
	}
	function pbtn(n, ic, label, disabled) {
		return '<button type="button" data-page="' + n + '" aria-label="' + esc(label) + '"' + (disabled ? ' disabled' : '') + '>' + icon(ic) + '</button>';
	}
	function pageWindow(cur, total) {
		var out = [];
		for (var i = 1; i <= total; i++) {
			if (i === 1 || i === total || Math.abs(i - cur) <= 1) out.push(i);
			else if (out[out.length - 1] !== '…') out.push('…');
		}
		return out;
	}
	function filterPop() {
		return '<div><label for="smi-f-svc">' + esc(t('service')) + '</label><select class="smi-select" id="smi-f-svc"><option value="">' + esc(t('all')) + '</option>' +
			S.form.services.map(function (s) { return '<option value="' + esc(s.id) + '"' + (ui.svc === s.id ? ' selected' : '') + '>' + esc(tr(s.label)) + '</option>'; }).join('') + '</select></div>' +
			'<div><label for="smi-f-lang">' + esc(t('formLang')) + '</label><select class="smi-select" id="smi-f-lang"><option value="">' + esc(t('all')) + '</option>' +
			LANGS.map(function (l) { return '<option value="' + l.code + '"' + (ui.lang === l.code ? ' selected' : '') + '>' + esc(l.name) + '</option>'; }).join('') + '</select></div>';
	}
	function langName(code) {
		for (var i = 0; i < LANGS.length; i++) if (LANGS[i].code === code) return LANGS[i].name;
		return code.toUpperCase();
	}
	function initials(n) {
		return String(n || '?').trim().split(/\s+/).map(function (w) { return Array.from(w)[0] || ''; }).slice(0, 2).join('').toUpperCase();
	}
	function svcById(id) {
		for (var i = 0; i < S.form.services.length; i++) if (S.form.services[i].id === id) return S.form.services[i];
		return null;
	}
	var dateFmt = new Intl.DateTimeFormat(UI === 'el' ? 'el-GR' : 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
	function fmtDate(s) {
		var d = s.slice(0, 10), time = s.slice(11, 16);
		if (d === B.today) return tf('todayAt', time);
		if (d === B.yesterday) return tf('yesterdayAt', time);
		var p = d.split('-');
		return dateFmt.format(new Date(+p[0], +p[1] - 1, +p[2]));
	}

	/** A form post that makes the browser save the answer (CSV, PDF). */
	function postDownload(action, fields) {
		var f = document.createElement('form');
		f.method = 'post';
		f.action = B.exportUrl;
		var all = { action: action, nonce: B.nonce };
		Object.keys(fields || {}).forEach(function (k) { all[k] = fields[k]; });
		Object.keys(all).forEach(function (k) {
			var i = document.createElement('input');
			i.type = 'hidden';
			i.name = k;
			i.value = all[k];
			f.appendChild(i);
		});
		document.body.appendChild(f);
		f.submit();
		f.remove();
	}

	function exportCsv(ids) {
		var f = document.createElement('form');
		f.method = 'post';
		f.action = B.exportUrl;
		var add = function (k, v) {
			var i = document.createElement('input');
			i.type = 'hidden';
			i.name = k;
			i.value = v;
			f.appendChild(i);
		};
		add('action', 'smi_export');
		add('nonce', B.nonce);
		if (ids && ids.length) add('ids', ids.join(','));
		else { add('status', ui.tab); add('service', ui.svc); add('lang', ui.lang); add('q', ui.q); }
		document.body.appendChild(f);
		f.submit();
		f.remove();
	}

	/* ---------- Drawer ---------- */
	var drawerData = null;
	function openDrawer(id) {
		ui.drawer = id;
		drawerData = null;
		drawer.innerHTML = '<div class="smi-drawer__top"><div><h2 id="smi-d-title">' + esc(t('loading')) + '</h2></div><button type="button" class="smi-btn smi-btn--icon smi-btn--ghost" id="smi-d-close" aria-label="' + esc(t('close')) + '">' + icon('x') + '</button></div><div class="smi-loading">' + esc(t('loading')) + '</div>';
		drawer.hidden = false;
		scrim.hidden = false;
		$('smi-d-close').focus();
		ajax('smi_get', { id: id }).then(function (r) {
			if (ui.drawer !== id) return;
			drawerData = r;
			paintDrawer();
			list.rows.forEach(function (x) { if (x.id === r.id) x.isRead = 1; });
			paintRequests();
		}).catch(function (e) {
			closeDrawer();
			toast(e.message, true);
		});
	}
	function paintDrawer() {
		var r = drawerData;
		var s = svcById(r.service);
		function dl(rows) {
			return '<dl class="smi-dl">' + rows.map(function (x) { return '<div><dt>' + esc(x[0]) + '</dt><dd>' + esc(x[1]) + '</dd></div>'; }).join('') + '</dl>';
		}
		var sections = (r.view && r.view.sections) || [];
		drawer.innerHTML =
			'<div class="smi-drawer__top"><div><div class="smi-drawer__ref"><span class="num">' + esc(r.ref) + '</span><span class="smi-pill smi-pill--' + r.status + '" id="smi-d-pill">' + esc(statusLabel(r.status)) + '</span></div>' +
				'<h2 id="smi-d-title">' + esc(r.name) + '</h2><p>' + esc((r.company ? r.company + ' · ' : '') + fmtDate(r.date) + ' · ' + tf('formIn', langName(r.lang))) + '</p></div>' +
				'<button type="button" class="smi-btn smi-btn--icon smi-btn--ghost" id="smi-d-close" aria-label="' + esc(t('close')) + '">' + icon('x') + '</button></div>' +
			'<div class="smi-drawer__body">' +
				'<div class="smi-box">' +
					contactRow('mail', t('email'), r.email, 'mailto:' + encodeURIComponent(r.email).replace('%40', '@')) +
					contactRow('phonecall', t('phone'), r.phone, 'tel:' + r.phone.replace(/[^0-9+]/g, '')) +
					'<div class="smi-contact__row">' + icon('chat') + '<div><small>' + esc(t('prefers')) + '</small><strong>' + esc(t(r.pref === 'phone' ? 'phone' : 'email')) + '</strong></div></div>' +
					(r.ip ? '<div class="smi-contact__row">' + icon('globe') + '<div><small>IP</small><strong class="num">' + esc(r.ip) + '</strong></div></div>' : '') +
				'</div>' +
				'<div class="smi-status-row"><label for="smi-d-status">' + esc(t('status')) + '</label><select class="smi-select" id="smi-d-status">' + STATUSES.map(function (st) { return '<option value="' + st + '"' + (st === r.status ? ' selected' : '') + '>' + esc(statusLabel(st)) + '</option>'; }).join('') + '</select></div>' +
				'<div class="smi-dsec"><h3>' + esc(t('service')) + '</h3><div class="smi-svccell"><span class="smi-svcicon">' + icon(s ? s.icon : 'form') + '</span><strong>' + esc(r.view ? r.view.service : r.svcName) + '</strong></div></div>' +
				sections.map(function (sec) { return '<div class="smi-dsec"><h3>' + esc(sec.title) + '</h3>' + dl(sec.rows) + '</div>'; }).join('') +
				'<div class="smi-dsec"><h3>' + esc(t('internalNotes')) + '</h3><textarea class="smi-textarea" id="smi-d-notes" rows="3" placeholder="' + esc(t('notesPh')) + '">' + esc(r.notes) + '</textarea><p class="smi-hint">' + esc(t('notesHint')) + '</p></div>' +
			'</div>' +
			'<div class="smi-drawer__foot"><button type="button" class="smi-btn smi-btn--danger" id="smi-d-delete">' + icon('trash') + '<span>' + esc(t('delete')) + '</span></button>' +
				'<button type="button" class="smi-btn smi-btn--primary" id="smi-d-pdf">' + icon('download') + '<span>' + esc(t('downloadPdf')) + '</span></button></div>';
		$('smi-d-close').focus();
	}
	function contactRow(ic, label, value, href) {
		return '<div class="smi-contact__row">' + icon(ic) + '<div><small>' + esc(label) + '</small><strong><a href="' + esc(href) + '">' + esc(value) + '</a></strong></div>' +
			'<button type="button" class="smi-btn smi-btn--sm" data-copy="' + esc(value) + '">' + icon('copy') + '<span>' + esc(t('copy')) + '</span></button></div>';
	}
	function closeDrawer() {
		var id = ui.drawer;
		drawer.hidden = true;
		scrim.hidden = true;
		ui.drawer = null;
		drawerData = null;
		var b = document.querySelector('[data-open="' + id + '"]');
		if (b) b.focus();
	}

	/** The PDF of a request is the visitor's one: their language, the saved texts and company details. */
	function requestPdf(r) {
		var saved = B.settings;
		var lang = r.lang;
		var tx = function (k) { return tr(saved.texts[k], lang); };
		var v = r.visitor || {};
		var sections = [{ title: tx('pdf_service'), rows: [[tx('pdf_need'), v.service || r.svcName]] }].concat(v.sections || []);
		sections.push({ title: tx('pdf_contact'), rows: [
			[tx('f_name'), r.name], [tx('f_email'), r.email], [tx('f_phone'), r.phone], [tx('f_company'), r.company], [tx('pdf_via'), tx(r.pref === 'phone' ? 'pref_phone' : 'pref_email')]
		].filter(function (x) { return x[1]; }) });
		return {
			accent: saved.look.accent, logo: logoUrl, company: saved.pdf.company, lang: lang,
			title: tx('pdf_title'), ref: r.ref, date: r.pdfDate, sections: sections,
			footer: tr(saved.pdf.footer, lang) || tx('reply_time'), filename: (tx('pdf_file') || 'request') + '-' + r.ref
		};
	}

	/* ====================================================================== */
	/* Form builder                                                            */
	/* ====================================================================== */

	var TYPES = ['single', 'multi', 'text', 'textarea'];
	function uid(prefix, taken) {
		var id;
		do { id = prefix + Math.random().toString(36).slice(2, 7); } while (taken && taken.indexOf(id) > -1);
		return id;
	}
	function allQIds(svc) { return svc.details.concat(svc.features).map(function (q) { return q.id; }); }

	function langSeg(attr, cur) {
		return '<div class="smi-seg" role="group" aria-label="' + esc(t('contentLang')) + '">' + LANGS.map(function (l) {
			return '<button type="button" ' + attr + '="' + l.code + '" aria-pressed="' + (cur === l.code) + '">' + esc(l.name) + '</button>';
		}).join('') + '</div>';
	}

	function viewForm() {
		var L = ui.editLang;
		var html = head(t('view_form'), t('formSub'), saveActions());
		html += '<div class="smi-builder"><div class="smi-svclist">';
		S.form.services.forEach(function (s, i) {
			var n = s.details.length + s.features.length;
			html += '<button type="button" class="smi-svcitem' + (s.on ? '' : ' is-off') + '" data-editsvc="' + esc(s.id) + '" data-dnd="svc" data-idx="' + i + '" aria-current="' + (ui.editSvc === s.id) + '">' +
				'<span class="smi-grip" draggable="true" title="' + esc(t('dragToReorder')) + '">' + icon('grip') + '</span><span class="smi-svcicon">' + icon(s.icon) + '</span>' +
				'<span class="smi-svcitem__txt"><strong>' + esc(tr(s.label, L) || t('untitled')) + '</strong><small>' + esc(tf('nQuestions', n)) + (s.on ? '' : ' · ' + esc(t('hidden'))) + '</small></span></button>';
		});
		html += '<button type="button" class="smi-svcitem" data-editsvc="__common" aria-current="' + (ui.editSvc === '__common') + '"><span class="smi-svcicon">' + icon('layers') + '</span><span class="smi-svcitem__txt"><strong>' + esc(t('commonQs')) + '</strong><small>' + esc(t('commonQsSub')) + '</small></span></button>';
		html += '<button type="button" class="smi-btn" id="smi-add-svc">' + icon('plus') + '<span>' + esc(t('newService')) + '</span></button></div>';

		html += '<div class="smi-editor">';
		html += '<div class="smi-editor__top">' + langSeg('data-elang', L);
		if (ui.editSvc === '__common') {
			html += '</div>' + commonEditor(L);
		} else {
			var s = svcById(ui.editSvc);
			if (!s) { ui.editSvc = '__common'; return viewForm(); }
			html += '<label class="smi-toggle-line" style="align-items:center"><span class="smi-switch"><input type="checkbox" id="smi-svc-on"' + (s.on ? ' checked' : '') + '><span></span></span><strong>' + esc(t('shownInForm')) + '</strong></label></div>';
			html += '<div class="smi-pair">' +
				field(t('serviceName'), 'svc.label', tr1(s.label, L)) +
				field(t('shortDesc'), 'svc.desc', tr1(s.desc, L)) + '</div>';
			html += '<div><div class="smi-note" style="margin-bottom:6px;font-weight:500;color:var(--g700)">' + esc(t('icon')) + '</div><div class="smi-icongrid">' +
				B.icons.map(function (ic) { return '<button type="button" data-icon="' + ic + '" aria-pressed="' + (s.icon === ic) + '" aria-label="' + ic + '">' + icon(ic) + '</button>'; }).join('') + '</div></div>';
			html += stepBlock(s, 'details', L);
			html += stepBlock(s, 'features', L);
			html += '<div class="smi-stepblock"><div class="smi-stepblock__h"><h3>' + esc(t('budget')) + '</h3><span>' + esc(t('budgetSub')) + '</span></div>' +
				'<div class="smi-qcard is-open"><div class="smi-qcard__body" style="padding-left:14px;padding-top:14px">' + optsEditor(s.budget, 'b:' + s.id, L) + '</div></div></div>';
			html += '<div class="smi-qcard__actions" style="border:0"><span></span><button type="button" class="smi-btn smi-btn--danger" id="smi-del-svc">' + icon('trash') + '<span>' + esc(t('deleteService')) + '</span></button></div>';
		}
		html += '</div></div>';
		return html;
	}
	function tr1(map, L) { return map && map[L] != null ? map[L] : ''; }
	function field(label, bind, value, ph, multiline) {
		var input = multiline
			? '<textarea class="smi-textarea" rows="2" data-bind="' + bind + '" placeholder="' + esc(ph || '') + '">' + esc(value) + '</textarea>'
			: '<input class="smi-input" data-bind="' + bind + '" value="' + esc(value) + '" placeholder="' + esc(ph || '') + '">';
		return '<label><span>' + esc(label) + '</span>' + input + '</label>';
	}
	function commonEditor(L) {
		return '<div class="smi-stepblock"><div class="smi-stepblock__h"><h3>' + esc(tr(S.texts.q_when, L) || t('whenQ')) + '</h3><span>' + esc(t('whenSub')) + '</span></div>' +
			'<div class="smi-qcard is-open"><div class="smi-qcard__body" style="padding-left:14px;padding-top:14px">' + optsEditor(S.form.when, 'w', L) + '</div></div></div>' +
			'<p class="smi-note">' + esc(t('commonNote')) + '</p>';
	}
	function stepBlock(s, step, L) {
		var qs = s[step];
		var title = step === 'details' ? t('stepDetails') : t('stepFeatures');
		var html = '<div class="smi-stepblock"><div class="smi-stepblock__h"><h3>' + esc(title) + '</h3><span>' + esc(step === 'features' && !qs.length ? t('stepSkipped') : tf('nQuestions', qs.length)) + '</span></div>';
		html += '<div class="smi-pair">' +
			field(t('stepTitle'), 'svc.' + step + '_title', tr1(s[step + '_title'], L), step === 'details' ? tr(s.label, L) : '') +
			field(t('stepSubtitle'), 'svc.' + step + '_sub', tr1(s[step + '_sub'], L)) + '</div>';
		qs.forEach(function (q, i) { html += qCard(s, step, q, i, L); });
		html += '<button type="button" class="smi-addline" data-addq="' + step + '">' + icon('plus') + esc(t('newQuestion')) + '</button></div>';
		return html;
	}
	function typeLabel(ty) { return t('type_' + ty); }
	function qCard(s, step, q, i, L) {
		var key = step + ':' + i;
		var open = ui.openQ === s.id + ':' + key;
		var choice = q.type === 'single' || q.type === 'multi';
		var html = '<div class="smi-qcard' + (open ? ' is-open' : '') + '" data-dnd="q:' + s.id + ':' + step + '" data-idx="' + i + '">' +
			'<div class="smi-qcard__head" data-toggleq="' + key + '" role="button" tabindex="0" aria-expanded="' + open + '"><span class="smi-grip" draggable="true" title="' + esc(t('dragToReorder')) + '">' + icon('grip') + '</span>' +
				'<span class="smi-qcard__title">' + esc(tr(q.label, L) || t('untitled')) + '</span>' +
				'<span class="smi-badge">' + esc(typeLabel(q.type)) + '</span>' + (q.req ? '<span class="smi-badge smi-badge--req">' + esc(t('required')) + '</span>' : '') +
				(q.show_if ? '<span class="smi-badge">' + esc(t('conditional')) + '</span>' : '') +
				'<span class="smi-chev">' + icon('chev') + '</span></div>';
		if (open) {
			html += '<div class="smi-qcard__body">' +
				'<div class="smi-pair">' + field(t('question'), 'q.label', tr1(q.label, L)) + field(t('hintSmall'), 'q.hint', tr1(q.hint, L), t('hintPh')) + '</div>' +
				'<div class="smi-seg" role="group" aria-label="' + esc(t('answerType')) + '">' + TYPES.map(function (ty) { return '<button type="button" data-qtype="' + ty + '" aria-pressed="' + (q.type === ty) + '">' + esc(typeLabel(ty)) + '</button>'; }).join('') + '</div>' +
				'<div class="smi-flags"><label><span class="smi-switch"><input type="checkbox" data-qflag="req"' + (q.req ? ' checked' : '') + '><span></span></span>' + esc(t('required')) + '</label>' +
				(choice ? '<label><span class="smi-switch"><input type="checkbox" data-qflag="other"' + (q.other ? ' checked' : '') + '><span></span></span>' + esc(t('otherOption')) + '</label>' : '') + '</div>';
			if (!choice) html += '<div class="smi-pair">' + field(t('placeholder'), 'q.ph', tr1(q.ph, L), t('placeholderPh')) + '</div>';
			// "Only show if": earlier single-choice questions of the same step.
			var parents = s[step].slice(0, i).filter(function (p) { return p.type === 'single'; });
			if (parents.length) {
				var cur = q.show_if || {};
				var parent = parents.filter(function (p) { return p.id === cur.q; })[0];
				html += '<div class="smi-lbl"><span>' + esc(t('showIf')) + '</span><div class="smi-showif">' +
					'<select class="smi-select" data-showif="q"><option value="">' + esc(t('always')) + '</option>' + parents.map(function (p) { return '<option value="' + esc(p.id) + '"' + (cur.q === p.id ? ' selected' : '') + '>' + esc(tr(p.label, L)) + '</option>'; }).join('') + '</select>' +
					(parent ? '<select class="smi-select" data-showif="opt" aria-label="' + esc(t('showIfAnswer')) + '">' + parent.opts.map(function (o) { return '<option value="' + esc(o.id) + '"' + (cur.opt === o.id ? ' selected' : '') + '>= ' + esc(tr(o.label, L)) + '</option>'; }).join('') + '</select>' : '') +
					'</div></div>';
			}
			if (choice) html += '<div class="smi-lbl"><span>' + esc(t('options')) + '</span>' + optsEditor(q.opts, 'o:' + s.id + ':' + step + ':' + i, L) + '</div>';
			html += '<div class="smi-qcard__actions"><span>' +
				'<button type="button" class="smi-btn smi-btn--sm" data-qmove="-1"' + (i === 0 ? ' disabled' : '') + '>' + icon('up') + '<span>' + esc(t('moveUp')) + '</span></button> ' +
				'<button type="button" class="smi-btn smi-btn--sm" data-qmove="1"' + (i === s[step].length - 1 ? ' disabled' : '') + '>' + icon('chev') + '<span>' + esc(t('moveDown')) + '</span></button></span>' +
				'<button type="button" class="smi-btn smi-btn--sm smi-btn--danger" data-qdel="1">' + icon('trash') + '<span>' + esc(t('deleteQuestion')) + '</span></button></div>';
			html += '</div>';
		}
		return html + '</div>';
	}
	function optsEditor(opts, group, L) {
		return '<div class="smi-opts">' + opts.map(function (o, j) {
			return '<div class="smi-opt" data-dnd="' + esc(group) + '" data-idx="' + j + '"><span class="smi-grip" draggable="true" title="' + esc(t('dragToReorder')) + '">' + icon('grip') + '</span>' +
				'<input class="smi-input" data-opt="' + esc(group) + '|' + j + '" value="' + esc(tr1(o.label, L)) + '" placeholder="' + esc(tr(o.label, L === 'el' ? 'en' : 'el')) + '" aria-label="' + esc(tf('optionN', j + 1)) + '">' +
				'<button type="button" class="smi-btn smi-btn--icon smi-btn--ghost smi-btn--sm" data-delopt="' + esc(group) + '|' + j + '" aria-label="' + esc(t('removeOption')) + '"' + (opts.length < 2 ? ' disabled' : '') + '>' + icon('x') + '</button></div>';
		}).join('') + '</div><button type="button" class="smi-addline" data-addopt="' + esc(group) + '">' + icon('plus') + esc(t('addOption')) + '</button>';
	}

	/** The array behind a drag-and-drop / options group key. */
	function arrFor(group) {
		var p = group.split(':');
		if (group === 'svc') return S.form.services;
		if (group === 'w') return S.form.when;
		var s = svcById(p[1]);
		if (!s) return null;
		if (p[0] === 'b') return s.budget;
		if (p[0] === 'q') return s[p[2]];
		if (p[0] === 'o') return s[p[2]][+p[3]] ? s[p[2]][+p[3]].opts : null;
		return null;
	}
	function curQ(el) {
		var card = el.closest('.smi-qcard[data-dnd^="q:"]');
		if (!card) return null;
		var p = card.getAttribute('data-dnd').split(':');
		var s = svcById(p[1]);
		return { svc: s, step: p[2], idx: +card.getAttribute('data-idx'), q: s[p[2]][+card.getAttribute('data-idx')] };
	}

	/* ====================================================================== */
	/* Texts                                                                   */
	/* ====================================================================== */

	var TEXT_GROUPS = [
		{ id: 'info', keys: ['info_service', 'info_features', 'info_plan', 'info_contact', 'reply_time'] },
		{ id: 'titles', keys: ['brand_title', 'brand_sub', 's1_title', 's1_sub', 'plan_title', 'plan_sub', 'contact_title', 'contact_sub', 'summary', 'summary_empty', 'step_of', 'step_service', 'step_details', 'step_features', 'step_plan', 'step_contact'] },
		{ id: 'fields', keys: ['q_when', 'q_budget', 'budget_hint', 'q_notes', 'notes_ph', 'optional', 'f_name', 'f_name_ph', 'f_email', 'f_email_ph', 'f_phone', 'f_phone_ph', 'f_company', 'f_company_ph', 'q_pref', 'pref_phone', 'pref_email', 'consent', 'privacy_link', 'other', 'other_ph'] },
		{ id: 'buttons', keys: ['back', 'next', 'submit', 'sending', 'clear', 'clear_title', 'clear_text', 'cancel', 'clear_yes', 'close'] },
		{ id: 'errors', keys: ['err_single', 'err_multi', 'err_field', 'err_email', 'err_phone', 'err_consent', 'err_send', 'err_changed', 'err_rate', 'err_turnstile', 'err_turnstile_down'] },
		{ id: 'done', keys: ['thanks', 'request', 'received', 'pdf_copy', 'pdf_btn', 'restart', 'pdf_title', 'pdf_service', 'pdf_need', 'pdf_contact', 'pdf_via', 'pdf_file'] }
	];
	var openGroups = { info: true };
	function viewTexts() {
		var html = head(t('view_texts'), t('textsSub'), saveActions());
		TEXT_GROUPS.forEach(function (g) {
			html += '<details class="smi-group" data-group="' + g.id + '"' + (openGroups[g.id] ? ' open' : '') + '><summary><span>' + esc(t('tg_' + g.id)) + ' <small>· ' + g.keys.length + '</small></span>' + icon('chev') + '</summary><div class="smi-group__body">';
			g.keys.forEach(function (k) {
				var long = /^(info_|consent|received|clear_text|err_|pdf_copy|contact_sub|plan_sub|s1_sub)/.test(k);
				html += '<div class="smi-textrow"><div class="smi-textrow__label"><strong>' + esc(t('tx_' + k)) + '</strong>' + (I['txh_' + k] ? '<small>' + esc(t('txh_' + k)) + '</small>' : '') +
					'<button type="button" data-reset="' + k + '">' + esc(t('resetDefault')) + '</button></div><div class="smi-langs">' +
					LANGS.map(function (l) {
						var v = S.texts[k] ? S.texts[k][l.code] || '' : '';
						return '<label><span class="smi-lang">' + esc(l.code.toUpperCase()) + '</span>' + (long
							? '<textarea class="smi-textarea" rows="2" data-text="' + k + '|' + l.code + '">' + esc(v) + '</textarea>'
							: '<input class="smi-input" data-text="' + k + '|' + l.code + '" value="' + esc(v) + '">') + '</label>';
					}).join('') + '</div></div>';
			});
			html += '</div></details>';
		});
		return html;
	}

	/* ====================================================================== */
	/* Notifications                                                           */
	/* ====================================================================== */

	function viewNotify() {
		var n = S.notify;
		var mailer = B.mailer;
		var html = head(t('view_notify'), t('notifySub'), saveActions()) + '<div class="smi-set">' +
			row(t('newRequestEmail'), t('newRequestEmailSub'),
				'<label class="smi-toggle-line"><span class="smi-switch"><input type="checkbox" id="smi-n-on"' + (n.enabled ? ' checked' : '') + '><span></span></span><span><strong>' + esc(t('emailEachRequest')) + '</strong><small>' + esc(t('emailEachRequestSub')) + '</small></span></label>') +
			row(t('recipients'), t('recipientsSub'),
				'<div class="smi-tags" id="smi-tags">' + n.recipients.map(function (r, i) { return '<span class="smi-tag">' + esc(r) + '<button type="button" data-deltag="' + i + '" aria-label="' + esc(tf('removeX', r)) + '">' + icon('x') + '</button></span>'; }).join('') +
				'<input id="smi-tag-input" type="email" placeholder="' + esc(t('recipientPh')) + '" aria-label="' + esc(t('recipients')) + '"></div>' +
				(n.recipients.length ? '' : '<p class="smi-note">' + esc(tf('noRecipients', B.adminEmail)) + '</p>')) +
			row(t('subject'), t('subjectSub'), '<input class="smi-input" data-bind="notify.subject" value="' + esc(n.subject) + '">') +
			row(t('attachPdf'), t('attachPdfSub'),
				'<label class="smi-toggle-line"><span class="smi-switch"><input type="checkbox" id="smi-n-attach"' + (n.attach ? ' checked' : '') + '><span></span></span><span><strong>' + esc(t('attachPdfOn')) + '</strong><small>' + esc(t(B.serverPdf ? 'attachPdfOnSub' : 'noServerPdf')) + '</small></span></label>') +
			row(t('delivery'), t('deliverySub'),
				(mailer ? '<div class="smi-notice-box smi-notice-box--ok">' + icon('ok') + '<div><strong>' + esc(mailer === 'custom' ? t('mailerCustom') : tf('mailerFound', mailer)) + '</strong>' + esc(t('mailerOkSub')) + '</div></div>'
					: '<div class="smi-notice-box">' + icon('warn') + '<div><strong>' + esc(t('noMailer')) + '</strong>' + esc(t('noMailerSub')) + '</div></div>') +
				'<div><button type="button" class="smi-btn" id="smi-test-mail">' + icon('mail') + '<span>' + esc(t('sendTest')) + '</span></button></div>') +
			'</div>';
		return html;
	}
	function row(label, sub, fieldHtml) {
		return '<div class="smi-set__row"><div class="smi-set__label"><strong>' + esc(label) + '</strong>' + (sub ? '<span>' + esc(sub) + '</span>' : '') + '</div><div class="smi-set__field">' + fieldHtml + '</div></div>';
	}

	/* ====================================================================== */
	/* PDF                                                                     */
	/* ====================================================================== */

	function viewPdf() {
		var p = S.pdf;
		var c = p.company;
		var html = head(t('view_pdf'), t('pdfSub'), saveActions()) + '<div class="smi-set">' +
			row(t('logo'), t('logoSub'),
				'<div class="smi-logo"><span class="smi-logo__img"><img src="' + esc(p.logo_id && logoUrl ? logoUrl : B.logoDefault) + '" alt=""></span>' + (p.logo_id ? '' : '<span class="smi-note">' + esc(t('defaultLogo')) + '</span>') +
				'<button type="button" class="smi-btn" id="smi-logo-pick">' + icon('upload') + '<span>' + esc(t(p.logo_id ? 'changeLogo' : 'chooseLogo')) + '</span></button>' +
				(p.logo_id ? '<button type="button" class="smi-btn smi-btn--ghost" id="smi-logo-remove">' + esc(t('remove')) + '</button>' : '') + '</div>') +
			row(t('companyDetails'), t('companyDetailsSub'), '<div class="smi-pair">' +
				field(t('co_name'), 'co.name', c.name) + field(t('co_website'), 'co.website', c.website, 'smartupweb.com') +
				field(t('co_address'), 'co.address', c.address) + field(t('co_phone'), 'co.phone', c.phone) +
				field(t('co_email'), 'co.email', c.email) + field(t('co_vat'), 'co.vat', c.vat) + '</div>') +
			row(t('footer'), t('footerSub'), '<div class="smi-langs">' + LANGS.map(function (l) {
				return '<label><span class="smi-lang">' + esc(l.code.toUpperCase()) + '</span><input class="smi-input" data-footer="' + l.code + '" value="' + esc(p.footer[l.code] || '') + '" placeholder="' + esc(tr(S.texts.reply_time, l.code)) + '"></label>';
			}).join('') + '</div>') +
			row(t('refPrefix'), t('refPrefixSub'), '<input class="smi-input" style="max-width:140px;text-transform:uppercase" maxlength="6" data-bind="ref_prefix" value="' + esc(S.ref_prefix) + '">') +
			row(t('sample'), t('sampleSub'), '<div class="smi-preview-btn">' + LANGS.map(function (l) {
				return '<button type="button" class="smi-btn" data-sample="' + l.code + '">' + icon('download') + '<span>' + esc(tf('samplePdf', l.name)) + '</span></button>';
			}).join('') + '</div>') +
			'</div>';
		return html;
	}
	function samplePdf(lang) {
		var tx = function (k) { return tr(S.texts[k], lang); };
		var s = S.form.services[0];
		var rows = s ? s.details.filter(function (q) { return q.opts.length; }).slice(0, 3).map(function (q) { return [tr(q.label, lang).replace(/[\s?;:;]+$/, ''), tr(q.opts[0].label, lang)]; }) : [];
		return {
			accent: S.look.accent, logo: S.pdf.logo_id ? logoUrl : B.logoDefault, company: S.pdf.company, lang: lang,
			title: tx('pdf_title'), ref: S.ref_prefix + '-' + B.today.slice(0, 4) + '-0001', date: B.today.split('-').reverse().join('/'),
			sections: [
				{ title: tx('pdf_service'), rows: [[tx('pdf_need'), s ? tr(s.label, lang) : '']] },
				{ title: tx('step_details'), rows: rows },
				{ title: tx('step_plan'), rows: [[tx('q_when').replace(/[\s?;:;]+$/, ''), tr(S.form.when[0].label, lang)], [tx('q_budget').replace(/[\s?;:;]+$/, ''), s ? tr(s.budget[0].label, lang) : '']] },
				{ title: tx('pdf_contact'), rows: [[tx('f_name'), tx('f_name_ph')], [tx('f_email'), tx('f_email_ph')], [tx('f_phone'), tx('f_phone_ph')]] }
			],
			footer: tr(S.pdf.footer, lang) || tx('reply_time'), filename: (tx('pdf_file') || 'request') + '-sample'
		};
	}

	/* ====================================================================== */
	/* Appearance & links                                                      */
	/* ====================================================================== */

	function viewLook() {
		var l = S.look;
		// Examples use a service that exists on this site.
		var ex = (S.form.services.filter(function (s) { return s.on; })[0] || S.form.services[0] || { id: 'app' }).id;
		var html = head(t('view_look'), t('lookSub'), saveActions()) + '<div class="smi-set">' +
			row(t('colors'), t('colorsSub'), '<div class="smi-pair">' +
				colorField(t('c_accent'), 'accent', l.accent) + colorField(t('c_accent_text'), 'accent_text', l.accent_text) + colorField(t('c_panel'), 'panel', l.panel) + '</div>' +
				'<div class="smi-preview-btn" id="smi-look-preview"><span class="demo" style="background:' + esc(l.accent) + ';color:' + esc(l.accent_text) + '">' + esc(tr(S.texts.next)) + '</span><span class="panel" style="background:' + esc(l.panel) + '" title="' + esc(t('c_panel')) + '"></span></div>') +
			viewFloat() +
			row(t('openForm'), t('openFormSub'), codeRow('#smartup-inquiry', t('linkAny')) + codeRow('js-inquiry', t('classAny'))) +
			row(t('inMenu'), t('inMenuSub'), '<p class="smi-note">' + esc(t('inMenuHow')) + '</p>' + codeRow('js-inquiry', t('inMenuClass')) + codeRow('js-inquiry-' + ex, t('inMenuClassSvc'))) +
			row(t('withService'), t('withServiceSub'), S.form.services.filter(function (s) { return s.on; }).map(function (s) { return codeRow('#smartup-inquiry-' + s.id, tr(s.label)); }).join('') || '<p class="smi-note">' + esc(t('noServices')) + '</p>') +
			row(t('withLang'), t('withLangSub'), LANGS.map(function (x) { return codeRow('#smartup-inquiry-' + x.code, tf('alwaysIn', x.name)); }).join('') + codeRow('#smartup-inquiry-' + ex + '-' + LANGS[0].code, t('combined'))) +
			'</div>';
		return html;
	}
	function viewFloat() {
		var f = S.float;
		var html = row(t('floatBtn'), t('floatBtnSub'),
			'<label class="smi-toggle-line"><span class="smi-switch"><input type="checkbox" id="smi-f-on"' + (f.enabled ? ' checked' : '') + '><span></span></span><span><strong>' + esc(t('floatOn')) + '</strong><small>' + esc(t('floatOnSub')) + '</small></span></label>' +
			(f.enabled ? '<div class="smi-float-demo" id="smi-float-demo"><span class="smi-float-demo__btn" style="background:' + esc(f.bg) + ';color:' + esc(f.color) + '">' + esc(tr(f.label) || tr(B.defaults.float.label)) + '</span></div>' : ''));
		if (!f.enabled) return html;
		var byId = {};
		B.pages.forEach(function (p) { byId[p.id] = p; });
		return html +
			row(t('floatPos'), t('floatPosSub'), '<div class="smi-seg" role="group" aria-label="' + esc(t('floatPos')) + '">' + ['left', 'right'].map(function (p) {
				return '<button type="button" data-fpos="' + p + '" aria-pressed="' + (f.position === p) + '">' + esc(t(p === 'left' ? 'floatLeft' : 'floatRight')) + '</button>';
			}).join('') + '</div>') +
			row(t('floatLabel'), t('floatLabelSub'), '<div class="smi-langs">' + LANGS.map(function (x) {
				return '<label><span class="smi-lang">' + esc(x.code.toUpperCase()) + '</span><input class="smi-input" maxlength="40" data-flabel="' + x.code + '" value="' + esc(f.label[x.code] || '') + '" placeholder="' + esc(tr(B.defaults.float.label, x.code)) + '"></label>';
			}).join('') + '</div>') +
			row(t('colors'), t('floatColorsSub'), '<div class="smi-pair">' + floatColorField(t('floatBg'), 'bg', f.bg) + floatColorField(t('floatText'), 'color', f.color) + '</div>') +
			row(t('floatMobile'), t('floatMobileSub'), '<select class="smi-select" style="max-width:280px" id="smi-f-mobile">' + ['show', 'hide'].map(function (m) {
				return '<option value="' + m + '"' + (f.mobile === m ? ' selected' : '') + '>' + esc(t('floatM_' + m)) + '</option>';
			}).join('') + '</select>') +
			row(t('floatExclude'), t('floatExcludeSub'),
				(f.exclude.length ? '<div class="smi-tags smi-tags--static">' + f.exclude.map(function (id, i) {
					var title = byId[id] ? byId[id].title : '#' + id;
					return '<span class="smi-tag">' + esc(title) + '<button type="button" data-fexdel="' + i + '" aria-label="' + esc(tf('removeX', title)) + '">' + icon('x') + '</button></span>';
				}).join('') + '</div>' : '<p class="smi-note">' + esc(t('floatNoExclude')) + '</p>') +
				'<select class="smi-select" style="max-width:360px" id="smi-f-exclude"><option value="">' + esc(t('floatAddPage')) + '</option>' +
				B.pages.filter(function (p) { return f.exclude.indexOf(p.id) < 0; }).map(function (p) { return '<option value="' + p.id + '">' + esc(p.title) + '</option>'; }).join('') + '</select>');
	}
	function floatColorField(label, key, hex) {
		return '<label><span>' + esc(label) + '</span><span class="smi-colorf"><input type="color" data-fcolor="' + key + '" value="' + esc(hex) + '" aria-label="' + esc(label) + '"><input class="smi-input" data-fcolorhex="' + key + '" value="' + esc(hex.toUpperCase()) + '" maxlength="7" aria-label="' + esc(label) + ' (hex)"></span></label>';
	}
	function syncFloatDemo() {
		var d = $('smi-float-demo');
		if (!d) return;
		var b = d.firstChild;
		b.style.background = S.float.bg;
		b.style.color = S.float.color;
		b.textContent = tr(S.float.label) || tr(B.defaults.float.label);
	}
	function colorField(label, key, hex) {
		return '<label><span>' + esc(label) + '</span><span class="smi-colorf"><input type="color" data-color="' + key + '" value="' + esc(hex) + '" aria-label="' + esc(label) + '"><input class="smi-input" data-colorhex="' + key + '" value="' + esc(hex.toUpperCase()) + '" maxlength="7" aria-label="' + esc(label) + ' (hex)"></span></label>';
	}
	function codeRow(code, note) {
		return '<div class="smi-code"><div><code>' + esc(code) + '</code><small>' + esc(note) + '</small></div><button type="button" class="smi-btn smi-btn--sm" data-copy="' + esc(code) + '">' + icon('copy') + '<span>' + esc(t('copy')) + '</span></button></div>';
	}

	/* ====================================================================== */
	/* Privacy                                                                 */
	/* ====================================================================== */

	function viewPrivacy() {
		var p = S.privacy;
		var wp = B.pages.filter(function (x) { return x.id === B.wpPrivacy; })[0];
		var html = head(t('view_privacy'), t('privacySub'), saveActions()) + '<div class="smi-set">' +
			row(t('privacyPage'), t('privacyPageSub'), '<div class="smi-langs">' + LANGS.map(function (l) {
				var cur = p.pages[l.code] || 0;
				return '<label><span class="smi-lang">' + esc(l.code.toUpperCase()) + '</span><select class="smi-select" data-ppage="' + l.code + '"><option value="0">' + esc(wp ? tf('wpPrivacyPage', wp.title) : t('noPrivacyPage')) + '</option>' +
					B.pages.map(function (x) { return '<option value="' + x.id + '"' + (cur === x.id ? ' selected' : '') + '>' + esc(x.title) + '</option>'; }).join('') + '</select></label>';
			}).join('') + '</div>') +
			row(t('retention'), t('retentionSub'), '<select class="smi-select" style="max-width:280px" id="smi-retention">' + [0, 6, 12, 24, 36].map(function (m) {
				return '<option value="' + m + '"' + (p.retention === m ? ' selected' : '') + '>' + esc(m ? tf('afterMonths', m) : t('never')) + '</option>';
			}).join('') + '</select>') +
			row(t('ipAddress'), t('ipAddressSub'), '<label class="smi-toggle-line"><span class="smi-switch"><input type="checkbox" id="smi-store-ip"' + (p.store_ip ? ' checked' : '') + '><span></span></span><span><strong>' + esc(t('storeIp')) + '</strong><small>' + esc(t('storeIpSub')) + '</small></span></label>') +
			row(t('spam'), t('spamSub'), '<div class="smi-notice-box smi-notice-box--ok">' + icon('ok') + '<div><strong>' + esc(t('spamOn')) + '</strong>' + esc(t('spamOnSub')) + '</div></div>') +
			viewTurnstile() +
			row(t('wpTools'), t('wpToolsSub'), '<p class="smi-note">' + esc(t('wpToolsText')) + '</p>') +
			'</div>';
		return html;
	}

	/* ====================================================================== */
	/* Access (administrators only): the entry link, the two roles, who has access */
	/* ====================================================================== */

	/** Same rules as WordPress page addresses, so what is typed is what gets saved. */
	function cleanSlug(v) {
		return String(v || '').toLowerCase().trim().replace(/\s+/g, '-').replace(/[^a-z0-9_-]/g, '').replace(/-+/g, '-').slice(0, 60);
	}
	function entryLinkHtml() {
		var A = B.access || {};
		var slug = S.access.slug;
		var html = slug ? codeRow(A.home + slug + '/', t('entryLinkCopy')) : '<p class="smi-note">' + esc(t('entryLinkOff')) + '</p>';
		if (slug && A.taken && A.takenFor === slug) html += '<div class="smi-notice-box">' + icon('warn') + '<div><strong>' + esc(t('slugTaken')) + '</strong>' + esc(t('slugTakenSub')) + '</div></div>';
		return html;
	}
	function viewAccess() {
		var A = B.access || { users: [], home: '', addUrl: '' };
		var people = A.users.length
			? '<div class="smi-people">' + A.users.map(function (u) {
				return '<div class="smi-person"><span class="smi-avatar">' + esc((u.name || '?').charAt(0).toUpperCase()) + '</span>' +
					'<div><strong>' + esc(u.name) + '</strong><small>' + esc(u.email) + '</small></div>' +
					'<span class="smi-person__role">' + esc(B.roleNames[u.role] || u.role) + '</span>' +
					'<a class="smi-btn smi-btn--sm" href="' + esc(u.editUrl) + '">' + esc(t('editPerson')) + '</a></div>';
			}).join('') + '</div>'
			: '<p class="smi-note">' + esc(t('noPeople')) + '</p>';
		return head(t('view_access'), t('accessSub'), saveActions()) + '<div class="smi-set">' +
			row(t('entryLink'), t('entryLinkSub'),
				'<div class="smi-slug"><span class="smi-slug__base">' + esc(A.home) + '</span><input class="smi-input" data-access="slug" value="' + esc(S.access.slug) + '" maxlength="60" spellcheck="false" aria-label="' + esc(t('entryLink')) + '"></div>' +
				'<div id="smi-entry">' + entryLinkHtml() + '</div>') +
			row(t('roles'), t('rolesSub'), Object.keys(B.roleNames).map(function (r) {
				return '<div class="smi-role"><strong>' + esc(B.roleNames[r]) + '</strong><span>' + esc(t('role_' + r)) + '</span></div>';
			}).join('')) +
			row(t('people'), t('peopleSub'), people + '<div class="smi-people__add"><a class="smi-btn" href="' + esc(A.addUrl) + '">' + icon('plus') + '<span>' + esc(t('addPerson')) + '</span></a></div>') +
			'</div>';
	}

	/** Cloudflare Turnstile: own keys if both are given, otherwise the ones of Contact Form 7. */
	function viewTurnstile() {
		var ts = S.turnstile;
		var own = ts.site && (ts.has_secret || ts.secret);
		var box = own
			? '<div class="smi-notice-box smi-notice-box--ok">' + icon('ok') + '<div><strong>' + esc(t('tsOwn')) + '</strong>' + esc(t('tsOwnSub')) + '</div></div>'
			: B.turnstile.cf7
				? '<div class="smi-notice-box smi-notice-box--ok">' + icon('ok') + '<div><strong>' + esc(t('tsCf7')) + '</strong>' + esc(tf('tsCf7Sub', B.turnstile.cf7Site)) + '</div></div>'
				: '<div class="smi-notice-box">' + icon('warn') + '<div><strong>' + esc(t('tsNone')) + '</strong>' + esc(t('tsNoneSub')) + '</div></div>';
		return row(t('turnstile'), t('turnstileSub'),
			'<label class="smi-toggle-line"><span class="smi-switch"><input type="checkbox" id="smi-ts-on"' + (ts.enabled ? ' checked' : '') + '><span></span></span><span><strong>' + esc(t('tsOn')) + '</strong><small>' + esc(t('tsOnSub')) + '</small></span></label>' +
			box +
			'<details class="smi-ts-keys"' + (own ? ' open' : '') + '><summary>' + esc(t('tsKeys')) + '</summary><p class="smi-note">' + esc(t('tsKeysSub')) + '</p><div class="smi-pair">' +
				'<label><span>Site Key</span><input class="smi-input" data-ts="site" value="' + esc(ts.site) + '" autocomplete="off" spellcheck="false"></label>' +
				'<label><span>Secret Key</span><input class="smi-input" type="password" data-ts="secret" value="" placeholder="' + esc(ts.has_secret ? t('tsSecretSaved') : '') + '" autocomplete="new-password" spellcheck="false"></label>' +
			'</div>' + (ts.has_secret ? '<button type="button" class="smi-btn smi-btn--ghost smi-btn--sm" id="smi-ts-clear">' + esc(t('tsClear')) + '</button>' : '') + '</details>');
	}

	/* ====================================================================== */
	/* Saving                                                                  */
	/* ====================================================================== */

	function save() {
		var btn = $('smi-save');
		if (btn) btn.disabled = true;
		return ajax('smi_save', { settings: JSON.stringify(S) }).then(function (d) {
			S = clone(d.settings);
			B.settings = clone(d.settings);
			savedJson = JSON.stringify(S);
			logoUrl = d.logoUrl;
			if (ui.editSvc !== '__common' && !svcById(ui.editSvc)) ui.editSvc = S.form.services.length ? S.form.services[0].id : '__common';
			render();
			toast(d.caches && d.caches.length ? tf('savedCaches', d.caches.join(', ')) : t('saved'));
		}).catch(function (e) {
			if (btn) btn.disabled = false;
			toast(e.message, true);
		});
	}
	window.addEventListener('beforeunload', function (e) {
		if (dirty()) { e.preventDefault(); e.returnValue = ''; }
	});

	/* ====================================================================== */
	/* Events                                                                  */
	/* ====================================================================== */

	function copy(v) {
		var done = function () { toast(tf('copied', v)); };
		try { navigator.clipboard.writeText(v).then(done, done); } catch (err) { done(); }
	}
	function arm(btn) {
		if (btn.classList.contains('is-armed')) return true;
		btn.classList.add('is-armed');
		btn.querySelector('span').textContent = t('sureDelete');
		setTimeout(function () {
			if (!btn.isConnected || !btn.classList.contains('is-armed')) return;
			btn.classList.remove('is-armed');
			btn.querySelector('span').textContent = t('delete');
		}, 4000);
		return false;
	}

	document.addEventListener('click', function (e) {
		var tg = e.target;
		if (!tg.closest || (!mount.contains(tg) && !drawer.contains(tg) && tg !== scrim)) return;
		var b;

		// Desktop notifications
		if (tg.closest('#smi-desk-btn')) {
			desk.open = !desk.open;
			$('smi-desk-pop').hidden = !desk.open;
			$('smi-desk-btn').setAttribute('aria-expanded', String(desk.open));
			return;
		}
		if (tg.closest('#smi-desk-test')) {
			deskNotify({ id: 0, ref: 'SU-TEST', svcName: t('deskTestBody'), name: B.user || '' });
			if (desk.sound) deskSound();
			return;
		}
		if ((b = tg.closest('[data-tone-play]'))) { deskSound(b.getAttribute('data-tone-play')); return; }
		if (desk.open && !tg.closest('.smi-desk')) {
			desk.open = false;
			var dp = $('smi-desk-pop');
			if (dp) { dp.hidden = true; $('smi-desk-btn').setAttribute('aria-expanded', 'false'); }
		}

		// Navigation
		if ((b = tg.closest('[data-view]'))) { ui.view = VIEWS.some(function (x) { return x.id === b.getAttribute('data-view'); }) ? b.getAttribute('data-view') : 'requests'; ui.filterOpen = false; render(); return; }
		if ((b = tg.closest('[data-svcnav]'))) { ui.view = 'requests'; ui.svc = b.getAttribute('data-svcnav'); ui.page = 1; ui.sel = {}; render(); return; }
		if (tg.closest('#smi-svc-all')) { ui.svc = ''; ui.page = 1; render(); return; }
		if ((b = tg.closest('[data-ui]'))) {
			var lang = b.getAttribute('data-ui');
			if (lang === UI) return;
			if (dirty() && !window.confirm(t('leaveUnsaved'))) return;
			ajax('smi_ui_lang', { lang: lang }).then(function () { savedJson = JSON.stringify(S); window.location.reload(); }).catch(function (er) { toast(er.message, true); });
			return;
		}
		if ((b = tg.closest('[data-copy]'))) { copy(b.getAttribute('data-copy')); return; }
		if (tg.closest('#smi-save')) { save(); return; }

		// Requests
		if ((b = tg.closest('[data-tab]'))) { ui.tab = b.getAttribute('data-tab'); ui.page = 1; ui.sel = {}; loadList(); paintRequests(); return; }
		if ((b = tg.closest('[data-clear]'))) { ui[b.getAttribute('data-clear')] = ''; ui.page = 1; renderNav(); loadList(); return; }
		if ((b = tg.closest('[data-page]'))) { if (!b.disabled) { ui.page = +b.getAttribute('data-page'); loadList(); } return; }
		if (tg.closest('#smi-filter')) {
			var pop = $('smi-pop');
			ui.filterOpen = pop.hidden;
			pop.innerHTML = filterPop();
			pop.hidden = !ui.filterOpen;
			$('smi-filter').setAttribute('aria-expanded', String(ui.filterOpen));
			return;
		}
		if (ui.filterOpen && !tg.closest('#smi-pop')) { ui.filterOpen = false; var pp = $('smi-pop'); if (pp) pp.hidden = true; }
		if (tg.closest('#smi-export-all')) { exportCsv(null); return; }
		if (tg.closest('#smi-bulk-export')) { exportCsv(selectedIds()); return; }
		if ((b = tg.closest('#smi-bulk-delete'))) {
			if (!arm(b)) return;
			var ids = selectedIds();
			ajax('smi_bulk', { ids: ids.join(','), do: 'delete' }).then(function (d) { ui.sel = {}; toast(tf('nDeleted', d.n)); loadList(); }).catch(function (er) { toast(er.message, true); });
			return;
		}
		if (tg.matches('.smi-check') || tg.closest('.w-check')) return;
		if ((b = tg.closest('[data-open]')) || (b = tg.closest('tr[data-row]'))) { openDrawer(+(b.getAttribute('data-open') || b.getAttribute('data-row'))); return; }

		// Drawer
		if (tg.closest('#smi-d-close') || tg === scrim) { closeDrawer(); return; }
		if ((b = tg.closest('#smi-d-pdf')) && drawerData) {
			// The server's copy is the very file the team got by email; the browser draws one only when the server can't.
			if (B.serverPdf) { postDownload('smi_pdf', { id: drawerData.id }); return; }
			b.disabled = true;
			window.SmartInquiryPDF.download(requestPdf(drawerData)).then(function () { b.disabled = false; }, function () { b.disabled = false; });
			return;
		}
		if ((b = tg.closest('#smi-d-delete')) && drawerData) {
			if (!arm(b)) return;
			var ref = drawerData.ref;
			ajax('smi_bulk', { ids: String(drawerData.id), do: 'delete' }).then(function () { delete ui.sel[drawerData ? drawerData.id : 0]; closeDrawer(); toast(tf('deletedX', ref)); loadList(); }).catch(function (er) { toast(er.message, true); });
			return;
		}

		// Form builder
		if ((b = tg.closest('[data-editsvc]'))) { if (tg.closest('.smi-grip')) return; ui.editSvc = b.getAttribute('data-editsvc'); ui.openQ = null; render(); return; }
		if ((b = tg.closest('[data-elang]'))) { ui.editLang = b.getAttribute('data-elang'); render(); return; }
		if ((b = tg.closest('[data-icon]'))) { svcById(ui.editSvc).icon = b.getAttribute('data-icon'); render(); return; }
		if ((b = tg.closest('[data-toggleq]'))) {
			if (tg.closest('.smi-grip')) return;
			var k = ui.editSvc + ':' + b.getAttribute('data-toggleq');
			ui.openQ = ui.openQ === k ? null : k;
			render();
			return;
		}
		if ((b = tg.closest('[data-qtype]'))) {
			var c = curQ(b);
			c.q.type = b.getAttribute('data-qtype');
			if ((c.q.type === 'single' || c.q.type === 'multi') && !c.q.opts.length) c.q.opts = twoOpts();
			if (c.q.type !== 'single') {
				// Questions depending on this one no longer can.
				c.svc[c.step].forEach(function (o) { if (o.show_if && o.show_if.q === c.q.id) o.show_if = null; });
			}
			render();
			return;
		}
		if ((b = tg.closest('[data-qmove]'))) {
			var m = curQ(b), d = +b.getAttribute('data-qmove'), arr = m.svc[m.step], to = m.idx + d;
			if (to < 0 || to >= arr.length) return;
			arr.splice(to, 0, arr.splice(m.idx, 1)[0]);
			fixShowIf(arr);
			ui.openQ = ui.editSvc + ':' + m.step + ':' + to;
			render();
			return;
		}
		if ((b = tg.closest('[data-qdel]'))) {
			if (!arm(b)) return;
			var qd = curQ(b);
			qd.svc[qd.step].splice(qd.idx, 1);
			fixShowIf(qd.svc[qd.step]);
			ui.openQ = null;
			render();
			return;
		}
		if ((b = tg.closest('[data-addq]'))) {
			var sv = svcById(ui.editSvc), step = b.getAttribute('data-addq');
			var lbl = {}; lbl[ui.editLang] = t('newQuestion');
			sv[step].push({ id: uid('q', allQIds(sv)), type: 'single', req: 0, other: 0, label: lbl, hint: {}, ph: {}, opts: twoOpts(), show_if: null });
			ui.openQ = sv.id + ':' + step + ':' + (sv[step].length - 1);
			render();
			return;
		}
		if ((b = tg.closest('[data-addopt]'))) {
			var group = b.getAttribute('data-addopt'), opts = arrFor(group);
			opts.push(newOpt(opts.map(function (o) { return o.id; })));
			render();
			var inputs = main.querySelectorAll('[data-opt^="' + group + '|"]');
			if (inputs.length) inputs[inputs.length - 1].focus();
			return;
		}
		if ((b = tg.closest('[data-delopt]'))) {
			var dp = b.getAttribute('data-delopt').split('|'), list2 = arrFor(dp[0]);
			if (list2.length < 2) return;
			var removed = list2.splice(+dp[1], 1)[0];
			// A condition pointing at the removed option is dropped.
			S.form.services.forEach(function (s) { s.details.concat(s.features).forEach(function (q) { if (q.show_if && q.show_if.opt === removed.id && dp[0].indexOf('o:' + s.id + ':') === 0) q.show_if = null; }); });
			render();
			return;
		}
		if (tg.closest('#smi-add-svc')) {
			var taken = S.form.services.map(function (s) { return s.id; });
			var label = {}; label[ui.editLang] = t('newService');
			var ns = { id: uid('svc', taken), on: 0, icon: 'form', label: label, desc: {}, details_title: {}, details_sub: {}, features_title: {}, features_sub: {}, budget: [{ id: 'o1', label: { el: 'Δεν ξέρω ακόμα', en: 'Not sure yet' } }], details: [], features: [] };
			var otherAt = S.form.services.map(function (s) { return s.id; }).indexOf('other');
			if (otherAt > -1) S.form.services.splice(otherAt, 0, ns); else S.form.services.push(ns);
			ui.editSvc = ns.id;
			render();
			toast(t('newServiceHidden'));
			return;
		}
		if ((b = tg.closest('#smi-del-svc'))) {
			if (!arm(b)) return;
			var at = S.form.services.map(function (s) { return s.id; }).indexOf(ui.editSvc);
			if (at > -1) S.form.services.splice(at, 1);
			ui.editSvc = S.form.services.length ? S.form.services[0].id : '__common';
			render();
			return;
		}

		// Texts
		if ((b = tg.closest('[data-reset]'))) {
			var key = b.getAttribute('data-reset');
			S.texts[key] = clone(B.defaults.texts[key]);
			render();
			return;
		}

		// Notifications
		if ((b = tg.closest('[data-deltag]'))) { S.notify.recipients.splice(+b.getAttribute('data-deltag'), 1); render(); $('smi-tag-input').focus(); return; }
		if ((b = tg.closest('#smi-test-mail'))) {
			if (dirty()) { toast(t('saveFirst'), true); return; }
			b.disabled = true;
			ajax('smi_test_mail', {}).then(function (d) { b.disabled = false; toast(tf('testSent', d.to.join(', '))); }).catch(function (er) { b.disabled = false; toast(t('testFailed'), true); });
			return;
		}

		// Turnstile
		if (tg.closest('#smi-ts-clear')) { S.turnstile.secret = ''; S.turnstile.has_secret = false; S.turnstile.clear_secret = 1; render(); return; }

		// Floating button
		if ((b = tg.closest('[data-fpos]'))) { S.float.position = b.getAttribute('data-fpos'); render(); return; }
		if ((b = tg.closest('[data-fexdel]'))) { S.float.exclude.splice(+b.getAttribute('data-fexdel'), 1); render(); return; }

		// PDF
		if (tg.closest('#smi-logo-pick')) { pickLogo(); return; }
		if (tg.closest('#smi-logo-remove')) { S.pdf.logo_id = 0; logoUrl = B.logoDefault; render(); return; }
		if ((b = tg.closest('[data-sample]'))) {
			if (B.serverPdf) { postDownload('smi_pdf_sample', { settings: JSON.stringify(S), lang: b.getAttribute('data-sample') }); return; }
			b.disabled = true;
			window.SmartInquiryPDF.download(samplePdf(b.getAttribute('data-sample'))).then(function () { b.disabled = false; }, function () { b.disabled = false; });
			return;
		}
	});

	function newOpt(taken) {
		var id = 'o1', n = 1;
		while (taken.indexOf(id) > -1) id = 'o' + (++n);
		var label = {};
		label[ui.editLang] = tf('optionN', n);
		return { id: id, label: label };
	}
	/** Two starting choices for a new question, with different ids. */
	function twoOpts() {
		var a = newOpt([]);
		return [a, newOpt([a.id])];
	}
	/** After reordering, a condition may only point at an earlier single-choice question. */
	function fixShowIf(arr) {
		arr.forEach(function (q, i) {
			if (!q.show_if) return;
			var ok = arr.slice(0, i).some(function (p) { return p.id === q.show_if.q && p.type === 'single'; });
			if (!ok) q.show_if = null;
		});
	}

	var mediaFrame = null;
	function pickLogo() {
		if (!window.wp || !wp.media) return;
		if (!mediaFrame) {
			mediaFrame = wp.media({ title: t('chooseLogo'), button: { text: t('useLogo') }, library: { type: 'image' }, multiple: false });
			mediaFrame.on('select', function () {
				var a = mediaFrame.state().get('selection').first().toJSON();
				S.pdf.logo_id = a.id;
				logoUrl = (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url);
				render();
			});
		}
		mediaFrame.open();
	}

	var searchTimer;
	document.addEventListener('input', function (e) {
		var el = e.target;
		if (!mount.contains(el)) return;
		if (el.id === 'smi-q') {
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () { ui.q = el.value; ui.page = 1; ui.sel = {}; loadList(); }, 300);
			return;
		}
		var L = ui.editLang;
		var bind = el.getAttribute('data-bind');
		if (bind) {
			var parts = bind.split('.');
			if (parts[0] === 'svc') {
				var s = svcById(ui.editSvc);
				s[parts[1]] = s[parts[1]] || {};
				s[parts[1]][L] = el.value;
				if (parts[1] === 'label') {
					var it = main.querySelector('[data-editsvc="' + s.id + '"] strong');
					if (it) it.textContent = el.value || t('untitled');
				}
			} else if (parts[0] === 'q') {
				var c = curQ(el);
				c.q[parts[1]] = c.q[parts[1]] || {};
				c.q[parts[1]][L] = el.value;
				if (parts[1] === 'label') el.closest('.smi-qcard').querySelector('.smi-qcard__title').textContent = el.value || t('untitled');
			} else if (parts[0] === 'co') {
				S.pdf.company[parts[1]] = el.value;
			} else if (bind === 'notify.subject') {
				S.notify.subject = el.value;
			} else if (bind === 'ref_prefix') {
				S.ref_prefix = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
			}
			updateSaveState();
			return;
		}
		if (el.hasAttribute('data-opt')) {
			var p = el.getAttribute('data-opt').split('|');
			var o = arrFor(p[0])[+p[1]];
			o.label = o.label || {};
			o.label[L] = el.value;
			updateSaveState();
			return;
		}
		if (el.hasAttribute('data-text')) {
			var tp = el.getAttribute('data-text').split('|');
			S.texts[tp[0]] = S.texts[tp[0]] || {};
			S.texts[tp[0]][tp[1]] = el.value;
			updateSaveState();
			return;
		}
		if (el.hasAttribute('data-footer')) {
			S.pdf.footer[el.getAttribute('data-footer')] = el.value;
			updateSaveState();
			return;
		}
		if (el.hasAttribute('data-access')) {
			S.access.slug = cleanSlug(el.value);
			$('smi-entry').innerHTML = entryLinkHtml();
			updateSaveState();
			return;
		}
		if (el.hasAttribute('data-ts')) {
			S.turnstile[el.getAttribute('data-ts')] = el.value.trim();
			if (el.getAttribute('data-ts') === 'secret' && el.value.trim()) delete S.turnstile.clear_secret;
			updateSaveState();
			return;
		}
		if (el.hasAttribute('data-flabel')) {
			S.float.label[el.getAttribute('data-flabel')] = el.value;
			syncFloatDemo();
			updateSaveState();
			return;
		}
		if (el.hasAttribute('data-color') || el.hasAttribute('data-colorhex')) {
			var key = el.getAttribute('data-color') || el.getAttribute('data-colorhex');
			var v = el.value.trim();
			if (el.hasAttribute('data-colorhex') && v && v[0] !== '#') v = '#' + v;
			if (!/^#[0-9a-f]{6}$/i.test(v)) return;
			S.look[key] = v.toLowerCase();
			var other = main.querySelector(el.hasAttribute('data-color') ? '[data-colorhex="' + key + '"]' : '[data-color="' + key + '"]');
			if (other) other.value = el.hasAttribute('data-color') ? v.toUpperCase() : v.toLowerCase();
			var pv = $('smi-look-preview');
			if (pv) {
				pv.querySelector('.demo').style.background = S.look.accent;
				pv.querySelector('.demo').style.color = S.look.accent_text;
				pv.querySelector('.panel').style.background = S.look.panel;
			}
			updateSaveState();
			return;
		}
		if (el.hasAttribute('data-fcolor') || el.hasAttribute('data-fcolorhex')) {
			var fk = el.getAttribute('data-fcolor') || el.getAttribute('data-fcolorhex');
			var fv = el.value.trim();
			if (el.hasAttribute('data-fcolorhex') && fv && fv[0] !== '#') fv = '#' + fv;
			if (!/^#[0-9a-f]{6}$/i.test(fv)) return;
			S.float[fk] = fv.toLowerCase();
			var twin = main.querySelector(el.hasAttribute('data-fcolor') ? '[data-fcolorhex="' + fk + '"]' : '[data-fcolor="' + fk + '"]');
			if (twin) twin.value = el.hasAttribute('data-fcolor') ? fv.toUpperCase() : fv.toLowerCase();
			syncFloatDemo();
			updateSaveState();
		}
	});

	document.addEventListener('change', function (e) {
		var el = e.target;
		if (!mount.contains(el) && !drawer.contains(el)) return;
		if (el.hasAttribute('data-sel')) { ui.sel[el.getAttribute('data-sel')] = el.checked; syncSelection(); return; }
		if (el.id === 'smi-sel-all') { list.rows.forEach(function (r) { ui.sel[r.id] = el.checked; }); syncSelection(); return; }
		if (el.id === 'smi-f-svc') { ui.svc = el.value; ui.page = 1; renderNav(); loadList(); return; }
		if (el.id === 'smi-f-lang') { ui.lang = el.value; ui.page = 1; loadList(); return; }
		if (el.id === 'smi-bulk-status' && el.value) {
			var ids = selectedIds(), st = el.value;
			ajax('smi_bulk', { ids: ids.join(','), do: st }).then(function (d) { ui.sel = {}; toast(tf('nChanged', d.n, statusLabel(st))); loadList(); }).catch(function (er) { toast(er.message, true); });
			return;
		}
		if (el.id === 'smi-d-status' && drawerData) {
			var r = drawerData, val = el.value;
			ajax('smi_update', { id: r.id, status: val }).then(function () {
				r.status = val;
				var pill = $('smi-d-pill');
				pill.className = 'smi-pill smi-pill--' + val;
				pill.textContent = statusLabel(val);
				toast(tf('statusNow', statusLabel(val)));
				loadList();
			}).catch(function (er) { toast(er.message, true); });
			return;
		}
		if (el.id === 'smi-d-notes' && drawerData) {
			var rr = drawerData;
			ajax('smi_update', { id: rr.id, notes: el.value }).then(function () { rr.notes = el.value; toast(t('noteSaved')); }).catch(function (er) { toast(er.message, true); });
			return;
		}
		// Settings
		if (el.id === 'smi-svc-on') { svcById(ui.editSvc).on = el.checked ? 1 : 0; render(); return; }
		if (el.hasAttribute('data-qflag')) { var c = curQ(el); c.q[el.getAttribute('data-qflag')] = el.checked ? 1 : 0; render(); return; }
		if (el.hasAttribute('data-showif')) {
			var cq = curQ(el);
			if (el.getAttribute('data-showif') === 'q') {
				var parent = cq.svc[cq.step].filter(function (p) { return p.id === el.value; })[0];
				cq.q.show_if = parent ? { q: parent.id, opt: parent.opts[0].id } : null;
			} else if (cq.q.show_if) {
				cq.q.show_if.opt = el.value;
			}
			render();
			return;
		}
		if (el.id === 'smi-n-on') { S.notify.enabled = el.checked ? 1 : 0; updateSaveState(); return; }
		if (el.id === 'smi-n-attach') { S.notify.attach = el.checked ? 1 : 0; updateSaveState(); return; }
		if (el.hasAttribute('data-ppage')) { S.privacy.pages[el.getAttribute('data-ppage')] = +el.value; if (!+el.value) delete S.privacy.pages[el.getAttribute('data-ppage')]; updateSaveState(); return; }
		if (el.id === 'smi-retention') { S.privacy.retention = +el.value; updateSaveState(); return; }
		if (el.id === 'smi-store-ip') { S.privacy.store_ip = el.checked ? 1 : 0; updateSaveState(); return; }
		if (el.id === 'smi-ts-on') { S.turnstile.enabled = el.checked ? 1 : 0; updateSaveState(); return; }
		if (el.id === 'smi-desk-on') {
			if (!el.checked) { desk.on = false; deskSave(); deskPaint(); return; }
			var st = deskState();
			var turnOn = function (perm) {
				if (perm !== 'granted') { desk.on = false; deskPaint(); toast(t('deskBlocked'), true); return; }
				desk.on = true;
				deskSave();
				deskPaint();
				if (desk.sound) deskSound(); // also unlocks sound for this page
				toast(t('deskOnToast'));
			};
			if (st === 'granted') turnOn('granted');
			else if (st === 'default') Notification.requestPermission().then(turnOn, function () { turnOn('denied'); });
			else turnOn('denied');
			return;
		}
		if (el.id === 'smi-desk-sound') { desk.sound = el.checked; if (desk.sound) deskSound(); deskSave(); deskPaint(); return; }
		if (el.name === 'smi-tone') { desk.tone = el.value; deskSound(); deskSave(); return; }
		if (el.id === 'smi-f-on') { S.float.enabled = el.checked ? 1 : 0; render(); return; }
		if (el.id === 'smi-f-mobile') { S.float.mobile = el.value; updateSaveState(); return; }
		if (el.id === 'smi-f-exclude' && +el.value) { S.float.exclude.push(+el.value); render(); $('smi-f-exclude').focus(); return; }
	});

	document.addEventListener('toggle', function (e) {
		var d = e.target;
		if (d.matches && d.matches('details.smi-group')) openGroups[d.getAttribute('data-group')] = d.open;
	}, true);

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			if (!drawer.hidden) { closeDrawer(); return; }
			if (ui.filterOpen) { ui.filterOpen = false; var p = $('smi-pop'); if (p) p.hidden = true; return; }
		}
		var el = e.target;
		if (el.id === 'smi-tag-input' && (e.key === 'Enter' || e.key === ',')) {
			e.preventDefault();
			var v = el.value.trim().replace(/,$/, '');
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { toast(t('badEmail'), true); return; }
			if (S.notify.recipients.indexOf(v) < 0) S.notify.recipients.push(v);
			render();
			$('smi-tag-input').focus();
			return;
		}
		if ((e.key === 'Enter' || e.key === ' ') && el.matches && el.matches('tr[data-row], [data-toggleq]')) {
			e.preventDefault();
			el.click();
		}
	});

	/* ---------- Drag and drop: services, questions and options (by their grip) ---------- */
	var drag = null;
	document.addEventListener('dragstart', function (e) {
		var grip = e.target.closest && e.target.closest('.smi-grip');
		var item = grip && grip.closest('[data-dnd]');
		if (!item) return;
		drag = { group: item.getAttribute('data-dnd'), idx: +item.getAttribute('data-idx') };
		e.dataTransfer.effectAllowed = 'move';
		try { e.dataTransfer.setData('text/plain', drag.group); e.dataTransfer.setDragImage(item, 16, 16); } catch (err) {}
	});
	document.addEventListener('dragover', function (e) {
		if (!drag) return;
		var item = e.target.closest && e.target.closest('[data-dnd="' + drag.group + '"]');
		if (!item) return;
		e.preventDefault();
		Array.prototype.forEach.call(main.querySelectorAll('.is-over'), function (x) { if (x !== item) x.classList.remove('is-over'); });
		item.classList.add('is-over');
	});
	document.addEventListener('drop', function (e) {
		if (!drag) return;
		var item = e.target.closest && e.target.closest('[data-dnd="' + drag.group + '"]');
		if (!item) return;
		e.preventDefault();
		var arr = arrFor(drag.group), to = +item.getAttribute('data-idx');
		if (arr && to !== drag.idx) {
			arr.splice(to, 0, arr.splice(drag.idx, 1)[0]);
			if (drag.group.indexOf('q:') === 0) { fixShowIf(arr); ui.openQ = null; }
		}
		drag = null;
		render();
	});
	document.addEventListener('dragend', function () {
		drag = null;
		Array.prototype.forEach.call(main.querySelectorAll('.is-over'), function (x) { x.classList.remove('is-over'); });
	});

	/* ---------- Start: each screen has its own address (#form, #texts…), so it can be bookmarked ---------- */
	function viewFromHash() {
		var h = window.location.hash.replace('#', '');
		return VIEWS.some(function (v) { return v.id === h; }) ? h : null;
	}
	var origRender = render;
	render = function () {
		var want = '#' + ui.view;
		if (window.location.hash !== want) {
			try { history.replaceState(null, '', window.location.pathname + window.location.search + (ui.view === 'requests' ? '' : want)); } catch (e) {}
		}
		origRender();
	};
	window.addEventListener('hashchange', function () {
		var v = viewFromHash();
		if (v && v !== ui.view) { ui.view = v; render(); }
	});
	ui.view = viewFromHash() || 'requests';
	render();
	// The side menu shows counts on every screen; the Requests screen loads them itself.
	if (ui.view !== 'requests') loadList();
	// New requests: the list and counts refresh on their own, and a desktop notification goes out if switched on.
	deskPoll();
	if (B.openId) openDrawer(B.openId);
})();
