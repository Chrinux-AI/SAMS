// Immediately apply saved theme to prevent flash of wrong colors
(function () {
  var t = localStorage.getItem('sams_theme');
  if (t) {
    document.documentElement.setAttribute('data-theme', t);
  }

  function ensureFaviconLinks() {
    var head = document.head;
    if (!head) return;

    if (head.querySelector('link[data-sams-favicon="1"]')) return;

    var base = '/attendance/assets/images/icons/';
    var links = [
      { rel: 'icon', type: 'image/svg+xml', href: base + 'favicon.svg' },
      { rel: 'icon', type: 'image/svg+xml', sizes: '32x32', href: base + 'icon-32x32.svg' },
      { rel: 'apple-touch-icon', href: base + 'icon-192x192.svg' }
    ];

    links.forEach(function (def) {
      var link = document.createElement('link');
      link.setAttribute('data-sams-favicon', '1');
      link.rel = def.rel;
      if (def.type) link.type = def.type;
      if (def.sizes) link.sizes = def.sizes;
      link.href = def.href;
      head.appendChild(link);
    });

    if (!head.querySelector('meta[name="theme-color"]')) {
      var meta = document.createElement('meta');
      meta.name = 'theme-color';
      meta.content = '#4F46E5';
      meta.setAttribute('data-sams-favicon', '1');
      head.appendChild(meta);
    }
  }

  ensureFaviconLinks();
})();
