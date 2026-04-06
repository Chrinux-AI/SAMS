/**
 * SAMS Cyberpunk Animations — Developer-exclusive visual effects.
 *
 * Effects:
 *  - Neon glow borders on cards
 *  - Holographic card entrance
 *  - Pulse animation on AI activity
 *  - Glitch text effect on alerts
 *  - Scanline overlay
 *  - Particle background
 *  - Terminal typing effect
 *
 * Auto-activates when data-theme starts with "cyberpunk".
 */
(function () {
  "use strict";

  function isCyberpunk() {
    var t = document.documentElement.getAttribute("data-theme") || "";
    return t.indexOf("cyberpunk") === 0;
  }

  function injectStyles() {
    if (document.getElementById("cyberpunk-fx-css")) return;
    var style = document.createElement("style");
    style.id = "cyberpunk-fx-css";
    style.textContent = [
      /* Neon glow on cards */
      '[data-theme^="cyberpunk"] .stat-card,',
      '[data-theme^="cyberpunk"] .panel,',
      '[data-theme^="cyberpunk"] .sec-stat {',
      "  transition: box-shadow 0.3s ease, border-color 0.3s ease;",
      "}",
      '[data-theme^="cyberpunk"] .stat-card:hover,',
      '[data-theme^="cyberpunk"] .panel:hover,',
      '[data-theme^="cyberpunk"] .sec-stat:hover {',
      "  box-shadow: 0 0 20px var(--primary), 0 0 40px rgba(0,240,255,0.1);",
      "  border-color: var(--primary) !important;",
      "}",

      /* Holographic card entrance */
      "@keyframes holoFadeIn {",
      "  0% { opacity:0; transform:translateY(12px) scale(0.97); filter:blur(4px) brightness(1.4); }",
      "  60% { filter:blur(0) brightness(1.1); }",
      "  100% { opacity:1; transform:translateY(0) scale(1); filter:blur(0) brightness(1); }",
      "}",
      '[data-theme^="cyberpunk"] .fade-in > * {',
      "  animation: holoFadeIn 0.5s ease-out both;",
      "}",
      '[data-theme^="cyberpunk"] .fade-in > *:nth-child(2) { animation-delay: 0.07s; }',
      '[data-theme^="cyberpunk"] .fade-in > *:nth-child(3) { animation-delay: 0.14s; }',
      '[data-theme^="cyberpunk"] .fade-in > *:nth-child(4) { animation-delay: 0.21s; }',
      '[data-theme^="cyberpunk"] .fade-in > *:nth-child(5) { animation-delay: 0.28s; }',
      '[data-theme^="cyberpunk"] .fade-in > *:nth-child(n+6) { animation-delay: 0.35s; }',

      /* Glow pulse for AI activity indicators */
      "@keyframes glowPulse {",
      "  0%, 100% { box-shadow: 0 0 8px var(--primary); }",
      "  50% { box-shadow: 0 0 24px var(--primary), 0 0 48px rgba(0,240,255,0.15); }",
      "}",
      '[data-theme^="cyberpunk"] .ai-pulse {',
      "  animation: glowPulse 2s ease-in-out infinite;",
      "}",

      /* Glitch text effect */
      "@keyframes glitch {",
      "  0% { transform: translate(0); }",
      "  20% { transform: translate(-2px, 1px); }",
      "  40% { transform: translate(2px, -1px); }",
      "  60% { transform: translate(-1px, 2px); }",
      "  80% { transform: translate(1px, -2px); }",
      "  100% { transform: translate(0); }",
      "}",
      '[data-theme^="cyberpunk"] .glitch-text {',
      "  animation: glitch 0.3s ease-in-out;",
      "}",

      /* Scanline overlay */
      '[data-theme^="cyberpunk"] .scanlines::after {',
      '  content: "";',
      "  position: fixed;",
      "  inset: 0;",
      "  pointer-events: none;",
      "  z-index: 9999;",
      "  background: repeating-linear-gradient(",
      "    0deg,",
      "    transparent,",
      "    transparent 2px,",
      "    rgba(0, 0, 0, 0.03) 2px,",
      "    rgba(0, 0, 0, 0.03) 4px",
      "  );",
      "}",

      /* Animated gradient border */
      "@keyframes borderGradient {",
      "  0% { border-image-source: linear-gradient(0deg, var(--primary), transparent); }",
      "  50% { border-image-source: linear-gradient(180deg, var(--primary), transparent); }",
      "  100% { border-image-source: linear-gradient(360deg, var(--primary), transparent); }",
      "}",

      /* Terminal typing cursor */
      "@keyframes blink {",
      "  0%, 100% { opacity: 1; }",
      "  50% { opacity: 0; }",
      "}",
      ".cyber-cursor::after {",
      '  content: "▊";',
      "  animation: blink 1s step-end infinite;",
      "  color: var(--primary);",
      "  margin-left: 2px;",
      "}",
    ].join("\n");
    document.head.appendChild(style);
  }

  // Simple particle background (canvas-based, light on resources)
  function initParticles() {
    if (document.getElementById("cyber-particles")) return;
    var canvas = document.createElement("canvas");
    canvas.id = "cyber-particles";
    canvas.style.cssText =
      "position:fixed;inset:0;z-index:0;pointer-events:none;opacity:0.4;";
    document.body.prepend(canvas);

    var ctx = canvas.getContext("2d");
    var particles = [];
    var MAX = 35;

    function resize() {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener("resize", resize);

    for (var i = 0; i < MAX; i++) {
      particles.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        vx: (Math.random() - 0.5) * 0.4,
        vy: (Math.random() - 0.5) * 0.4,
        r: Math.random() * 1.5 + 0.5,
      });
    }

    var color =
      getComputedStyle(document.documentElement)
        .getPropertyValue("--primary")
        .trim() || "#00f0ff";

    function draw() {
      if (!isCyberpunk()) {
        canvas.style.display = "none";
        return;
      }
      canvas.style.display = "";
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      for (var i = 0; i < particles.length; i++) {
        var p = particles[i];
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0 || p.x > canvas.width) p.vx *= -1;
        if (p.y < 0 || p.y > canvas.height) p.vy *= -1;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
        // Draw connections
        for (var j = i + 1; j < particles.length; j++) {
          var q = particles[j];
          var dx = p.x - q.x,
            dy = p.y - q.y;
          var dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < 140) {
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            ctx.lineTo(q.x, q.y);
            ctx.strokeStyle = color;
            ctx.globalAlpha = 1 - dist / 140;
            ctx.lineWidth = 0.5;
            ctx.stroke();
            ctx.globalAlpha = 1;
          }
        }
      }
      requestAnimationFrame(draw);
    }
    requestAnimationFrame(draw);
  }

  function boot() {
    if (!isCyberpunk()) return;
    injectStyles();
    initParticles();
    // Add scanline class to body
    document.body.classList.add("scanlines");
  }

  // Run on load and observe theme changes
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }

  // Re-check on theme change (MutationObserver on html data-theme)
  var observer = new MutationObserver(function () {
    if (isCyberpunk()) {
      boot();
    } else {
      document.body.classList.remove("scanlines");
      var c = document.getElementById("cyber-particles");
      if (c) c.style.display = "none";
    }
  });
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["data-theme"],
  });
})();
