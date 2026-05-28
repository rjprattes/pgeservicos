(function () {
  "use strict";

  const page = document.querySelector(".pgeservicos-meus-chamados-page");

  if (!page) {
    return;
  }

  const button = page.querySelector("[data-pgeservicos-mark-updates-read]");

  if (!button) {
    return;
  }

  const endpoint = page.getAttribute("data-pgeservicos-mark-updates-url") || "";
  const csrfToken = page.getAttribute("data-pgeservicos-csrf-token") || "";
  const originalButtonHtml = button.innerHTML;

  const copyToastTheme = (region) => {
    const styles = window.getComputedStyle(page);
    const primary = styles.getPropertyValue("--pgeservicos-primary").trim() || styles.getPropertyValue("--tblr-primary").trim() || "#2f7ecb";
    const onPrimary = styles.getPropertyValue("--pgeservicos-on-primary").trim() || "#ffffff";

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

  const setLoading = (loading) => {
    button.disabled = loading;
    button.classList.toggle("is-loading", loading);
    button.innerHTML = loading
      ? "<i class='ti ti-loader-2' aria-hidden='true'></i><span>Marcando...</span>"
      : originalButtonHtml;
  };

  button.addEventListener("click", async () => {
    if (!endpoint || !csrfToken || button.disabled) {
      return;
    }

    const body = new FormData();
    body.set("_glpi_csrf_token", csrfToken);
    setLoading(true);

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        body,
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const payload = await response.json().catch(() => ({}));

      if (!response.ok || payload.ok === false || payload.success === false) {
        throw new Error(payload.message || "Não foi possível marcar as atualizações.");
      }

      showToast(payload.message || "Atualizações marcadas como visualizadas.", "success");
      window.setTimeout(() => {
        window.location.reload();
      }, 850);
    } catch (error) {
      showToast(error.message || "Não foi possível marcar as atualizações.", "error");
      setLoading(false);
    }
  });
})();
