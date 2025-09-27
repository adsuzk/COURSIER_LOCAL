// Auto-refresh for app_updates and applications pages to reflect latest APKs
(function(){
  const REFRESH_MS = 5000;

  function shouldSkipRefresh(el){
    // Skip sections explicitly marked or containing file inputs (to avoid clearing selections)
    if (!el) return true;
    if (el.hasAttribute('data-no-refresh')) return true;
    if (el.querySelector && el.querySelector('input[type="file"]')) return true;
    return false;
  }

  function refreshIfVisible(){
    if (document.hidden) return;
    fetch(window.location.href, {cache: 'no-store'})
      .then(r=>r.text())
      .then(html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const blocks = ['.card', '.data-table', '.stats-grid'];
        blocks.forEach(sel => {
          const newEls = Array.from(doc.querySelectorAll(sel));
          const curEls = Array.from(document.querySelectorAll(sel));
          const count = Math.min(newEls.length, curEls.length);
          for (let i = 0; i < count; i++) {
            const curEl = curEls[i];
            const newEl = newEls[i];
            if (!curEl || !newEl) continue;
            if (shouldSkipRefresh(curEl)) continue;
            curEl.innerHTML = newEl.innerHTML;
          }
        });
      })
      .catch(()=>{});
  }

  document.addEventListener('visibilitychange', refreshIfVisible);
  setInterval(refreshIfVisible, REFRESH_MS);
})();