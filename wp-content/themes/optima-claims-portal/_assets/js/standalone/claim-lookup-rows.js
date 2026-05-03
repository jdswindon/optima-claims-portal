/**
 * Claim upload: add/remove image rows (lookup block).
 * Loaded only via wp_enqueue_script — NOT in _assets/js/core/ (those files are bundled into production-dist.js).
 * Config: window.optimaClaimLookupRows from wp_localize_script.
 */
(function () {
	var cfg = typeof window.optimaClaimLookupRows === 'object' && window.optimaClaimLookupRows
		? window.optimaClaimLookupRows
		: {};
	var rowLabel = cfg.rowLabel || 'Image';
	var maxRows = parseInt(String(cfg.maxRows || '15'), 10) || 15;
	var removeAria = cfg.removeAria || 'Remove this image slot';

	var wrap = document.getElementById('claim-image-rows');
	var addBtn = document.getElementById('optima-add-claim-image-row');
	if (!wrap || !addBtn) {
		return;
	}

	function setRowIndex(row, index) {
		row.dataset.rowIndex = String(index);
		var h = row.querySelector('.claim-image-row-heading');
		if (h) {
			h.textContent = rowLabel + ' ' + (index + 1);
		}
		var file = row.querySelector('input[type=file]');
		var ta = row.querySelector('textarea');
		var idBase = 'claim_row_' + index;
		if (file) {
			file.name = 'claim_image_files[' + index + ']';
			file.id = idBase + '_file';
		}
		if (ta) {
			ta.name = 'claim_image_descriptions[' + index + ']';
			ta.id = idBase + '_desc';
		}
		var labels = row.querySelectorAll('label');
		if (labels[0] && file) {
			labels[0].setAttribute('for', file.id);
		}
		if (labels[1] && ta) {
			labels[1].setAttribute('for', ta.id);
		}
	}

	function updateRemoveButtons() {
		var rows = wrap.querySelectorAll('.claim-image-row');
		var multi = rows.length > 1;
		var i;
		for (i = 0; i < rows.length; i++) {
			var row = rows[i];
			var btn = row.querySelector('.claim-image-row-remove');
			if (!btn) {
				continue;
			}
			if (multi) {
				btn.removeAttribute('hidden');
				btn.setAttribute('aria-label', removeAria);
			} else {
				btn.setAttribute('hidden', 'hidden');
				btn.removeAttribute('aria-label');
			}
		}
	}

	function renumberRows() {
		var rows = wrap.querySelectorAll('.claim-image-row');
		var i;
		for (i = 0; i < rows.length; i++) {
			setRowIndex(rows[i], i);
		}
		updateRemoveButtons();
	}

	function matchesSel(el, selector) {
		if (!el || el.nodeType !== 1) {
			return false;
		}
		var m = el.matches || el.msMatchesSelector || el.webkitMatchesSelector;
		return m ? m.call(el, selector) : false;
	}

	function closestEl(el, selector) {
		while (el && el.nodeType === 1) {
			if (matchesSel(el, selector)) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	wrap.addEventListener('click', function (e) {
		var t = e.target;
		if (t.nodeType !== 1) {
			t = t.parentElement;
		}
		if (!t) {
			return;
		}
		var btn = typeof t.closest === 'function'
			? t.closest('.claim-image-row-remove')
			: closestEl(t, '.claim-image-row-remove');
		if (!btn || !wrap.contains(btn)) {
			return;
		}
		var rows = wrap.querySelectorAll('.claim-image-row');
		if (rows.length <= 1) {
			return;
		}
		var row = typeof btn.closest === 'function'
			? btn.closest('.claim-image-row')
			: closestEl(btn, '.claim-image-row');
		if (row && row.parentNode === wrap) {
			row.remove();
			renumberRows();
		}
	});

	addBtn.addEventListener('click', function () {
		var rows = wrap.querySelectorAll('.claim-image-row');
		if (rows.length >= maxRows) {
			return;
		}
		var proto = rows[0];
		var row = proto.cloneNode(true);
		var f = row.querySelector('input[type=file]');
		if (f) {
			f.value = '';
		}
		var ta = row.querySelector('textarea');
		if (ta) {
			ta.value = '';
		}
		wrap.appendChild(row);
		renumberRows();
	});

	updateRemoveButtons();
})();
