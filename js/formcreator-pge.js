(function () {
  "use strict";

  var initialized = false;

  function isDebugEnabled() {
    return new URLSearchParams(window.location.search).get("pgeservicos_debug") === "1";
  }

  function debug() {
    if (!isDebugEnabled() || !window.console || typeof window.console.info !== "function") {
      return;
    }

    window.console.info.apply(window.console, ["PGE Serviços Formcreator:"].concat(Array.prototype.slice.call(arguments)));
  }

  debug("script carregado", window.location.href);

  function getCurrentFormId() {
    var params = new URLSearchParams(window.location.search);
    var idFromUrl = params.get("id");

    if (idFromUrl) {
      return idFromUrl;
    }

    var form = document.querySelector(
      "form#plugin_formcreator_form.plugin_formcreator_form",
    ) || document.querySelector("form.plugin_formcreator_form");

    return form ? form.getAttribute("data-id") || "" : "";
  }

  function rememberPortalContext(formId) {
    try {
      window.sessionStorage.setItem(
        "pgeservicosFormcreatorPortalFormId",
        String(formId || ""),
      );
    } catch (error) {
      // Storage can be blocked; the URL marker is enough for the first load.
    }
  }

  function hasRememberedPortalContext(formId) {
    try {
      return formId
        && window.sessionStorage.getItem("pgeservicosFormcreatorPortalFormId") === String(formId);
    } catch (error) {
      return false;
    }
  }

  function isPortalFormDisplayPage() {
    var params = new URLSearchParams(window.location.search);
    var formId = getCurrentFormId();
    var hasPortalMarker = params.get("pgeservicos_portal") === "1";
    var hasStoredMarker = hasRememberedPortalContext(formId);

    debug("contexto", {
      formId: formId,
      hasPortalMarker: hasPortalMarker,
      hasStoredMarker: hasStoredMarker,
    });

    if (hasPortalMarker) {
      rememberPortalContext(formId);
      return true;
    }

    return hasStoredMarker;
  }

  function isFormCreatorDisplayPage() {
    var hasFormcreatorRoute = window.location.pathname.indexOf(
      "/plugins/formcreator/front/formdisplay.php",
    ) !== -1;
    var hasFormcreatorForm =
      document.querySelector(
        "form#plugin_formcreator_form.plugin_formcreator_form",
      ) !== null ||
      document.querySelector("form.plugin_formcreator_form") !== null ||
      document.querySelector('form[action*="formdisplay.php"]') !== null;

    debug("detecção", {
      hasFormcreatorRoute: hasFormcreatorRoute,
      hasFormcreatorForm: hasFormcreatorForm,
    });

    return (hasFormcreatorRoute || hasFormcreatorForm) && isPortalFormDisplayPage();
  }

  function getRootDoc() {
    if (window.CFG_GLPI && typeof window.CFG_GLPI.root_doc === "string") {
      return window.CFG_GLPI.root_doc;
    }

    return "";
  }

  function getFormTitleElement(form) {
    return form.querySelector(".form-title") || document.querySelector("h1");
  }

  function getFormTitle(form) {
    var title = getFormTitleElement(form);

    if (!title) {
      return "Solicitação de Serviço";
    }

    return (
      title.textContent.replace(/\s+/g, " ").trim() || "Solicitação de Serviço"
    );
  }


  function parseRgb(value) {
    var match = String(value || "").match(/rgba?\(([^)]+)\)/i);

    if (!match) {
      return null;
    }

    var channels = match[1]
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
    var linear = rgb.map(function (channel) {
      var value = channel / 255;
      return value <= 0.03928
        ? value / 12.92
        : Math.pow((value + 0.055) / 1.055, 2.4);
    });

    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
  }

  function contrast(first, second) {
    var lighter = Math.max(luminance(first), luminance(second));
    var darker = Math.min(luminance(first), luminance(second));

    return (lighter + 0.05) / (darker + 0.05);
  }

  function readableTextColor(background, candidate) {
    var bg = parseRgb(background);

    if (!bg) {
      return candidate || "";
    }

    var parsedCandidate = parseRgb(candidate);

    if (parsedCandidate && contrast(bg, parsedCandidate) >= 4.5) {
      return candidate;
    }

    var dark = [30, 41, 59];
    var light = [255, 255, 255];

    return contrast(bg, dark) >= contrast(bg, light) ? "#1e293b" : "#ffffff";
  }

  function isTransparentBackground(value) {
    return !value || value === "rgba(0, 0, 0, 0)" || value === "transparent";
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

  function findMenuElement() {
    var selectors = [
      ".topbar.navbar",
      ".topbar",
      "nav.topbar",
      "header.topbar",
      "aside.sidebar",
      ".navbar-vertical.sidebar",
      ".sidebar.navbar",
    ];
    var seen = [];
    var candidates = [];

    selectors.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (element) {
        if (seen.indexOf(element) === -1) {
          seen.push(element);
          candidates.push(element);
        }
      });
    });

    var verticalSidebar = candidates.find(function (element) {
      return element.matches("aside.sidebar, .navbar-vertical.sidebar, .sidebar.navbar")
        && isVisibleElement(element)
        && !isTransparentBackground(window.getComputedStyle(element).backgroundColor);
    });

    if (verticalSidebar) {
      return verticalSidebar;
    }

    candidates = candidates
      .filter(isVisibleElement)
      .map(function (element) {
        var rect = element.getBoundingClientRect();
        var style = window.getComputedStyle(element);
        var background = style.backgroundColor;
        var score = 0;

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

        return { element: element, score: score, background: background };
      })
      .filter(function (candidate) {
        return !isTransparentBackground(candidate.background);
      })
      .sort(function (first, second) {
        return second.score - first.score;
      });

    return candidates.length ? candidates[0].element : null;
  }

  function applyMenuTheme(shell) {
    var menu = findMenuElement();

    if (!shell || !menu) {
      return;
    }

    var menuStyle = window.getComputedStyle(menu);
    var background = menuStyle.backgroundColor;
    var color = readableTextColor(background, menuStyle.color);

    if (isTransparentBackground(background)) {
      return;
    }

    shell.style.setProperty("--pgeservicos-menu-bg", background);
    shell.style.setProperty("--pgeservicos-menu-color", color);
    shell.style.setProperty("--pgeservicos-current-sidebar-bg", background);
    shell.style.setProperty("--pgeservicos-current-sidebar-color", color);
    shell.style.setProperty("--pgeservicos-fc-header-bg", background);
    shell.style.setProperty("--pgeservicos-fc-header-color", color);
  }

  function createHeader(title) {
    var header = document.createElement("section");
    var backLink = document.createElement("a");
    var heading = document.createElement("h1");
    var subtitle = document.createElement("p");

    header.className = "pgeservicos-formcreator-header";

    backLink.className = "pgeservicos-formcreator-back";
    backLink.href = getRootDoc() + "/plugins/pgeservicos/front/index.php";
    backLink.textContent = "← Voltar ao Portal de Serviços";

    heading.textContent = title;
    subtitle.textContent =
      "Preencha as informações abaixo para registrar sua solicitação.";

    header.appendChild(backLink);
    header.appendChild(heading);
    header.appendChild(subtitle);

    return header;
  }

  function wrapForm(form, title) {
    var existingShell = form.closest(".pgeservicos-formcreator-shell");

    if (existingShell) {
      return existingShell.closest(".pgeservicos-formcreator-page") || existingShell;
    }

    var page = document.createElement("div");
    var shell = document.createElement("div");
    var card = document.createElement("div");
    var parent = form.parentNode;

    page.className = "pgeservicos-formcreator-page";
    shell.className = "pgeservicos-formcreator-shell";
    card.className = "pgeservicos-formcreator-card";

    applyMenuTheme(page);
    applyMenuTheme(shell);

    parent.insertBefore(page, form);
    page.appendChild(shell);
    shell.appendChild(createHeader(title));
    shell.appendChild(card);
    card.appendChild(form);

    return page;
  }

  function markOriginalTitle(form) {
    var title = getFormTitleElement(form);

    if (title) {
      title.classList.add("pgeservicos-formcreator-original-title");
    }
  }

  function enhanceSections(form) {
    form
      .querySelectorAll(".plugin_formcreator_section")
      .forEach(function (section) {
        section.classList.add("pgeservicos-formcreator-section");
      });

    form
      .querySelectorAll('[data-itemtype="PluginFormcreatorQuestion"]')
      .forEach(function (question) {
        question.classList.add("pgeservicos-formcreator-question");
      });

    form.querySelectorAll("fieldset").forEach(function (fieldset) {
      fieldset.classList.add("pgeservicos-formcreator-fieldset");
    });
  }

  function enhanceButtons(form) {
    form
      .querySelectorAll(
        'button, input[type="submit"], input[type="button"], a.btn',
      )
      .forEach(function (button) {
        if (button.closest(".tox")) {
          return;
        }

        var type = (button.getAttribute("type") || "").toLowerCase();
        var name = (button.getAttribute("name") || "").toLowerCase();

        if (
          type === "submit" ||
          name === "add" ||
          button.classList.contains("btn-primary")
        ) {
          button.classList.add("pgeservicos-formcreator-primary-action");
        } else {
          button.classList.add("pgeservicos-formcreator-secondary-action");
        }
      });
  }

  function enhanceSelectActions(form) {
    form.querySelectorAll(".select2-container").forEach(function (select) {
      var next = select.nextElementSibling;
      var wrapper = select.closest(
        '[data-itemtype="PluginFormcreatorQuestion"], .form_field, .form-group',
      );

      if (
        wrapper &&
        next &&
        (next.matches("button") ||
          next.matches('input[type="button"]') ||
          next.matches("a.btn") ||
          next.classList.contains("btn"))
      ) {
        wrapper.classList.add("pgeservicos-formcreator-select-with-action");
      }
    });
  }

  function enhanceFocus(form) {
    var fields = form.querySelectorAll("input, select, textarea");

    fields.forEach(function (field) {
      field.addEventListener("focus", function () {
        var question = field.closest(
          '[data-itemtype="PluginFormcreatorQuestion"], .form_field, .form-group',
        );

        if (question) {
          question.classList.add("pgeservicos-formcreator-focused");
        }
      });

      field.addEventListener("blur", function () {
        var question = field.closest(
          '[data-itemtype="PluginFormcreatorQuestion"], .form_field, .form-group',
        );

        if (question) {
          question.classList.remove("pgeservicos-formcreator-focused");
        }
      });

      if (field.required) {
        field
          .closest(
            '[data-itemtype="PluginFormcreatorQuestion"], .form_field, .form-group',
          )
          ?.classList.add("pgeservicos-formcreator-required");
      }
    });
  }

  function enhanceMessages(form) {
    var selectors = [
      ".alert",
      ".message_after_redirect",
      ".error",
      ".warning",
      ".invalid-feedback",
      ".form-text.text-danger",
    ];

    form.querySelectorAll(selectors.join(",")).forEach(function (message) {
      message.classList.add("pgeservicos-formcreator-message");
    });
  }

  function cleanNumbers(value) {
    return (value || "").toString().replace(/\D/g, "");
  }

  function applyCpfMask(value) {
    var digits = cleanNumbers(value).slice(0, 11);

    if (digits.length <= 3) {
      return digits;
    }

    if (digits.length <= 6) {
      return digits.replace(/^(\d{3})(\d+)/, "$1.$2");
    }

    if (digits.length <= 9) {
      return digits.replace(/^(\d{3})(\d{3})(\d+)/, "$1.$2.$3");
    }

    return digits.replace(/^(\d{3})(\d{3})(\d{3})(\d+)/, "$1.$2.$3-$4");
  }

  function applyCnpjMask(value) {
    var digits = cleanNumbers(value).slice(0, 14);

    if (digits.length <= 2) {
      return digits;
    }

    if (digits.length <= 5) {
      return digits.replace(/^(\d{2})(\d+)/, "$1.$2");
    }

    if (digits.length <= 8) {
      return digits.replace(/^(\d{2})(\d{3})(\d+)/, "$1.$2.$3");
    }

    if (digits.length <= 12) {
      return digits.replace(/^(\d{2})(\d{3})(\d{3})(\d+)/, "$1.$2.$3/$4");
    }

    return digits.replace(
      /^(\d{2})(\d{3})(\d{3})(\d{4})(\d+)/,
      "$1.$2.$3/$4-$5",
    );
  }

  function applyCepMask(value) {
    var digits = cleanNumbers(value).slice(0, 8);

    if (digits.length <= 5) {
      return digits;
    }

    return digits.replace(/^(\d{5})(\d+)/, "$1-$2");
  }

  function applyPhoneMask(value) {
    var digits = cleanNumbers(value).slice(0, 11);

    if (digits.length <= 2) {
      return digits ? "(" + digits : "";
    }

    if (digits.length <= 6) {
      return digits.replace(/^(\d{2})(\d+)/, "($1) $2");
    }

    if (digits.length <= 10) {
      return digits.replace(/^(\d{2})(\d{4})(\d+)/, "($1) $2-$3");
    }

    return digits.replace(/^(\d{2})(\d{5})(\d+)/, "($1) $2-$3");
  }

  function normalizeMaskText(text) {
    return (text || "")
      .toString()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  }

  function getMaskTypeFromText(text) {
    var normalized = normalizeMaskText(text);

    if (!normalized) {
      return "";
    }

    if (normalized.indexOf("cnpj") !== -1) {
      return "cnpj";
    }

    if (normalized.indexOf("cpf") !== -1) {
      return "cpf";
    }

    if (normalized.indexOf("cep") !== -1) {
      return "cep";
    }

    if (
      normalized.indexOf("tel") !== -1 ||
      normalized.indexOf("cel") !== -1 ||
      normalized.indexOf("telefone") !== -1 ||
      normalized.indexOf("celular") !== -1
    ) {
      return "telefone";
    }

    return "";
  }

  function getQuestionWrapper(field) {
    return (
      field.closest('[data-itemtype="PluginFormcreatorQuestion"]') ||
      field.closest(".form-group") ||
      field.closest(".form_field")
    );
  }

  function findLabelForField(field, wrapper) {
    var labels;

    if (!field.id) {
      return wrapper ? wrapper.querySelector("label") : null;
    }

    if (window.CSS && typeof window.CSS.escape === "function") {
      var escapedId = window.CSS.escape(field.id);
      var directLabel = document.querySelector(
        'label[for="' + escapedId + '"]',
      );

      if (directLabel) {
        return directLabel;
      }
    }

    labels = (wrapper || document).querySelectorAll("label");

    for (var index = 0; index < labels.length; index += 1) {
      if (labels[index].htmlFor === field.id) {
        return labels[index];
      }
    }

    return wrapper ? wrapper.querySelector("label") : null;
  }

  function pushTextCandidate(candidates, value) {
    var text = (value || "").toString().replace(/\s+/g, " ").trim();

    if (text) {
      candidates.push(text);
    }
  }

  function collectAnnotationCandidates(field, wrapper) {
    var candidates = [];
    var annotationSelectors = [
      "[data-annotation]",
      "[data-pge-annotation]",
      ".annotation",
      ".formcreator_annotation",
      ".plugin_formcreator_annotation",
      ".help-block",
      ".form-text",
      ".text-muted",
      "small",
    ];

    [
      "data-annotation",
      "data-pge-annotation",
      "aria-description",
      "title",
    ].forEach(function (attribute) {
      pushTextCandidate(candidates, field.getAttribute(attribute));

      if (wrapper) {
        pushTextCandidate(candidates, wrapper.getAttribute(attribute));
      }
    });

    if (field.getAttribute("aria-describedby")) {
      field
        .getAttribute("aria-describedby")
        .split(/\s+/)
        .forEach(function (id) {
          var description = document.getElementById(id);
          pushTextCandidate(candidates, description && description.textContent);
        });
    }

    if (wrapper) {
      wrapper
        .querySelectorAll(annotationSelectors.join(","))
        .forEach(function (element) {
          pushTextCandidate(
            candidates,
            element.getAttribute("data-annotation"),
          );
          pushTextCandidate(
            candidates,
            element.getAttribute("data-pge-annotation"),
          );
          pushTextCandidate(candidates, element.textContent);
        });

      wrapper
        .querySelectorAll('input[type="hidden"]')
        .forEach(function (hiddenInput) {
          var hiddenName = normalizeMaskText(hiddenInput.getAttribute("name"));
          var hiddenId = normalizeMaskText(hiddenInput.id);

          if (
            hiddenName.indexOf("annotation") !== -1 ||
            hiddenId.indexOf("annotation") !== -1
          ) {
            pushTextCandidate(candidates, hiddenInput.value);
          }
        });
    }

    return candidates.join(" ");
  }

  function collectFallbackCandidates(field, wrapper) {
    var candidates = [];
    var label = findLabelForField(field, wrapper);

    pushTextCandidate(candidates, label && label.textContent);
    pushTextCandidate(candidates, field.getAttribute("aria-label"));

    if (wrapper) {
      wrapper
        .querySelectorAll(".help-block, .form-text, small")
        .forEach(function (element) {
          pushTextCandidate(candidates, element.textContent);
        });
    }

    return candidates.join(" ");
  }

  function detectFieldAnnotation(field) {
    var wrapper = getQuestionWrapper(field);
    var annotationText = collectAnnotationCandidates(field, wrapper);
    var annotationMask = getMaskTypeFromText(annotationText);

    if (annotationMask) {
      return {
        source: "annotation",
        text: annotationText,
        type: annotationMask,
      };
    }

    var fallbackText = collectFallbackCandidates(field, wrapper);
    var fallbackMask = getMaskTypeFromText(fallbackText);

    if (fallbackMask) {
      return {
        source: "label",
        text: fallbackText,
        type: fallbackMask,
      };
    }

    return null;
  }

  function getMaskPlaceholder(type) {
    var placeholders = {
      cpf: "000.000.000-00",
      cnpj: "00.000.000/0000-00",
      cep: "00000-000",
      telefone: "(00) 00000-0000",
    };

    return placeholders[type] || "";
  }

  function applyMaskByType(value, type) {
    if (type === "cpf") {
      return applyCpfMask(value);
    }

    if (type === "cnpj") {
      return applyCnpjMask(value);
    }

    if (type === "cep") {
      return applyCepMask(value);
    }

    if (type === "telefone") {
      return applyPhoneMask(value);
    }

    return value;
  }

  function configureMaskedField(field, maskData) {
    var maskType = maskData.type;

    field.dataset.pgeMask = maskType;
    field.dataset.pgeMaskSource = maskData.source;
    field.classList.add("pge-masked-field");
    field.setAttribute("inputmode", "numeric");

    if (!field.getAttribute("placeholder")) {
      field.setAttribute("placeholder", getMaskPlaceholder(maskType));
    }

    if (maskType === "telefone" && !field.getAttribute("autocomplete")) {
      field.setAttribute("autocomplete", "tel");
    }

    if (maskType === "cep" && !field.getAttribute("autocomplete")) {
      field.setAttribute("autocomplete", "postal-code");
    }

    function refreshMask() {
      field.value = applyMaskByType(field.value, maskType);
    }

    refreshMask();
    field.addEventListener("input", refreshMask);
    field.addEventListener("blur", refreshMask);
    field.addEventListener("paste", function () {
      window.setTimeout(refreshMask, 0);
    });
  }

  function enhanceMasks(form) {
    var fields = form.querySelectorAll(
      [
        'input[type="text"]',
        'input[type="tel"]',
        'input[type="search"]',
        'input[type="number"]',
        "input:not([type])",
      ].join(","),
    );

    fields.forEach(function (field) {
      if (
        field.closest(".tox") ||
        field.readOnly ||
        field.disabled ||
        field.dataset.pgeMask
      ) {
        return;
      }

      var maskData = detectFieldAnnotation(field);

      if (maskData) {
        configureMaskedField(field, maskData);
      }
    });
  }

  function init() {
    try {
      if (initialized || !isFormCreatorDisplayPage()) {
        return initialized;
      }

      var form =
        document.querySelector(
          "form#plugin_formcreator_form.plugin_formcreator_form",
        ) ||
        document.querySelector("form.plugin_formcreator_form") ||
        document.querySelector('form[action*="formdisplay.php"]');

      if (!form) {
        debug("formulário não encontrado ainda");
        return false;
      }

      debug("formulário encontrado", {
        id: form.id || "",
        className: form.className || "",
        dataId: form.getAttribute("data-id") || "",
      });

      initialized = true;
      var title = getFormTitle(form);

      form.classList.add("pgeservicos-formcreator-form");
      markOriginalTitle(form);
      var page = wrapForm(form, title);
      debug("wrapper aplicado", {
        hasPage: !!page,
        hasCustomPage: !!document.querySelector(".pgeservicos-formcreator-page"),
        hasCustomForm: form.classList.contains("pgeservicos-formcreator-form"),
      });
      applyMenuTheme(page);
      window.setTimeout(function () {
        applyMenuTheme(page);
      }, 250);
      window.addEventListener("resize", function () {
        applyMenuTheme(page);
      });
      enhanceSections(form);
      enhanceButtons(form);
      enhanceSelectActions(form);
      enhanceFocus(form);
      enhanceMasks(form);
      enhanceMessages(form);

      return true;
    } catch (error) {
      console.warn(
        "PGE Serviços: não foi possível aprimorar o formulário do Form Creator.",
        error,
      );
      return false;
    }
  }

  function scheduleInit() {
    if (init()) {
      return;
    }

    debug("aguardando formulário via MutationObserver");

    if (typeof MutationObserver !== "function") {
      return;
    }

    var observer = new MutationObserver(function () {
      if (init()) {
        observer.disconnect();
      }
    });

    observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
    });

    window.setTimeout(function () {
      observer.disconnect();
    }, 5000);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", scheduleInit);
  } else {
    scheduleInit();
  }
})();
