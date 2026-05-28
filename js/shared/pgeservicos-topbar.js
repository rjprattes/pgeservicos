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
  };

  var contextSelector = [
    ".pgeservicos-container",
    ".pgegestor-page",
    ".pgeservicos-formcreator-page",
  ].join(",");

  var backLinkSelector = [
    ".pgeservicos-header-back-link",
    ".pgeservicos-back-link",
    ".pgeservicos-formcreator-back",
  ].join(",");

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

  function isVerticalLayout() {
    if (document.body && document.body.classList.contains("horizontal-layout")) {
      return false;
    }

    if (document.body && document.body.classList.contains("vertical-layout")) {
      return Boolean(findVerticalSidebar());
    }

    return Boolean(findVerticalSidebar());
  }

  function findTopbar() {
    var candidates = Array.prototype.slice.call(
      document.querySelectorAll(".page > header.navbar, header.navbar.d-print-none, header.navbar"),
    );

    return candidates.find(function (element) {
      return isVisibleElement(element)
        && !element.classList.contains("topbar")
        && !element.classList.contains("navbar-vertical")
        && !element.closest("aside.sidebar");
    }) || null;
  }

  function findNativeContainer(topbar) {
    return topbar ? topbar.querySelector(":scope > .container-fluid") : null;
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
      placeholder.textContent = "Portal de Serviços";
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

  function createCustomBar() {
    var customBar = document.createElement("div");
    var left = document.createElement("div");
    var links = document.createElement("div");
    var right = document.createElement("div");
    var profileSlot = document.createElement("div");
    var userSlot = document.createElement("div");

    customBar.className = "pgeservicos-portal-topbar";
    customBar.setAttribute("role", "region");
    customBar.setAttribute("aria-label", "Navegação do Portal de Serviços");

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

  function activate() {
    var topbar = findTopbar();
    var container = findNativeContainer(topbar);
    var userMenu = findUserMenu(container);

    if (state.active) {
      if (!state.customBar || !document.documentElement.contains(state.customBar) || state.topbar !== topbar || state.container !== container) {
        deactivate();
        activate();
        return;
      }

      syncBackButtonTheme(state.customBar);
      enforceUserAvatarOnly(state.userMenu);
      hideNativeElements(state.container, state.customBar);
      buildBackLinks(state.customBar.querySelector(".pgeservicos-portal-topbar__links"));
      return;
    }

    if (!topbar || !container || !userMenu) {
      return;
    }

    var profileName = getActiveProfileName(userMenu);
    var profile = removeProfileFromUserMenu(userMenu);
    var custom = createCustomBar();

    storeOriginalPosition("userMenu", userMenu);
    state.topbar = topbar;
    state.container = container;
    state.userMenu = userMenu;
    state.customBar = custom.root;

    maybeHideEntityForUserProfile(userMenu, profileName);

    if (profile) {
      profile.classList.add("pgeservicos-portal-profile-selector");
      moveElement(profile, custom.profileSlot);
    }

    userMenu.classList.add("pgeservicos-portal-user-menu");
    enforceUserAvatarOnly(userMenu);
    moveElement(userMenu, custom.userSlot);

    syncBackButtonTheme(custom.root);
    buildBackLinks(custom.links);

    container.insertBefore(custom.root, container.firstChild);
    hideNativeElements(container, custom.root);
    topbar.classList.add("pgeservicos-portal-topbar-native");
    topbar.dataset.pgeservicosTopbar = "active";
    state.active = true;
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

    if (state.topbar) {
      state.topbar.classList.remove("pgeservicos-portal-topbar-native");
      delete state.topbar.dataset.pgeservicosTopbar;
    }

    state.active = false;
    state.topbar = null;
    state.container = null;
    state.customBar = null;
  }

  function shouldActivate() {
    return isPgeservicosContext() && isVerticalLayout() && Boolean(findTopbar());
  }

  function refresh() {
    if (shouldActivate()) {
      activate();
    } else {
      deactivate();
    }
  }

  function scheduleRefresh() {
    window.clearTimeout(scheduleRefresh.timer);
    scheduleRefresh.timer = window.setTimeout(refresh, 80);
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
    window.setTimeout(refresh, 250);
    window.setTimeout(refresh, 900);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
