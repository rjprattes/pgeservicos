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

  function findSidebar() {
    return (
      document.querySelector("aside.sidebar") ||
      document.querySelector(".navbar-vertical.sidebar") ||
      document.querySelector(".sidebar.navbar")
    );
  }

  function applySidebarTheme() {
    const sidebar = findSidebar();

    if (!sidebar) {
      return;
    }

    const sidebarStyle = window.getComputedStyle(sidebar);
    const background = sidebarStyle.backgroundColor;
    const color = readableTextColor(background, sidebarStyle.color);

    if (!background || background === "rgba(0, 0, 0, 0)" || background === "transparent") {
      return;
    }

    document.querySelectorAll(pageSelector).forEach(function (page) {
      page.style.setProperty("--pgeservicos-current-sidebar-bg", background);

      if (color) {
        page.style.setProperty("--pgeservicos-current-sidebar-color", color);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", applySidebarTheme);
  } else {
    applySidebarTheme();
  }
})();
