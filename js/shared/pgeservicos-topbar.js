(function () {
  "use strict";

  var state = {
    active: false,
    topbar: null,
    container: null,
    customBar: null,
    hiddenSiblings: [],
    hiddenBackLinks: [],
    backLinkSignature: "",
    userMenu: null,
    userMenuParent: null,
    userMenuNextSibling: null,
    profileSelector: null,
    profileParent: null,
    profileNextSibling: null,
    entitySelector: null,
    entityParent: null,
    entityNextSibling: null,
    observer: null,
    bootingTopbar: null,
    mainBar: null,
    layoutMode: "",
  };

  var contextSelector = [
    ".pgeservicos-container",
    ".pgegestor-page",
    ".pgeservicos-formcreator-page",
    "[data-pgeservicos-custom-topbar='1']",
  ].join(",");

  var backLinkSelector = [
    ".pgeservicos-header-back-link",
    ".pgeservicos-back-link",
    ".pgeservicos-formcreator-back",
  ].join(",");

  var debugEnabled = new URLSearchParams(window.location.search || "").get("pgeservicos_topbar_debug") === "1";

  function debugLog() {
    if (!debugEnabled || !window.console) {
      return;
    }

    window.console.info.apply(window.console, ["[pgeservicos-topbar]"].concat(Array.prototype.slice.call(arguments)));
  }

  function normalizeText(value) {
    return String(value || "")
      .replace(/\s+/g, " ")
      .trim();
  }

  function normalizeComparable(value) {
    return normalizeText(value)
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  }

  function isVisibleElement(element) {
    if (!element) {
      return false;
    }

    var rect = element.getBoundingClientRect();
    var style = window.getComputedStyle(element);

    return rect.width > 0
      && rect.height > 0
      && style.display !== "none"
      && style.visibility !== "hidden";
  }

  function isPgeservicosContext() {
    var path = window.location.pathname || "";
    var params = new URLSearchParams(window.location.search || "");

    if (document.querySelector(contextSelector)) {
      return true;
    }

    if (path.indexOf("/plugins/pgeservicos/") !== -1) {
      return true;
    }

    if (
      path.indexOf("/plugins/formcreator/front/formdisplay.php") !== -1
      && params.get("pgeservicos_portal") === "1"
    ) {
      return true;
    }

    if (path.indexOf("/plugins/formcreator/front/formdisplay.php") !== -1) {
      try {
        return Boolean(window.sessionStorage.getItem("pgeservicosFormcreatorPortalFormId"));
      } catch (error) {
        return false;
      }
    }

    return false;
  }

  function findVerticalSidebar() {
    var selectors = [
      "aside.navbar-vertical.sidebar",
      "aside.sidebar",
      ".navbar-vertical.sidebar",
      ".sidebar.navbar-vertical",
    ];

    for (var index = 0; index < selectors.length; index += 1) {
      var element = document.querySelector(selectors[index]);

      if (isVisibleElement(element)) {
        return element;
      }
    }

    return null;
  }

  function uniqueElements(selectors) {
    var seen = [];
    var elements = [];

    selectors.forEach(function (selector) {
      Array.prototype.slice.call(document.querySelectorAll(selector)).forEach(function (element) {
        if (seen.indexOf(element) === -1) {
          seen.push(element);
          elements.push(element);
        }
      });
    });

    return elements;
  }

  function hasSearchControl(element) {
    if (!element) {
      return false;
    }

    if (element.querySelector("form[role='search'], .global-search, .search-form, [class*='global-search']")) {
      return true;
    }

    return Array.prototype.slice.call(element.querySelectorAll("input, button, [aria-label], [title]")).some(function (candidate) {
      var label = normalizeComparable(candidate.getAttribute("placeholder") || candidate.getAttribute("aria-label") || candidate.getAttribute("title") || candidate.textContent);

      return label.indexOf("pesquisar") !== -1 || label.indexOf("search") !== -1;
    });
  }

  function hasBreadcrumbContent(element) {
    if (!element) {
      return false;
    }

    if (element.querySelector(".breadcrumb, nav[aria-label='breadcrumb']")) {
      return true;
    }

    var text = normalizeComparable(element.textContent);

    return text.indexOf("home") !== -1 && (
      text.indexOf("pge servicos") !== -1
      || text.indexOf("ferramentas") !== -1
      || text.indexOf("portal") !== -1
    );
  }

  function menuWordScore(element) {
    var text = normalizeComparable(element ? element.textContent : "");
    var words = ["ativos", "assistencia", "gerencia", "ferramentas", "plug-ins", "plugins", "administracao", "configurar"];

    return words.reduce(function (score, word) {
      return score + (text.indexOf(word) !== -1 ? 1 : 0);
    }, 0);
  }

  function navbarCandidates() {
    return uniqueElements([
      ".page > header.navbar",
      ".page > .navbar",
      ".page-wrapper > header.navbar",
      ".page-wrapper > .navbar",
      "header.navbar.d-print-none",
      ".navbar.d-print-none",
      "header.navbar",
      ".layout-navbar",
      ".page-header",
    ]).filter(function (element) {
      return isVisibleElement(element)
        && !element.classList.contains("navbar-vertical")
        && !element.closest("aside.sidebar")
        && !element.closest(".pgeservicos-portal-topbar");
    });
  }

  function findHorizontalMainBar() {
    var scored = navbarCandidates().map(function (element) {
      var rect = element.getBoundingClientRect();
      var score = 0;

      if (element.classList.contains("navbar-dark") || element.classList.contains("navbar-horizontal")) {
        score += 80;
      }

      score += menuWordScore(element) * 18;

      if (rect.top <= 90) {
        score += 20;
      }

      if (hasBreadcrumbContent(element)) {
        score -= 60;
      }

      if (hasSearchControl(element)) {
        score -= 20;
      }

      return { element: element, score: score };
    }).sort(function (first, second) {
      return second.score - first.score;
    });

    return scored.length && scored[0].score >= 45 ? scored[0].element : null;
  }

  function findHorizontalSecondaryBar() {
    var mainBar = findHorizontalMainBar();
    var mainBottom = mainBar ? mainBar.getBoundingClientRect().bottom : 0;
    var scored = navbarCandidates().map(function (element) {
      var rect = element.getBoundingClientRect();
      var hasBreadcrumb = hasBreadcrumbContent(element);
      var hasSearch = hasSearchControl(element);
      var score = 0;

      if (element === mainBar) {
        score -= 200;
      }

      if (hasBreadcrumb) {
        score += 95;
      }

      if (hasSearch) {
        score += 55;
      }

      if (mainBar) {
        var distanceFromMain = rect.top - mainBottom;

        if (distanceFromMain >= -4 && distanceFromMain <= 16) {
          score += 44;
        } else if (distanceFromMain > 48 || distanceFromMain < -4) {
          score -= 120;
        }
      } else if (rect.top >= mainBottom - 4) {
        score += 20;
      }

      if (element.matches(".page > header.navbar, .page > .navbar, .page-wrapper > header.navbar, .page-wrapper > .navbar")) {
        score += 16;
      }

      if (element.classList.contains("navbar-dark") || element.classList.contains("navbar-horizontal")) {
        score -= 120;
      }

      score -= menuWordScore(element) * 14;

      return {
        element: element,
        score: score,
        hasBreadcrumb: hasBreadcrumb,
        hasSearch: hasSearch,
        rect: rect,
      };
    }).filter(function (candidate) {
      return candidate.score >= 85 && (candidate.hasBreadcrumb || candidate.hasSearch);
    }).sort(function (first, second) {
      return second.score - first.score;
    });

    if (debugEnabled) {
      debugLog("horizontal main bar", mainBar);
      debugLog("horizontal secondary candidates", scored.map(function (candidate) {
        return {
          score: candidate.score,
          classes: candidate.element.className,
          rect: {
            top: Math.round(candidate.rect.top),
            bottom: Math.round(candidate.rect.bottom),
            width: Math.round(candidate.rect.width),
            height: Math.round(candidate.rect.height),
          },
          hasBreadcrumb: candidate.hasBreadcrumb,
          hasSearch: candidate.hasSearch,
        };
      }));
    }

    return scored.length ? scored[0].element : null;
  }

  function hasVisibleHorizontalNavbar() {
    return Array.prototype.slice.call(document.querySelectorAll(".navbar-horizontal")).some(isVisibleElement);
  }

  function isHorizontalLayout() {
    if (document.body && document.body.classList.contains("vertical-layout")) {
      return false;
    }

    if (document.body && document.body.classList.contains("horizontal-layout")) {
      return true;
    }

    if (findVerticalSidebar() && !hasVisibleHorizontalNavbar()) {
      return false;
    }

    if (findHorizontalSecondaryBar()) {
      return true;
    }

    return Boolean(findHorizontalMainBar()) && !findVerticalSidebar();
  }

  function isVerticalLayout() {
    if (document.body && document.body.classList.contains("vertical-layout")) {
      return Boolean(findVerticalSidebar());
    }

    if (document.body && document.body.classList.contains("horizontal-layout")) {
      return false;
    }

    return Boolean(findVerticalSidebar()) && !findHorizontalMainBar();
  }

  function findVerticalTopbar() {
    var scored = navbarCandidates().map(function (element) {
      var score = 0;

      if (element.matches(".page > header.navbar")) {
        score += 100;
      }

      if (hasBreadcrumbContent(element) || hasSearchControl(element)) {
        score += 30;
      }

      if (element.classList.contains("navbar-dark") || element.classList.contains("navbar-horizontal")) {
        score -= 120;
      }

      return { element: element, score: score };
    }).sort(function (first, second) {
      return second.score - first.score;
    });

    return scored.length ? scored[0].element : null;
  }

  function findTopbarTarget() {
    if (isHorizontalLayout()) {
      var horizontalBar = findHorizontalSecondaryBar();

      if (horizontalBar) {
        debugLog("target topbar", "horizontal", horizontalBar);
        return { topbar: horizontalBar, layoutMode: "horizontal" };
      }

      debugLog("target topbar", "none: horizontal layout without secondary bar");
      return { topbar: null, layoutMode: "" };
    }

    var verticalBar = findVerticalTopbar();

    if (verticalBar) {
      debugLog("target topbar", "vertical", verticalBar);
      return { topbar: verticalBar, layoutMode: "vertical" };
    }

    debugLog("target topbar", "none");
    return { topbar: null, layoutMode: "" };
  }

  function findTopbar() {
    return findTopbarTarget().topbar;
  }

  function findNativeContainer(topbar) {
    return topbar ? (topbar.querySelector(":scope > .container-fluid") || topbar) : null;
  }

  function elementMetrics(element) {
    if (!element) {
      return null;
    }

    var rect = element.getBoundingClientRect();

    return {
      tag: element.tagName ? element.tagName.toLowerCase() : "",
      classes: element.className || "",
      top: Math.round(rect.top),
      bottom: Math.round(rect.bottom),
      width: Math.round(rect.width),
      height: Math.round(rect.height),
    };
  }

  function topbarMetrics(topbar, container, customBar) {
    return {
      host: elementMetrics(topbar),
      container: elementMetrics(container),
      custom: elementMetrics(customBar),
    };
  }

  function clearHorizontalMainBar() {
    if (!state.mainBar) {
      return;
    }

    state.mainBar.classList.remove("pgeservicos-portal-mainbar-native");
    if (state.mainBar.dataset.pgeservicosTopbarMain === "horizontal") {
      delete state.mainBar.dataset.pgeservicosTopbarMain;
    }
    state.mainBar = null;
  }

  function styleHorizontalMainBar(mainBar) {
    if (!mainBar) {
      clearHorizontalMainBar();
      return;
    }

    if (state.mainBar && state.mainBar !== mainBar) {
      clearHorizontalMainBar();
    }

    mainBar.classList.add("pgeservicos-portal-mainbar-native");
    mainBar.dataset.pgeservicosTopbarMain = "horizontal";
    state.mainBar = mainBar;
    debugLog("horizontal main bar prepared", elementMetrics(mainBar));
  }

  function findLayoutUserMenu(layoutMode, container, mainBar) {
    if (layoutMode === "horizontal") {
      return findUserMenu(mainBar) || findUserMenu(container);
    }

    return findUserMenu(container);
  }

  function markTopbarBooting(topbar, layoutMode) {
    if (!topbar || state.active) {
      return;
    }

    if (topbar.dataset.pgeservicosTopbar !== "active") {
      topbar.dataset.pgeservicosTopbar = "booting";
      if (layoutMode) {
        topbar.dataset.pgeservicosTopbarLayout = layoutMode;
      }
      state.bootingTopbar = topbar;
    }
  }

  function clearTopbarBooting(topbar) {
    var target = topbar || state.bootingTopbar;

    if (target && target.dataset.pgeservicosTopbar === "booting") {
      delete target.dataset.pgeservicosTopbar;
      delete target.dataset.pgeservicosTopbarLayout;
    }

    if (!topbar || state.bootingTopbar === topbar) {
      state.bootingTopbar = null;
    }
  }

  function findUserMenu(container) {
    if (!container) {
      return null;
    }

    return container.querySelector(".user-menu")
      || container.querySelector(".user-menu-dropdown-toggle")?.closest(".navbar-nav")
      || null;
  }

  function findUserDropdownMenu(userMenu) {
    return userMenu ? userMenu.querySelector(".dropdown-menu") : null;
  }

  function findProfileSelector(userMenu) {
    var dropdown = findUserDropdownMenu(userMenu);

    if (!dropdown) {
      return null;
    }

    return Array.prototype.slice.call(dropdown.querySelectorAll(":scope > .dropdown, :scope > .dropstart"))
      .find(function (element) {
        var text = normalizeComparable(element.textContent);

        return element.querySelector(".ti-user-check")
          || text.indexOf("profiles") !== -1
          || text.indexOf("perfis") !== -1;
      }) || null;
  }

  function findEntitySelector(userMenu) {
    var dropdown = findUserDropdownMenu(userMenu);

    if (!dropdown) {
      return null;
    }

    return Array.prototype.slice.call(dropdown.children).find(function (element) {
      return element.matches('[id^="entity-tree-dropdown-"]')
        || element.querySelector(".entity-dropdown-toggle")
        || element.querySelector(".ti-stack")
        || (element.classList.contains("dropdown-item-text")
          && normalizeComparable(element.textContent).indexOf("entidade") !== -1);
    }) || null;
  }

  function getActiveProfileName(userMenu) {
    var profileSelector = findProfileSelector(userMenu);
    var profileButton = profileSelector ? profileSelector.querySelector(".dropdown-toggle") : null;
    var triggerText = userMenu
      ? userMenu.querySelector(".user-menu-dropdown-toggle .pe-2 div")?.textContent
      : "";

    return normalizeText(profileButton ? profileButton.textContent : triggerText);
  }

  function isUserProfile(profileName) {
    return normalizeComparable(profileName) === "usuario";
  }

  function trackHiddenNativeElement(element) {
    if (!element || element === state.customBar || element.closest(".pgeservicos-portal-topbar")) {
      return;
    }

    if (element.hidden && element.dataset.pgeservicosTopbarHidden !== "1") {
      return;
    }

    element.hidden = true;
    element.dataset.pgeservicosTopbarHidden = "1";

    if (state.hiddenSiblings.indexOf(element) === -1) {
      state.hiddenSiblings.push(element);
    }
  }

  function hideNativeElements(container, customBar) {
    var root = state.topbar || container;
    var nativeSelectors = [
      ":scope > .container-fluid > *",
      ".breadcrumb",
      "nav[aria-label='breadcrumb']",
      "form[role='search']",
      ".global-search",
      ".search-form",
      "[class*='global-search']",
      ".entity-selector",
    ];

    if (!container || !customBar) {
      return;
    }

    Array.prototype.slice.call(container.children).forEach(function (child) {
      if (child !== customBar) {
        trackHiddenNativeElement(child);
      }
    });

    nativeSelectors.forEach(function (selector) {
      try {
        Array.prototype.slice.call(root.querySelectorAll(selector)).forEach(function (element) {
          if (element !== customBar && !customBar.contains(element)) {
            trackHiddenNativeElement(element);
          }
        });
      } catch (error) {
        // Ignore unsupported scoped selectors in older browsers.
      }
    });
  }

  function restoreNativeSiblings() {
    state.hiddenSiblings.forEach(function (element) {
      if (element.dataset.pgeservicosTopbarHidden === "1") {
        element.hidden = false;
        delete element.dataset.pgeservicosTopbarHidden;
      }
    });
    state.hiddenSiblings = [];
  }

  function collectContextRoot() {
    return document.querySelector(contextSelector);
  }

  function cssVariable(element, name) {
    if (!element) {
      return "";
    }

    return window.getComputedStyle(element).getPropertyValue(name).trim();
  }

  function syncBackButtonTheme(customBar) {
    var context = document.querySelector(".pgeservicos-formcreator-shell") || collectContextRoot();
    var background = cssVariable(context, "--pgeservicos-menu-bg")
      || cssVariable(context, "--pgeservicos-current-sidebar-bg")
      || cssVariable(context, "--pgeservicos-primary");
    var color = cssVariable(context, "--pgeservicos-menu-color")
      || cssVariable(context, "--pgeservicos-current-sidebar-color")
      || "#ffffff";

    if (!customBar) {
      return;
    }

    if (background) {
      customBar.style.setProperty("--pgeservicos-portal-topbar-back-bg", background);
    }

    customBar.style.setProperty("--pgeservicos-portal-topbar-back-color", color || "#ffffff");
  }

  function enforceUserAvatarOnly(userMenu) {
    var toggle = userMenu ? userMenu.querySelector(".user-menu-dropdown-toggle") : null;

    if (!toggle) {
      return;
    }

    toggle.setAttribute("aria-label", "Menu do usuário");
    toggle.setAttribute("title", "Menu do usuário");
  }


  function clearBackLinkSources() {
    state.hiddenBackLinks.forEach(function (element) {
      if (element.dataset.pgeservicosTopbarSourceHidden === "1") {
        element.hidden = element.dataset.pgeservicosTopbarPreviousHidden === "1";
        delete element.dataset.pgeservicosTopbarPreviousHidden;
        if (element.dataset.pgeservicosTopbarPreviousDisplay !== undefined) {
          element.style.display = element.dataset.pgeservicosTopbarPreviousDisplay;
          delete element.dataset.pgeservicosTopbarPreviousDisplay;
        }
        delete element.dataset.pgeservicosTopbarSourceHidden;
      }
    });
    state.hiddenBackLinks = [];
  }

  function findBackLinkHideTarget(link) {
    return link.closest(".pgeservicos-chamado-topnav, .pgeservicos-chamado-sticky-links")
      || link;
  }

  function hideBackLinkSource(link) {
    var target = findBackLinkHideTarget(link);

    if (!target || target.closest(".pgeservicos-portal-topbar")) {
      return;
    }

    if (target.dataset.pgeservicosTopbarSourceHidden !== "1") {
      target.dataset.pgeservicosTopbarPreviousHidden = target.hidden ? "1" : "0";
      target.dataset.pgeservicosTopbarPreviousDisplay = target.style.display || "";
      target.hidden = true;
      target.style.display = "none";
      target.dataset.pgeservicosTopbarSourceHidden = "1";
    }

    if (state.hiddenBackLinks.indexOf(target) === -1) {
      state.hiddenBackLinks.push(target);
    }
  }

  function collectBackLinkCandidates() {
    var context = collectContextRoot();
    var seen = {};
    var links = [];

    if (!context) {
      return links;
    }

    Array.prototype.slice.call(context.querySelectorAll(backLinkSelector)).forEach(function (link) {
      var href = link.getAttribute("href") || "";
      var text = normalizeText(link.textContent);
      var key = href;

      if (!href || !text || link.closest(".pgeservicos-portal-topbar")) {
        return;
      }

      if (seen[key]) {
        seen[key].sources.push(link);
        return;
      }

      seen[key] = { href: href, text: text, sources: [link] };
      links.push(seen[key]);
    });

    return links;
  }

  function buildBackLinks(target) {
    var links = collectBackLinkCandidates();
    var signature = links.map(function (entry) {
      return entry.href + "|" + entry.text;
    }).join("||");

    if (signature === state.backLinkSignature && target.childNodes.length) {
      links.forEach(function (entry) {
        entry.sources.forEach(hideBackLinkSource);
      });
      return;
    }

    state.backLinkSignature = signature;
    clearBackLinkSources();
    target.textContent = "";

    if (!links.length) {
      var placeholder = document.createElement("span");
      placeholder.className = "pgeservicos-portal-topbar__placeholder";
      placeholder.textContent = "Procuradoria-Geral do Estado";
      placeholder.setAttribute("title", "Procuradoria-Geral do Estado");
      placeholder.setAttribute("aria-label", "Procuradoria-Geral do Estado");
      target.appendChild(placeholder);
      return;
    }

    links.forEach(function (entry) {
      var link = document.createElement("a");
      link.className = "pgeservicos-portal-topbar__link";
      link.href = entry.href;
      link.textContent = entry.text;
      target.appendChild(link);

      entry.sources.forEach(hideBackLinkSource);
    });
  }

  function moveElement(element, target) {
    if (!element || !target) {
      return;
    }

    target.appendChild(element);
  }

  function storeOriginalPosition(key, element) {
    if (!element || state[key + "Parent"]) {
      return;
    }

    state[key + "Parent"] = element.parentNode;
    state[key + "NextSibling"] = element.nextSibling;
  }

  function restoreElement(key, element) {
    var parent = state[key + "Parent"];
    var nextSibling = state[key + "NextSibling"];

    if (element && parent) {
      parent.insertBefore(element, nextSibling && nextSibling.parentNode === parent ? nextSibling : null);
    }

    state[key + "Parent"] = null;
    state[key + "NextSibling"] = null;
  }

  function removeProfileFromUserMenu(userMenu) {
    var profile = findProfileSelector(userMenu);

    if (!profile) {
      return null;
    }

    storeOriginalPosition("profile", profile);
    state.profileSelector = profile;
    return profile;
  }

  function maybeHideEntityForUserProfile(userMenu, profileName) {
    if (!isUserProfile(profileName)) {
      return;
    }

    var entity = findEntitySelector(userMenu);

    if (!entity) {
      return;
    }

    storeOriginalPosition("entity", entity);
    state.entitySelector = entity;
    entity.remove();
  }

  function createCustomBar(layoutMode) {
    var customBar = document.createElement("div");
    var left = document.createElement("div");
    var links = document.createElement("div");
    var right = document.createElement("div");
    var profileSlot = document.createElement("div");
    var userSlot = document.createElement("div");

    customBar.className = "pgeservicos-portal-topbar pgeservicos-portal-topbar--" + layoutMode;
    customBar.dataset.pgeservicosTopbarMode = layoutMode;
    customBar.setAttribute("role", "region");
    customBar.setAttribute("aria-label", "Navegação da Procuradoria-Geral do Estado");

    left.className = "pgeservicos-portal-topbar__left";
    links.className = "pgeservicos-portal-topbar__links";
    right.className = "pgeservicos-portal-topbar__right";
    profileSlot.className = "pgeservicos-portal-topbar__profile";
    userSlot.className = "pgeservicos-portal-topbar__user";

    left.appendChild(links);
    right.appendChild(profileSlot);
    right.appendChild(userSlot);
    customBar.appendChild(left);
    customBar.appendChild(right);

    return {
      root: customBar,
      links: links,
      profileSlot: profileSlot,
      userSlot: userSlot,
    };
  }

  function notifyLayoutChanged() {
    if (typeof window.pgeservicosUpdateFloatingLayout !== "function") {
      return;
    }

    window.pgeservicosUpdateFloatingLayout();

    if (state.layoutMode !== "horizontal") {
      return;
    }

    window.requestAnimationFrame(function () {
      window.pgeservicosUpdateFloatingLayout();
      window.requestAnimationFrame(function () {
        window.pgeservicosUpdateFloatingLayout();
      });
    });
  }

  function activate() {
    var target = findTopbarTarget();
    var topbar = target.topbar;
    var layoutMode = target.layoutMode;
    var container = findNativeContainer(topbar);
    var mainBar = layoutMode === "horizontal" ? findHorizontalMainBar() : null;
    var userMenu = findLayoutUserMenu(layoutMode, container, mainBar);

    debugLog("activate", {
      context: isPgeservicosContext(),
      layoutMode: layoutMode,
      topbar: topbar,
      container: container,
      mainBar: mainBar,
      hasUserMenu: Boolean(userMenu || state.userMenu),
      hasProfileSelector: Boolean((userMenu || state.userMenu) && findProfileSelector(userMenu || state.userMenu)),
      hasEntitySelector: Boolean((userMenu || state.userMenu) && findEntitySelector(userMenu || state.userMenu)),
      activeProfileName: (userMenu || state.userMenu) ? getActiveProfileName(userMenu || state.userMenu) : "",
      active: state.active,
      metricsBefore: topbarMetrics(topbar, container, null),
    });

    if (state.active) {
      if (!state.customBar || !document.documentElement.contains(state.customBar) || state.topbar !== topbar || state.container !== container || state.layoutMode !== layoutMode) {
        deactivate();
        activate();
        return;
      }

      syncBackButtonTheme(state.customBar);
      enforceUserAvatarOnly(state.userMenu);
      styleHorizontalMainBar(layoutMode === "horizontal" ? mainBar : null);
      hideNativeElements(state.container, state.customBar);
      buildBackLinks(state.customBar.querySelector(".pgeservicos-portal-topbar__links"));
      debugLog("topbar host refreshed", topbarMetrics(state.topbar, state.container, state.customBar));
      notifyLayoutChanged();
      return;
    }

    if (!topbar || !container || !layoutMode) {
      debugLog("activate aborted", "missing target");
      return;
    }

    if (!userMenu) {
      debugLog("activate aborted", layoutMode + " user menu not found");
      return;
    }

    var profileName = userMenu ? getActiveProfileName(userMenu) : "";
    var profile = userMenu ? removeProfileFromUserMenu(userMenu) : null;
    var custom = createCustomBar(layoutMode);

    state.topbar = topbar;
    state.container = container;
    state.customBar = custom.root;
    state.layoutMode = layoutMode;

    styleHorizontalMainBar(layoutMode === "horizontal" ? mainBar : null);

    if (userMenu) {
      storeOriginalPosition("userMenu", userMenu);
      state.userMenu = userMenu;
      maybeHideEntityForUserProfile(userMenu, profileName);

      if (profile) {
        profile.classList.add("pgeservicos-portal-profile-selector");
        moveElement(profile, custom.profileSlot);
      }

      userMenu.classList.add("pgeservicos-portal-user-menu");
      enforceUserAvatarOnly(userMenu);
      moveElement(userMenu, custom.userSlot);
    }

    syncBackButtonTheme(custom.root);
    buildBackLinks(custom.links);

    container.insertBefore(custom.root, container.firstChild);
    hideNativeElements(container, custom.root);
    clearTopbarBooting(topbar);
    topbar.classList.add("pgeservicos-portal-topbar-native");
    topbar.dataset.pgeservicosTopbar = "active";
    topbar.dataset.pgeservicosTopbarLayout = layoutMode;
    state.active = true;
    debugLog("custom topbar attached", custom.root);
    debugLog("topbar host after attach", topbarMetrics(topbar, container, custom.root));
    notifyLayoutChanged();
  }

  function deactivate() {
    if (!state.active) {
      return;
    }

    restoreNativeSiblings();
    clearBackLinkSources();
    state.backLinkSignature = "";

    restoreElement("entity", state.entitySelector);
    state.entitySelector = null;

    restoreElement("profile", state.profileSelector);
    if (state.profileSelector) {
      state.profileSelector.classList.remove("pgeservicos-portal-profile-selector");
    }
    state.profileSelector = null;

    restoreElement("userMenu", state.userMenu);
    if (state.userMenu) {
      state.userMenu.classList.remove("pgeservicos-portal-user-menu");
    }
    state.userMenu = null;

    if (state.customBar) {
      state.customBar.remove();
    }

    clearHorizontalMainBar();

    if (state.topbar) {
      state.topbar.classList.remove("pgeservicos-portal-topbar-native");
      delete state.topbar.dataset.pgeservicosTopbar;
      delete state.topbar.dataset.pgeservicosTopbarLayout;
    }

    clearTopbarBooting();

    state.active = false;
    state.topbar = null;
    state.container = null;
    state.customBar = null;
    state.layoutMode = "";
    notifyLayoutChanged();
  }

  function shouldActivate() {
    var target = findTopbarTarget();

    if (!isPgeservicosContext() || !target.topbar) {
      debugLog("shouldActivate", false, "missing context or target");
      return false;
    }

    if (state.active) {
      debugLog("shouldActivate", true, target.layoutMode);
      return true;
    }

    var container = findNativeContainer(target.topbar);
    var mainBar = target.layoutMode === "horizontal" ? findHorizontalMainBar() : null;
    var userMenu = findLayoutUserMenu(target.layoutMode, container, mainBar);
    var hasUserMenu = Boolean(userMenu);

    debugLog("shouldActivate", hasUserMenu, {
      layoutMode: target.layoutMode,
      hasUserMenu: hasUserMenu,
      hasProfileSelector: Boolean(userMenu && findProfileSelector(userMenu)),
      hasEntitySelector: Boolean(userMenu && findEntitySelector(userMenu)),
      activeProfileName: userMenu ? getActiveProfileName(userMenu) : "",
    });

    return hasUserMenu;
  }

  function refresh() {
    var target = findTopbarTarget();

    if (shouldActivate()) {
      markTopbarBooting(target.topbar, target.layoutMode);
      activate();
    } else {
      clearTopbarBooting();
      deactivate();
    }
  }

  function scheduleRefresh() {
    if (scheduleRefresh.scheduled) {
      return;
    }

    scheduleRefresh.scheduled = true;
    window.requestAnimationFrame(function () {
      scheduleRefresh.scheduled = false;
      refresh();
    });
  }

  function startObserver() {
    if (state.observer || !document.body) {
      return;
    }

    state.observer = new MutationObserver(scheduleRefresh);
    state.observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["class", "hidden", "style"],
    });
  }

  function init() {
    refresh();
    startObserver();
    window.addEventListener("resize", scheduleRefresh);
    window.addEventListener("load", scheduleRefresh);
    window.requestAnimationFrame(scheduleRefresh);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
