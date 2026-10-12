/*
 * SmartInquiry PDF: draws the request on A4 canvases with the browser's own fonts (so Greek always renders),
 * then wraps the pages as JPEG images in a minimal PDF. No libraries, nothing sent anywhere.
 *
 * SmartInquiryPDF.download({
 *   accent, logo (URL, same site), company: { name, address, phone, email, website, vat },
 *   title, ref, date, sections: [ { title, rows: [ [label, value], … ] } ], footer, filename
 * })
 */
(function () {
	'use strict';

	var PT_W = 595.28, PT_H = 841.89;          // A4 in points
	var SCALE = 2.2;                           // ≈ 160 dpi
	var W = Math.round(PT_W * SCALE), H = Math.round(PT_H * SCALE);
	var M = 46;                                // side margin (pt)
	var TOP = 46, BOTTOM = 56;

	function fontFamily() {
		try {
			var f = window.getComputedStyle(document.body).fontFamily;
			if (f) return f + ', "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
		} catch (e) {}
		return '"Segoe UI", Roboto, Helvetica, Arial, sans-serif';
	}

	function loadImage(url) {
		return new Promise(function (resolve) {
			if (!url) return resolve(null);
			var img = new Image();
			var done = false;
			var finish = function (v) { if (!done) { done = true; resolve(v); } };
			img.onload = function () { finish(img.naturalWidth ? img : null); };
			img.onerror = function () { finish(null); };
			setTimeout(function () { finish(null); }, 4000);
			img.src = url;
		});
	}

	/** Splits text into lines that fit maxWidth (points); long words are broken by character. */
	function wrap(ctx, text, maxWidth) {
		var out = [];
		String(text == null ? '' : text).split(/\r?\n/).forEach(function (para) {
			var words = para.split(/\s+/).filter(Boolean);
			var line = '';
			if (!words.length) { out.push(''); return; }
			words.forEach(function (w) {
				var test = line ? line + ' ' + w : w;
				if (ctx.measureText(test).width <= maxWidth) { line = test; return; }
				if (line) out.push(line);
				if (ctx.measureText(w).width <= maxWidth) { line = w; return; }
				var part = '';
				Array.from(w).forEach(function (ch) {
					if (ctx.measureText(part + ch).width > maxWidth && part) { out.push(part); part = ch; } else { part += ch; }
				});
				line = part;
			});
			if (line) out.push(line);
		});
		return out;
	}

	function newPage() {
		var c = document.createElement('canvas');
		c.width = W;
		c.height = H;
		var ctx = c.getContext('2d');
		ctx.fillStyle = '#ffffff';
		ctx.fillRect(0, 0, W, H);
		ctx.scale(SCALE, SCALE);
		ctx.textBaseline = 'alphabetic';
		return { canvas: c, ctx: ctx };
	}

	/** Uppercase in the page language (Greek then drops its accents); an odd language code falls back to plain uppercase. */
	function upper(s, lang) {
		s = String(s || '');
		try { return s.toLocaleUpperCase(lang || undefined); } catch (e) { return s.toUpperCase(); }
	}

	function setFont(ctx, size, weight) {
		ctx.font = (weight || 400) + ' ' + size + 'px ' + FONT;
	}
	var FONT = '';

	function render(doc, logo) {
		FONT = fontFamily();
		var pages = [];
		var page = newPage();
		var ctx = page.ctx;
		pages.push(page);
		var y = TOP;
		var right = PT_W - M;
		var accent = /^#[0-9a-f]{6}$/i.test(doc.accent || '') ? doc.accent : '#46e9d4';

		// Header: logo (or company name) left, title/ref/date right.
		var headTop = y;
		if (logo) {
			var lh = 34, lw = logo.naturalWidth / logo.naturalHeight * lh;
			if (lw > 170) { lw = 170; lh = lw * logo.naturalHeight / logo.naturalWidth; }
			ctx.drawImage(logo, M, y, lw, lh);
			y += lh + 10;
		} else if (doc.company && doc.company.name) {
			setFont(ctx, 17, 700);
			ctx.fillStyle = '#101828';
			ctx.fillText(doc.company.name, M, y + 16);
			y += 26;
		}
		var co = doc.company || {};
		var lines = [co.name && logo ? co.name : '', co.address, [co.phone, co.email].filter(Boolean).join(' · '), co.website, co.vat].filter(Boolean);
		setFont(ctx, 8.5, 400);
		ctx.fillStyle = '#667085';
		lines.forEach(function (l) { ctx.fillText(l, M, y + 8); y += 12; });

		ctx.textAlign = 'right';
		setFont(ctx, 16, 700);
		ctx.fillStyle = '#101828';
		ctx.fillText(doc.title || '', right, headTop + 15);
		setFont(ctx, 9.5, 400);
		ctx.fillStyle = '#667085';
		if (doc.ref) ctx.fillText(doc.ref, right, headTop + 31);
		if (doc.date) ctx.fillText(doc.date, right, headTop + 45);
		ctx.textAlign = 'left';

		y = Math.max(y, headTop + 52) + 10;
		ctx.fillStyle = accent;
		ctx.fillRect(M, y, PT_W - 2 * M, 2.2);
		y += 26;

		var labelW = (PT_W - 2 * M) * 0.38;
		var valueX = M + labelW + 12;
		var valueW = right - valueX;

		function ensure(space) {
			if (y + space <= PT_H - BOTTOM) return;
			page = newPage();
			ctx = page.ctx;
			pages.push(page);
			y = TOP;
		}

		function rowHeight(row) {
			setFont(ctx, 9.5, 400);
			var l = wrap(ctx, row[0], labelW).length;
			setFont(ctx, 9.5, 600);
			var v = wrap(ctx, row[1] || '—', valueW).length;
			return Math.max(l, v) * 13.5 + 12;
		}

		var LINE = 13.5;
		var FIRST = 16;                            // first baseline below the row's top edge
		var TWO_LINES = FIRST + LINE + 12;         // enough to start a row that will continue on the next page

		(doc.sections || []).forEach(function (sec) {
			if (!sec.rows || !sec.rows.length) return;
			// A section title never stays alone at the bottom of a page: it moves with (the start of) its first row.
			ensure(8 + Math.min(rowHeight(sec.rows[0]), TWO_LINES));
			setFont(ctx, 8, 700);
			ctx.fillStyle = '#667085';
			if ('letterSpacing' in ctx) ctx.letterSpacing = '1px';
			ctx.fillText(upper(sec.title, doc.lang), M, y);
			if ('letterSpacing' in ctx) ctx.letterSpacing = '0px';
			y += 8;
			sec.rows.forEach(function (row) {
				setFont(ctx, 9.5, 400);
				var l = wrap(ctx, row[0], labelW);
				setFont(ctx, 9.5, 600);
				var v = wrap(ctx, row[1] || '—', valueW);
				var n = Math.max(l.length, v.length);
				var h = n * LINE + 12;
				// Short rows stay in one piece; a long text (e.g. notes) continues on the next page line by line.
				ensure(h <= (PT_H - TOP - BOTTOM) / 3 ? h : TWO_LINES);
				var ty = y + FIRST;
				for (var i = 0; i < n; i++) {
					if (ty > PT_H - BOTTOM) {
						ensure(PT_H); // always a new page
						ty = y + FIRST - 4;
					}
					if (l[i] != null) {
						setFont(ctx, 9.5, 400);
						ctx.fillStyle = '#667085';
						ctx.fillText(l[i], M, ty);
					}
					if (v[i] != null) {
						setFont(ctx, 9.5, 600);
						ctx.fillStyle = '#101828';
						ctx.fillText(v[i], valueX, ty);
					}
					ty += LINE;
				}
				y = ty - LINE + 9.5; // bottom of the row: last baseline plus the usual gap
				ctx.fillStyle = '#eaecf0';
				ctx.fillRect(M, y, PT_W - 2 * M, 0.7);
			});
			y += 24;
		});

		// Footer on every page.
		pages.forEach(function (p, i) {
			var c = p.ctx;
			c.fillStyle = '#eaecf0';
			c.fillRect(M, PT_H - 40, PT_W - 2 * M, 0.7);
			setFont(c, 8, 400);
			c.fillStyle = '#98a2b3';
			c.textAlign = 'left';
			if (doc.footer) c.fillText(doc.footer, M, PT_H - 26);
			c.textAlign = 'right';
			c.fillText((i + 1) + ' / ' + pages.length, right, PT_H - 26);
			c.textAlign = 'left';
		});
		return pages.map(function (p) { return p.canvas; });
	}

	/* ---------- Minimal PDF writer: one JPEG image per page ---------- */

	function bytesOf(str) {
		var out = new Uint8Array(str.length);
		for (var i = 0; i < str.length; i++) out[i] = str.charCodeAt(i) & 0xff;
		return out;
	}
	function jpegBytes(canvas) {
		var b64 = canvas.toDataURL('image/jpeg', 0.9).split(',')[1];
		var bin = atob(b64);
		var out = new Uint8Array(bin.length);
		for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
		return out;
	}
	/** PDF text string for any Unicode title: UTF-16BE in hex. */
	function pdfText(s) {
		var hex = 'FEFF';
		for (var i = 0; i < s.length; i++) hex += ('000' + s.charCodeAt(i).toString(16)).slice(-4).toUpperCase();
		return '<' + hex + '>';
	}

	function buildPdf(canvases, title) {
		var parts = [];
		var offsets = [];
		var length = 0;
		function push(x) {
			var b = typeof x === 'string' ? bytesOf(x) : x;
			parts.push(b);
			length += b.length;
		}
		function obj(n, body) {
			offsets[n] = length;
			push(n + ' 0 obj\n');
			body();
			push('\nendobj\n');
		}
		push('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n');
		var n = canvases.length;
		// 1 catalog, 2 pages, 3 info, then 3 objects per page: page, content, image.
		var kids = [];
		for (var i = 0; i < n; i++) kids.push((4 + i * 3) + ' 0 R');
		obj(1, function () { push('<< /Type /Catalog /Pages 2 0 R >>'); });
		obj(2, function () { push('<< /Type /Pages /Kids [' + kids.join(' ') + '] /Count ' + n + ' >>'); });
		obj(3, function () { push('<< /Title ' + pdfText(title || 'Request') + ' /Producer (SmartInquiry) >>'); });
		canvases.forEach(function (c, i) {
			var pageN = 4 + i * 3, contentN = pageN + 1, imgN = pageN + 2;
			var content = 'q ' + PT_W + ' 0 0 ' + PT_H + ' 0 0 cm /Im0 Do Q';
			var img = jpegBytes(c);
			obj(pageN, function () {
				push('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + PT_W + ' ' + PT_H + '] /Resources << /XObject << /Im0 ' + imgN + ' 0 R >> >> /Contents ' + contentN + ' 0 R >>');
			});
			obj(contentN, function () {
				push('<< /Length ' + content.length + ' >>\nstream\n' + content + '\nendstream');
			});
			obj(imgN, function () {
				push('<< /Type /XObject /Subtype /Image /Width ' + c.width + ' /Height ' + c.height + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + img.length + ' >>\nstream\n');
				push(img);
				push('\nendstream');
			});
		});
		var total = 4 + n * 3;
		var xref = length;
		var table = 'xref\n0 ' + total + '\n0000000000 65535 f \n';
		for (var k = 1; k < total; k++) table += ('0000000000' + offsets[k]).slice(-10) + ' 00000 n \n';
		push(table + 'trailer\n<< /Size ' + total + ' /Root 1 0 R /Info 3 0 R >>\nstartxref\n' + xref + '\n%%EOF');
		return new Blob(parts, { type: 'application/pdf' });
	}

	function safeName(s) {
		return String(s || 'request').replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'request';
	}

	function download(doc) {
		return loadImage(doc.logo).then(function (logo) {
			var title = doc.title + (doc.ref ? ' ' + doc.ref : '');
			var blob;
			try {
				blob = buildPdf(render(doc, logo), title);
			} catch (e) {
				// A logo from another domain (e.g. a CDN) "taints" the canvas and the page can't be exported.
				blob = buildPdf(render(doc, null), title);
			}
			var url = URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.href = url;
			a.download = safeName(doc.filename) + '.pdf';
			a.rel = 'noopener';
			document.body.appendChild(a);
			a.click();
			setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 4000);
			return blob;
		});
	}

	window.SmartInquiryPDF = { download: download, render: render, build: buildPdf };
})();
