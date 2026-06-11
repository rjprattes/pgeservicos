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
    const verticalSidebar = menuCandidates().find(function (element) {
      return element.matches("aside.sidebar, .navbar-vertical.sidebar, .sidebar.navbar")
        && isVisibleElement(element)
        && !isTransparentBackground(window.getComputedStyle(element).backgroundColor);
    });

    if (verticalSidebar) {
      return verticalSidebar;
    }

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



  const floatingSidebarSelectors = [
    "aside.navbar-vertical.sidebar",
    "aside.sidebar",
    ".navbar-vertical.sidebar",
    ".sidebar.navbar-vertical",
    ".sidebar.navbar",
  ];
  let floatingLayoutScheduled = false;
  let floatingObserversStarted = false;
  let floatingResizeObserver = null;
  let floatingMutationObserver = null;
  const observedFloatingElements = new WeakSet();

  function fixedTopCandidates() {
    const selectors = [
      ".pgeservicos-portal-topbar",
      "header.navbar[data-pgeservicos-topbar]",
      ".topbar",
      ".navbar.fixed-top",
      ".navbar.sticky-top",
      "header.navbar",
      ".layout-navbar",
      ".navbar",
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

  function fixedOrStickyAncestor(element) {
    let current = element;

    while (current && current !== document.documentElement) {
      const style = window.getComputedStyle(current);

      if (["fixed", "sticky"].includes(style.position)) {
        return current;
      }

      current = current.parentElement;
    }

    return null;
  }

  function isVisibleFixedTopElement(element, page) {
    if (!element || (page && page.contains(element)) || element.matches(".sidebar, .navbar-vertical")) {
      return false;
    }

    const rect = element.getBoundingClientRect();
    const style = window.getComputedStyle(element);
    const positioned = ["fixed", "sticky"].includes(style.position);
    const customTopbar = element.matches(".pgeservicos-portal-topbar");

    return rect.width > 0
      && rect.height > 0
      && rect.bottom > 0
      && rect.top <= 160
      && style.display !== "none"
      && style.visibility !== "hidden"
      && (positioned || (customTopbar && Boolean(fixedOrStickyAncestor(element))));
  }

  function visibleCustomTopbarBottom(page) {
    const customTopbar = Array.prototype.slice.call(document.querySelectorAll(".pgeservicos-portal-topbar"))
      .find(function (element) {
        return isVisibleFixedTopElement(element, page);
      });

    if (!customTopbar) {
      return 0;
    }

    const customBottom = customTopbar.getBoundingClientRect().bottom;

    if (!customTopbar.classList.contains("pgeservicos-portal-topbar--horizontal")) {
      return customBottom;
    }

    const host = customTopbar.closest(".pgeservicos-portal-topbar-native[data-pgeservicos-topbar-layout='horizontal']");

    if (!host || (page && page.contains(host))) {
      return customBottom;
    }

    const hostRect = host.getBoundingClientRect();
    const hostStyle = window.getComputedStyle(host);
    const visibleHost = hostRect.width > 0
      && hostRect.height > 0
      && hostRect.bottom > 0
      && hostRect.top <= 160
      && hostStyle.display !== "none"
      && hostStyle.visibility !== "hidden";

    return visibleHost ? Math.max(customBottom, hostRect.bottom) : customBottom;
  }

  function updatePageStickyTop(page) {
    let top = visibleCustomTopbarBottom(page);

    if (!top) {
      fixedTopCandidates()
        .filter(function (element) {
          return !element.matches(".pgeservicos-portal-topbar") && isVisibleFixedTopElement(element, page);
        })
        .sort(function (first, second) {
          return first.getBoundingClientRect().top - second.getBoundingClientRect().top;
        })
        .forEach(function (element) {
          const rect = element.getBoundingClientRect();
          const style = window.getComputedStyle(element);
          const fixed = style.position === "fixed";
          const stackedSticky = style.position === "sticky" && rect.top <= top + 3;

          if (fixed || stackedSticky) {
            top = Math.max(top, rect.bottom);
          }
        });
    }

    if (top > 0) {
      page.style.setProperty("--pgeservicos-sticky-top", Math.ceil(top) + "px");
    } else {
      page.style.removeProperty("--pgeservicos-sticky-top");
    }
  }

  function actionThemeFor(page) {
    const styles = window.getComputedStyle(page);
    const primary = styles.getPropertyValue("--pgeservicos-action-bg").trim()
      || styles.getPropertyValue("--pgeservicos-primary").trim()
      || styles.getPropertyValue("--tblr-primary").trim()
      || "#fec95c";
    const color = styles.getPropertyValue("--pgeservicos-action-color").trim()
      || styles.getPropertyValue("--pgeservicos-primary-contrast").trim()
      || styles.getPropertyValue("--pgeservicos-on-primary").trim()
      || styles.getPropertyValue("--tblr-btn-color-text").trim()
      || readableTextColor(primary, "#ffffff");
    const rgb = styles.getPropertyValue("--pgeservicos-action-accent-rgb").trim()
      || styles.getPropertyValue("--pgeservicos-primary-rgb").trim()
      || styles.getPropertyValue("--tblr-primary-rgb").trim()
      || "254, 201, 92";

    return { primary, color, rgb };
  }

  function setFloatingVars(target, bounds, actionTheme) {
    if (!target) {
      return;
    }

    target.style.setProperty("--pgeservicos-floating-left", bounds.left + "px");
    target.style.setProperty("--pgeservicos-floating-right", bounds.right + "px");
    target.style.setProperty("--pgeservicos-floating-width", bounds.width + "px");
    target.style.setProperty("--pgeservicos-content-left", bounds.left + "px");
    target.style.setProperty("--pgeservicos-content-width", bounds.width + "px");
    target.style.setProperty("--pgeservicos-action-bg", actionTheme.primary);
    target.style.setProperty("--pgeservicos-action-color", actionTheme.color);
    target.style.setProperty("--pgeservicos-action-accent", actionTheme.primary);
    target.style.setProperty("--pgeservicos-action-accent-rgb", actionTheme.rgb);
    target.style.setProperty("--pgeservicos-action-accent-fg", actionTheme.color);
    target.style.setProperty("--pgeservicos-toast-bg", actionTheme.primary);
    target.style.setProperty("--pgeservicos-toast-color", actionTheme.color);
    target.style.setProperty("--pgeservicos-scroll-btn-bg", actionTheme.primary);
    target.style.setProperty("--pgeservicos-scroll-btn-color", actionTheme.color);
  }

  function updateFloatingLayout() {
    const pages = Array.prototype.slice.call(document.querySelectorAll(pageSelector))
      .filter(isVisibleElement);
    const viewportWidth = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
    let firstBounds = null;
    let firstTheme = null;

    pages.forEach(function (page) {
      const rect = page.getBoundingClientRect();
      const left = Math.max(10, Math.min(Math.ceil(rect.left), Math.max(10, viewportWidth - 10)));
      const right = Math.max(10, Math.ceil(viewportWidth - rect.right));
      const width = Math.max(0, Math.floor(viewportWidth - left - right));
      const bounds = { left, right, width };
      const actionTheme = actionThemeFor(page);

      setFloatingVars(page, bounds, actionTheme);
      updatePageStickyTop(page);

      if (!firstBounds) {
        firstBounds = bounds;
        firstTheme = actionTheme;
      }
    });

    if (firstBounds && firstTheme) {
      [
        document.querySelector("[data-pgeservicos-toast-region]"),
        document.querySelector(".pgeservicos-scroll-bottom-btn"),
      ].filter(Boolean).forEach(function (target) {
        setFloatingVars(target, firstBounds, firstTheme);
      });
    }
  }

  function scheduleFloatingLayoutUpdate() {
    if (floatingLayoutScheduled) {
      return;
    }

    floatingLayoutScheduled = true;
    window.requestAnimationFrame(function () {
      floatingLayoutScheduled = false;
      updateFloatingLayout();
    });
  }

  function scheduleFloatingLayoutTransitionPass() {
    scheduleFloatingLayoutUpdate();
    window.setTimeout(scheduleFloatingLayoutUpdate, 60);
    window.setTimeout(scheduleFloatingLayoutUpdate, 180);
    window.setTimeout(scheduleFloatingLayoutUpdate, 340);
  }

  function observeFloatingElement(element) {
    if (!element || observedFloatingElements.has(element)) {
      return;
    }

    observedFloatingElements.add(element);

    if (floatingResizeObserver) {
      floatingResizeObserver.observe(element);
    }

    element.addEventListener("transitionend", scheduleFloatingLayoutTransitionPass, true);
  }

  function refreshFloatingObservers() {
    document.querySelectorAll(pageSelector).forEach(observeFloatingElement);
    floatingSidebarSelectors.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(observeFloatingElement);
    });
    fixedTopCandidates().forEach(observeFloatingElement);
  }

  function initFloatingLayout() {
    if (floatingObserversStarted) {
      scheduleFloatingLayoutTransitionPass();
      return;
    }

    floatingObserversStarted = true;

    if (typeof ResizeObserver === "function") {
      floatingResizeObserver = new ResizeObserver(scheduleFloatingLayoutTransitionPass);
    }

    refreshFloatingObservers();

    if (typeof MutationObserver === "function" && document.body) {
      floatingMutationObserver = new MutationObserver(function (mutations) {
        const shouldUpdate = mutations.some(function (mutation) {
          const target = mutation.target;

          if (mutation.type === "childList") {
            return true;
          }

          if (!target || target.nodeType !== 1) {
            return false;
          }

          if (target.matches(floatingSidebarSelectors.join(",")) || target.closest(floatingSidebarSelectors.join(","))) {
            return true;
          }

          if (target.matches(".topbar, .navbar, header.navbar, .layout-navbar")) {
            return true;
          }

          return target === document.body;
        });

        if (shouldUpdate) {
          refreshFloatingObservers();
          scheduleFloatingLayoutTransitionPass();
        }
      });
      floatingMutationObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ["class", "style", "aria-expanded", "data-bs-theme", "data-pgeservicos-topbar"],
        childList: true,
        subtree: true,
      });
    }

    document.addEventListener("transitionend", function (event) {
      if (event.target && (event.target.matches(floatingSidebarSelectors.join(",")) || event.target.closest(floatingSidebarSelectors.join(",")))) {
        scheduleFloatingLayoutTransitionPass();
      }
    }, true);

    window.pgeservicosUpdateFloatingLayout = scheduleFloatingLayoutTransitionPass;
    window.addEventListener("resize", scheduleFloatingLayoutTransitionPass);
    window.addEventListener("load", scheduleFloatingLayoutTransitionPass);
    scheduleFloatingLayoutTransitionPass();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      applyMenuTheme();
      initFloatingLayout();
    });
  } else {
    applyMenuTheme();
    initFloatingLayout();
  }

  window.addEventListener("resize", function () {
    applyMenuTheme();
    scheduleFloatingLayoutTransitionPass();
  });
  window.setTimeout(function () {
    applyMenuTheme();
    scheduleFloatingLayoutTransitionPass();
  }, 250);
})();
