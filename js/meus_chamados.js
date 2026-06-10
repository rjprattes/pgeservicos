(function () {
  "use strict";

  const page = document.querySelector(".pgeservicos-meus-chamados-page");

  if (!page) {
    return;
  }

  const filterForm = page.querySelector("[data-pgeservicos-auto-filters]");
  const resultsShell = page.querySelector("[data-pgeservicos-results-shell]");
  const resultsRegion = page.querySelector("[data-pgeservicos-results]");
  const paginationRegion = page.querySelector("[data-pgeservicos-pagination-region]");
  const summaryRegion = page.querySelector("[data-pgeservicos-summary]");
  let activeRequest = null;
  let autoSubmitTimer = 0;

  const endpoint = page.getAttribute("data-pgeservicos-mark-updates-url") || "";
  const csrfToken = page.getAttribute("data-pgeservicos-csrf-token") || "";

  const copyToastTheme = (region) => {
    const styles = window.getComputedStyle(page);
    const primary = styles.getPropertyValue("--pgeservicos-action-bg").trim()
      || styles.getPropertyValue("--pgeservicos-primary").trim()
      || styles.getPropertyValue("--tblr-primary").trim()
      || "#fec95c";
    const onPrimary = styles.getPropertyValue("--pgeservicos-action-color").trim()
      || styles.getPropertyValue("--pgeservicos-on-primary").trim()
      || "#ffffff";

    region.style.setProperty("--pgeservicos-toast-bg", primary);
    region.style.setProperty("--pgeservicos-toast-color", onPrimary);
    region.style.setProperty("--pgeservicos-primary", primary);
    region.style.setProperty("--pgeservicos-on-primary", onPrimary);
  };

  const toastRegion = () => {
    let region = document.querySelector("[data-pgeservicos-toast-region]");

    if (!region) {
      region = document.createElement("div");
      region.className = "pgeservicos-toast-region";
      region.setAttribute("data-pgeservicos-toast-region", "");
      region.setAttribute("aria-live", "polite");
      region.setAttribute("aria-atomic", "false");
      document.body.appendChild(region);
    }

    copyToastTheme(region);

    while (region.children.length >= 4) {
      region.firstElementChild?.remove();
    }

    return region;
  };

  const showToast = (message, type) => {
    if (typeof window.pgeservicosShowToast === "function") {
      window.pgeservicosShowToast(type, message);
      return;
    }

    const normalizedType = ["success", "error", "warning", "info"].includes(type) ? type : "info";
    const region = toastRegion();
    const toast = document.createElement("div");
    const text = document.createElement("span");
    const close = document.createElement("button");

    toast.className = `pgeservicos-toast is-${normalizedType}`;
    toast.setAttribute("role", normalizedType === "error" ? "alert" : "status");
    text.textContent = message || "Operação concluída.";
    close.type = "button";
    close.textContent = "×";
    close.setAttribute("aria-label", "Fechar notificação");
    close.addEventListener("click", () => {
      toast.remove();
    });

    toast.appendChild(text);
    toast.appendChild(close);
    region.appendChild(toast);

    window.clearTimeout(toast._pgeTimer);
    toast._pgeTimer = window.setTimeout(() => {
      toast.remove();
    }, normalizedType === "error" ? 7600 : 4800);
  };

  const visibleUrlFromParams = (params) => {
    const url = new URL(filterForm ? filterForm.action : window.location.href, window.location.origin);
    const cleanParams = new URLSearchParams(params);
    cleanParams.delete("partial");
    url.search = cleanParams.toString();
    return url;
  };

  const hasValidDateInputs = () => {
    if (!filterForm) {
      return true;
    }

    let valid = true;

    filterForm.querySelectorAll("input[type='date']").forEach((input) => {
      const inputValid = input.value === "" || input.validity.valid;
      input.classList.toggle("is-invalid", !inputValid);

      if (!inputValid) {
        valid = false;
      }
    });

    return valid;
  };

  const normalizeParams = (params, options = {}) => {
    const resetPage = options.resetPage !== false;
    params.delete("partial");

    if (resetPage) {
      params.delete("page");
    }

    for (const [key, value] of Array.from(params.entries())) {
      if (String(value).trim() === "") {
        params.delete(key);
      }
    }

    const query = (params.get("q") || "").trim();

    if (query.length < 3) {
      params.delete("q");
    } else {
      params.set("q", query);
    }

    ["opened", "solved", "closed"].forEach((prefix) => {
      const hasEnabled = params.has(`${prefix}_enabled`);
      const hasFrom = (params.get(`${prefix}_from`) || "").trim() !== "";
      const hasTo = (params.get(`${prefix}_to`) || "").trim() !== "";

      if (!hasEnabled || (!hasFrom && !hasTo)) {
        params.delete(`${prefix}_enabled`);
        params.delete(`${prefix}_from`);
        params.delete(`${prefix}_to`);
      }
    });

    return params;
  };

  const paramsFromForm = (options = {}) => {
    const formData = new FormData(filterForm);
    return normalizeParams(new URLSearchParams(formData), options);
  };

  const syncDateFilter = (dateFilter) => {
    const checkbox = dateFilter.querySelector("input[type='checkbox']");
    const dateInputs = dateFilter.querySelectorAll("input[type='date']");
    const enabled = Boolean(checkbox?.checked);

    dateFilter.classList.toggle("is-enabled", enabled);

    if (!enabled) {
      dateInputs.forEach((input) => {
        input.value = "";
        input.classList.remove("is-invalid");
      });
    }
  };

  const syncAllDateFilters = () => {
    filterForm?.querySelectorAll("[data-pgeservicos-date-filter]").forEach(syncDateFilter);
  };

  const setAdvancedOpen = (open) => {
    const toggle = filterForm?.querySelector("[data-pgeservicos-advanced-toggle]");
    const panel = filterForm?.querySelector("[data-pgeservicos-advanced-panel]");

    if (!toggle || !panel) {
      return;
    }

    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    panel.hidden = !open;
  };

  const updateFilterFormFromUrl = (url) => {
    if (!filterForm) {
      return;
    }

    const params = new URL(url, window.location.origin).searchParams;
    const search = filterForm.querySelector("[data-pgeservicos-search]");
    const status = filterForm.querySelector("select[name='status']");
    const entity = filterForm.querySelector("select[name='entidade']");
    const sort = filterForm.querySelector("select[name='sort']");
    const perPage = filterForm.querySelector("input[name='per_page']");

    if (search) {
      search.value = params.get("q") || "";
    }

    if (status) {
      status.value = params.get("status") || "not_solved";
    }

    if (entity) {
      entity.value = params.get("entidade") || "";
    }

    if (sort) {
      sort.value = params.get("sort") || "updated_desc";
    }

    if (perPage) {
      perPage.value = params.get("per_page") || "20";
    }

    ["opened", "solved", "closed"].forEach((prefix) => {
      const checkbox = filterForm.querySelector(`input[name='${prefix}_enabled']`);
      const from = filterForm.querySelector(`input[name='${prefix}_from']`);
      const to = filterForm.querySelector(`input[name='${prefix}_to']`);
      const enabled = params.has(`${prefix}_enabled`);

      if (checkbox) {
        checkbox.checked = enabled;
      }

      if (from) {
        from.value = enabled ? (params.get(`${prefix}_from`) || "") : "";
      }

      if (to) {
        to.value = enabled ? (params.get(`${prefix}_to`) || "") : "";
      }
    });

    syncAllDateFilters();
    setAdvancedOpen(["opened", "solved", "closed"].some((prefix) => params.has(`${prefix}_enabled`)));
  };

  const setLoading = (loading) => {
    resultsShell?.classList.toggle("is-loading", loading);
    resultsShell?.setAttribute("aria-busy", loading ? "true" : "false");
  };

  const refreshFloatingLayout = () => {
    if (typeof window.pgeservicosUpdateFloatingLayout === "function") {
      window.pgeservicosUpdateFloatingLayout();
    }
  };

  const fetchPartial = async (url, options = {}) => {
    if (!filterForm || !resultsRegion || !paginationRegion || !summaryRegion) {
      window.location.assign(url);
      return;
    }

    if (!hasValidDateInputs()) {
      return;
    }

    const visibleUrl = new URL(url, window.location.origin);
    visibleUrl.searchParams.delete("partial");

    const requestUrl = new URL(visibleUrl.href);
    requestUrl.searchParams.set("partial", "1");

    if (activeRequest) {
      activeRequest.abort();
    }

    const controller = new AbortController();
    activeRequest = controller;
    setLoading(true);

    try {
      const response = await fetch(requestUrl.href, {
        method: "GET",
        credentials: "same-origin",
        signal: controller.signal,
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-PGESERVICOS-PARTIAL": "1",
        },
      });
      const payload = await response.json().catch(() => ({}));

      if (!response.ok || payload.ok === false) {
        throw new Error(payload.message || "Não foi possível atualizar a listagem.");
      }

      summaryRegion.innerHTML = payload.summary_html || "";
      resultsRegion.innerHTML = payload.list_html || "";
      paginationRegion.innerHTML = payload.pagination_html || "";

      const activeFilters = filterForm.querySelector("[data-pgeservicos-active-filters]");

      if (activeFilters) {
        activeFilters.innerHTML = payload.active_filters_html || "";
      }

      const perPageInput = filterForm.querySelector("input[name='per_page']");

      if (perPageInput && payload.per_page) {
        perPageInput.value = String(payload.per_page);
      }

      const nextUrl = payload.url ? new URL(payload.url, window.location.origin) : visibleUrl;
      nextUrl.searchParams.delete("partial");

      if (options.updateHistory !== false) {
        window.history.replaceState({}, "", nextUrl.href);
      }

      refreshFloatingLayout();
    } catch (error) {
      if (error.name === "AbortError") {
        return;
      }

      showToast(error.message || "Não foi possível atualizar a listagem.", "error");
      window.location.assign(visibleUrl.href);
    } finally {
      if (activeRequest === controller) {
        activeRequest = null;
      }

      setLoading(false);
    }
  };

  const submitFilters = (options = {}) => {
    if (!filterForm) {
      return;
    }

    const params = paramsFromForm({ resetPage: options.resetPage !== false });
    const targetUrl = visibleUrlFromParams(params);
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.delete("partial");

    if (targetUrl.href === currentUrl.href && options.force !== true) {
      return;
    }

    fetchPartial(targetUrl.href, options);
  };

  const scheduleSubmit = (delay, options = {}) => {
    window.clearTimeout(autoSubmitTimer);
    autoSubmitTimer = window.setTimeout(() => submitFilters(options), delay);
  };

  if (filterForm) {
    syncAllDateFilters();

    filterForm.querySelectorAll("[data-pgeservicos-auto-submit]").forEach((field) => {
      field.addEventListener("change", () => scheduleSubmit(0));
    });

    const searchInput = filterForm.querySelector("[data-pgeservicos-search]");

    if (searchInput) {
      searchInput.addEventListener("input", () => {
        const value = searchInput.value.trim();
        const currentParams = new URLSearchParams(window.location.search);

        if (value.length === 0 || value.length >= 3 || currentParams.has("q")) {
          scheduleSubmit(450);
        }
      });
    }

    filterForm.querySelectorAll("[data-pgeservicos-date-filter]").forEach((dateFilter) => {
      const checkbox = dateFilter.querySelector("input[type='checkbox']");
      const dateInputs = dateFilter.querySelectorAll("input[type='date']");

      checkbox?.addEventListener("change", () => {
        syncDateFilter(dateFilter);

        if (!checkbox.checked) {
          scheduleSubmit(0);
          return;
        }

        const hasValue = Array.from(dateInputs).some((input) => input.value !== "");

        if (hasValue) {
          scheduleSubmit(250);
        }
      });

      dateInputs.forEach((input) => {
        input.addEventListener("change", () => {
          if (!input.validity.valid) {
            input.classList.add("is-invalid");
            return;
          }

          input.classList.remove("is-invalid");
          scheduleSubmit(250);
        });
      });
    });

    filterForm.addEventListener("click", (event) => {
      const advancedToggle = event.target.closest("[data-pgeservicos-advanced-toggle]");

      if (advancedToggle) {
        event.preventDefault();
        setAdvancedOpen(advancedToggle.getAttribute("aria-expanded") !== "true");
        return;
      }

      const clearAll = event.target.closest("[data-pgeservicos-clear-filters]");

      if (clearAll) {
        event.preventDefault();
        filterForm.reset();

        const status = filterForm.querySelector("select[name='status']");
        const entity = filterForm.querySelector("select[name='entidade']");
        const sort = filterForm.querySelector("select[name='sort']");
        const search = filterForm.querySelector("[data-pgeservicos-search]");
        const perPage = filterForm.querySelector("input[name='per_page']");

        if (status) status.value = "not_solved";
        if (entity) entity.value = "";
        if (sort) sort.value = "updated_desc";
        if (search) search.value = "";
        if (perPage) perPage.value = "20";

        syncAllDateFilters();
        setAdvancedOpen(false);
        fetchPartial(clearAll.href, { force: true });
        return;
      }

      const chipButton = event.target.closest("[data-pgeservicos-clear-filter]");

      if (!chipButton) {
        return;
      }

      event.preventDefault();

      const filterKey = chipButton.getAttribute("data-pgeservicos-clear-filter");

      if (filterKey === "q") {
        const search = filterForm.querySelector("[data-pgeservicos-search]");
        if (search) search.value = "";
      } else if (filterKey === "status") {
        const status = filterForm.querySelector("select[name='status']");
        if (status) status.value = "not_solved";
      } else if (filterKey === "entidade") {
        const entity = filterForm.querySelector("select[name='entidade']");
        if (entity) entity.value = "";
      } else if (filterKey === "sort") {
        const sort = filterForm.querySelector("select[name='sort']");
        if (sort) sort.value = "updated_desc";
      } else if (["opened", "solved", "closed"].includes(filterKey)) {
        const checkbox = filterForm.querySelector(`input[name='${filterKey}_enabled']`);
        const from = filterForm.querySelector(`input[name='${filterKey}_from']`);
        const to = filterForm.querySelector(`input[name='${filterKey}_to']`);
        if (checkbox) checkbox.checked = false;
        if (from) from.value = "";
        if (to) to.value = "";
        const dateFilter = filterForm.querySelector(`[data-pgeservicos-date-filter='${filterKey}']`);
        if (dateFilter) syncDateFilter(dateFilter);
      }

      scheduleSubmit(0);
    });

    filterForm.addEventListener("submit", (event) => {
      event.preventDefault();
      submitFilters({ force: true });
    });
  }

  page.addEventListener("click", (event) => {
    const paginationLink = event.target.closest("[data-pgeservicos-pagination-region] .pgeservicos-pagination nav a");

    if (paginationLink && filterForm) {
      event.preventDefault();
      fetchPartial(paginationLink.href, { resetPage: false });
      return;
    }

    const button = event.target.closest("[data-pgeservicos-mark-updates-read]");

    if (!button || !endpoint || !csrfToken || button.disabled) {
      return;
    }

    const originalButtonHtml = button.innerHTML;
    const body = new FormData();
    body.set("_glpi_csrf_token", csrfToken);
    button.disabled = true;
    button.classList.add("is-loading");
    button.innerHTML = "<i class='ti ti-loader-2' aria-hidden='true'></i><span>Marcando...</span>";

    fetch(endpoint, {
      method: "POST",
      body,
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    }).then(async (response) => {
      const payload = await response.json().catch(() => ({}));

      if (!response.ok || payload.ok === false || payload.success === false) {
        throw new Error(payload.message || "Não foi possível marcar as atualizações.");
      }

      showToast(payload.message || "Atualizações marcadas como visualizadas.", "success");
      submitFilters({ force: true, resetPage: false });
    }).catch((error) => {
      showToast(error.message || "Não foi possível marcar as atualizações.", "error");
    }).finally(() => {
      button.disabled = false;
      button.classList.remove("is-loading");
      button.innerHTML = originalButtonHtml;
    });
  });

  page.addEventListener("change", (event) => {
    const perPageSelect = event.target.closest("[data-pgeservicos-per-page]");

    if (!perPageSelect || !filterForm) {
      return;
    }

    event.preventDefault();
    const params = new URLSearchParams(window.location.search);
    params.set("per_page", perPageSelect.value);
    params.delete("page");
    fetchPartial(visibleUrlFromParams(normalizeParams(params)).href);
  });

  window.addEventListener("popstate", () => {
    if (!filterForm) {
      return;
    }

    updateFilterFormFromUrl(window.location.href);
    fetchPartial(window.location.href, { updateHistory: false, resetPage: false, force: true });
  });
})();
