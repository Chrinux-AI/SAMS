// Immediately apply saved theme to prevent flash of wrong colors
(function () {
  var t = localStorage.getItem("sams_theme");
  if (t !== "light" && t !== "dark") {
    t = "light";
    localStorage.setItem("sams_theme", t);
  }
  document.documentElement.setAttribute("data-theme", t);
  document.documentElement.classList.toggle("dark", t === "dark");
  document.documentElement.classList.toggle("light", t !== "dark");

  function ensureFaviconLinks() {
    var head = document.head;
    if (!head) return;

    var base = "/attendance/assets/images/icons/";
    var isDark = t === "dark";
    var links = [
      {
        rel: "icon",
        type: "image/png",
        href: base + (isDark ? "logo4.png" : "logo5.png"),
      },
      {
        rel: "shortcut icon",
        href: base + (isDark ? "logo4.png" : "logo5.png"),
      },
      {
        rel: "apple-touch-icon",
        href: base + (isDark ? "logo4.png" : "logo5.png"),
      },
    ];

    links.forEach(function (def) {
      var link =
        head.querySelector('link[rel="' + def.rel + '"]') ||
        document.createElement("link");
      link.setAttribute("data-sams-favicon", "1");
      link.rel = def.rel;
      if (def.type) link.type = def.type;
      if (def.sizes) link.sizes = def.sizes;
      link.href = def.href;
      if (!link.parentNode) {
        head.appendChild(link);
      }
    });

    if (!head.querySelector('meta[name="theme-color"]')) {
      var meta = document.createElement("meta");
      meta.name = "theme-color";
      meta.content = "#4F46E5";
      meta.setAttribute("data-sams-favicon", "1");
      head.appendChild(meta);
    }
  }

  ensureFaviconLinks();
})();
