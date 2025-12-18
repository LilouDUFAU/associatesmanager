/* Associates Manager common JS */
(function(window, document){
	if (window.__AM_LOADED__) return; // prevent double init if included twice
	window.__AM_LOADED__ = true;

	function $(sel, ctx){ return (ctx||document).querySelector(sel); }
	function $all(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }

	function postFormEncoded(url, params){
		var body = Object.keys(params).map(function(k){
			return encodeURIComponent(k) + '=' + encodeURIComponent(params[k] == null ? '' : String(params[k]));
		}).join('&');
		var headers = { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' };
		// Always push CSRF token in both body and headers (dual casing for safety)
		if (params && params._glpi_csrf_token) {
			headers['X-GLPI-CSRF-Token'] = params._glpi_csrf_token;
			headers['X-Glpi-Csrf-Token'] = params._glpi_csrf_token;
		}
		return fetch(url, { method: 'POST', headers: headers, body: body, credentials: 'same-origin' });
	}

	function wireNbPartTotalForms(){
		// Let the native form submission handle CSRF; no JS interception needed
		$all('.am-nbparttotal-form').forEach(function(wrapper){
			if (wrapper.__wired__) return;
			wrapper.__wired__ = true;
		});
	}

	// Provide RNE modal helpers if not already declared inline
	if (typeof window.showRneSyncModal !== 'function') {
		window.showRneSyncModal = function(supplierId, siren){
			var input = document.getElementById('modal_siren');
			if (input) input.value = siren || '';
			var modal = document.getElementById('rneModal');
			if (modal){ modal.style.display = 'block'; modal.classList.add('show'); }
		};
	}
	if (typeof window.hideRneSyncModal !== 'function') {
		window.hideRneSyncModal = function(){
			var modal = document.getElementById('rneModal');
			if (modal){ modal.style.display = 'none'; modal.classList.remove('show'); }
		};
	}

	// Close modal when clicking on backdrop
	function wireBackdropClose(){
		window.addEventListener('click', function(event){
			var modal = document.getElementById('rneModal');
			if (modal && event.target === modal){
				try { window.hideRneSyncModal(); } catch(_){}
			}
		});
	}

	function init(){
		function wireNbPartTotalForms(){
			// Let the native form submission handle CSRF; no JS interception needed
			$all('.am-nbparttotal-form').forEach(function(wrapper){
				if (wrapper.__wired__) return;
				wrapper.__wired__ = true;
			});
		}
		init();

	}
