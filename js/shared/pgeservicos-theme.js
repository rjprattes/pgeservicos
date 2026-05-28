(function () {
  "use strict";

  const pageSelector = [
    ".pgeservicos-container",
    ".pgegestor-page",
    ".pgeservicos-formcreator-page .pgeservicos-formcreator-shell",
  ].join(",");

  function parseRgb(value) {
    const match = String(value || "").match(/rgba?\(([^)]+)\)/i);

    if (!match) {
      return null;
    }

    const channels = match[1]
      .split(",")
      .slice(0, 3)
      .map(function (part) {
        return Number.parseFloat(part.trim());
      });

    return channels.length === 3 && channels.every(Number.isFinite)
      ? channels
      : null;
  }

  function luminance(rgb) {
    const linear = rgb.map(function (channel) {
      const value = channel / 255;
      return value <= 0.03928
        ? value / 12.92
        : Math.pow((value + 0.055) / 1.055, 2.4);
    });

    return (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2]);
  }

  function contrast(first, second) {
    const lighter = Math.max(luminance(first), luminance(second));
    const darker = Math.min(luminance(first), luminance(second));

    return (lighter + 0.05) / (darker + 0.05);
  }

  function readableTextColor(background, candidate) {
    const bg = parseRgb(background);

    if (!bg) {
      return candidate || "";
    }

    const parsedCandidate = parseRgb(candidate);

    if (parsedCandidate && contrast(bg, parsedCandidate) >= 4.5) {
      return candidate;
    }

    const dark = [30, 41, 59];
    const light = [255, 255, 255];

    return contrast(bg, dark) >= contrast(bg, light) ? "#1e293b" : "#ffffff";
  }

  function isTransparentBackground(value) {
    return !value || value === "rgba(0, 0, 0, 0)" || value === "transparent";
  }

  function isVisibleElement(element) {
    if (!element) {
      return false;
    }

    const rect = element.getBoundingClientRect();
    const style = window.getComputedStyle(element);

    return rect.width > 0
      && rect.height > 0
      && style.display !== "none"
      && style.visibility !== "hidden";
  }

  function menuCandidates() {
    const selectors = [
      ".topbar.navbar",
      ".topbar",
      "nav.topbar",
      "header.topbar",
      "aside.sidebar",
      ".navbar-vertical.sidebar",
      ".sidebar.navbar",
    ];
    const seen = new Set();
    const candidates = [];

    selectors.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (element) {
        if (!seen.has(element)) {
          seen.add(element);
          candidates.push(element);
        }
      });
    });

    return candidates;
  }

  function findMenuElement() {
    const scored = menuCandidates()
      .filter(isVisibleElement)
      .map(function (element) {
        const rect = element.getBoundingClientRect();
        const style = window.getComputedStyle(element);
        const background = style.backgroundColor;
        let score = 0;

        if (!isTransparentBackground(background)) {
          score += 100;
        }

        if (element.matches(".topbar, .topbar.navbar, nav.topbar, header.topbar")) {
          score += 40;
        }

        if (element.matches("aside.sidebar, .navbar-vertical.sidebar, .sidebar.navbar")) {
          score += 30;
        }

        if (rect.top <= 120) {
          score += 10;
        }

        score += Math.min(rect.width, 1200) / 1200;

        return { element, score, background };
      })
      .filter(function (candidate) {
        return !isTransparentBackground(candidate.background);
      })
      .sort(function (first, second) {
        return second.score - first.score;
      });

    return scored.length ? scored[0].element : null;
  }

  function applyMenuTheme() {
    const menu = findMenuElement();

    if (!menu) {
      return;
    }

    const menuStyle = window.getComputedStyle(menu);
    const background = menuStyle.backgroundColor;
    const color = readableTextColor(background, menuStyle.color);

    if (isTransparentBackground(background)) {
      return;
    }

    document.querySelectorAll(pageSelector).forEach(function (page) {
      page.style.setProperty("--pgeservicos-menu-bg", background);
      page.style.setProperty("--pgeservicos-current-sidebar-bg", background);

      if (color) {
        page.style.setProperty("--pgeservicos-menu-color", color);
        page.style.setProperty("--pgeservicos-current-sidebar-color", color);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", applyMenuTheme);
  } else {
    applyMenuTheme();
  }

  window.addEventListener("resize", applyMenuTheme);
  window.setTimeout(applyMenuTheme, 250);
})();
