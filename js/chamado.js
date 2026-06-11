(function () {
  "use strict";

  function normalizeEscapedNewlines(value) {
    return String(value || "").replace(/\\r\\n|\\n|\\r/g, "<br>");
  }

  function syncEditor(form) {
    const editor = form.querySelector(".pgeservicos-rich-editor");
    const input = form.querySelector(".pgeservicos-rich-input");

    if (editor && input) {
      input.value = normalizeEscapedNewlines(editor.innerHTML.trim());
    }
  }

  function closeActionPanels(root) {
    root.querySelectorAll("[data-pgeservicos-action-panel]").forEach((panel) => {
      panel.hidden = true;
    });

    root.querySelectorAll("[data-pgeservicos-action-tab]").forEach((button) => {
      button.classList.remove("is-active");
    });
  }

  function runCommand(button) {
    const command = button.getAttribute("data-pgeservicos-command");
    const insertion = button.getAttribute("data-pgeservicos-insert");
    const form = button.closest("label, form");
    const editor = form?.querySelector(".pgeservicos-rich-editor");

    if (!command || !editor) {
      return;
    }

    editor.focus();

    if (insertion) {
      document.execCommand("insertText", false, insertion);
      return;
    }

    if (command === "createLink" || command === "insertImage") {
      showUrlEditor(button, editor, command);
      return;
    }

    if (command === "insertTable") {
      document.execCommand("insertHTML", false, "<table><tbody><tr><td>Conteúdo</td><td>Conteúdo</td></tr><tr><td>Conteúdo</td><td>Conteúdo</td></tr></tbody></table><p><br></p>");
      return;
    }

    document.execCommand(command, false, null);
  }

  function showUrlEditor(button, editor, command) {
    const toolbar = button.closest(".pgeservicos-rich-toolbar");

    if (!toolbar) {
      return;
    }

    toolbar.querySelector(".pgeservicos-rich-link-popover")?.remove();

    const selection = window.getSelection();
    const range = selection && selection.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
    const popover = document.createElement("span");
    const input = document.createElement("input");
    const apply = document.createElement("button");
    const cancel = document.createElement("button");

    popover.className = "pgeservicos-rich-link-popover";
    input.type = "url";
    input.placeholder = command === "insertImage" ? "URL da imagem" : "https://...";
    input.autocomplete = "off";
    apply.type = "button";
    apply.textContent = "Aplicar";
    cancel.type = "button";
    cancel.textContent = "Cancelar";

    apply.addEventListener("click", function () {
      const url = input.value.trim();

      if (!url) {
        input.focus();
        return;
      }

      editor.focus();

      if (range && selection) {
        selection.removeAllRanges();
        selection.addRange(range);
      }

      document.execCommand(command, false, url);
      popover.remove();
    });

    cancel.addEventListener("click", function () {
      popover.remove();
      editor.focus();
    });

    popover.appendChild(input);
    popover.appendChild(apply);
    popover.appendChild(cancel);
    toolbar.appendChild(popover);
    input.focus();
  }

  function applyRichFormat(select) {
    const form = select.closest("label, form");
    const editor = form?.querySelector(".pgeservicos-rich-editor");
    const value = select.value || "p";

    if (!editor) {
      return;
    }

    editor.focus();
    document.execCommand("formatBlock", false, value);
    select.value = "p";
  }

  function debounce(fn, delay) {
    let timeout;

    return function (...args) {
      window.clearTimeout(timeout);
      timeout = window.setTimeout(function () {
        fn.apply(null, args);
      }, delay);
    };
  }

  function isTicketClosed(page) {
    return page?.getAttribute("data-ticket-closed") === "1";
  }

  function copyActionVars(page, target) {
    if (!page || !target) {
      return;
    }

    const computed = window.getComputedStyle(page);
    [
      "--pgeservicos-action-bg",
      "--pgeservicos-action-color",
      "--pgeservicos-toast-bg",
      "--pgeservicos-toast-color",
      "--pgeservicos-scroll-btn-bg",
      "--pgeservicos-scroll-btn-color",
    ].forEach(function (name) {
      const value = page.style.getPropertyValue(name) || computed.getPropertyValue(name);

      if (value) {
        target.style.setProperty(name, value.trim());
      }
    });
  }

  function formatFileSize(bytes) {
    const size = Number(bytes || 0);

    if (size >= 1024 * 1024) {
      return (Math.round((size / 1024 / 1024) * 100) / 100) + " MB";
    }

    if (size >= 1024) {
      return (Math.round((size / 1024) * 100) / 100) + " KB";
    }

    return size + " bytes";
  }

  function validateFormUploads(page, form) {
    const maxBytes = Number(page?.getAttribute("data-upload-max-bytes") || 0);
    const maxLabel = page?.getAttribute("data-upload-max-label") || (maxBytes > 0 ? formatFileSize(maxBytes) : "");

    if (!maxBytes || maxBytes <= 0) {
      return { ok: true };
    }

    const inputs = Array.from(form.querySelectorAll('input[type="file"]'));

    for (const input of inputs) {
      const files = Array.from(input.files || []);

      for (const file of files) {
        if (file.size > maxBytes) {
          return {
            ok: false,
            message: "Arquivo muito grande. Limite máximo: " + maxLabel + ". Arquivo enviado: " + formatFileSize(file.size) + ". Arquivo: " + file.name + ". Escolha um arquivo menor antes de enviar.",
          };
        }
      }
    }

    return { ok: true };
  }

  function pgeservicosShowToast(type, message) {
    const normalizedType = ["success", "error", "warning", "info"].includes(type) ? type : "info";
    let region = document.querySelector("[data-pgeservicos-toast-region]");

    if (!region) {
      region = document.createElement("div");
      region.className = "pgeservicos-toast-region";
      region.setAttribute("data-pgeservicos-toast-region", "");
      region.setAttribute("aria-live", "polite");
      region.setAttribute("aria-atomic", "false");
      document.body.appendChild(region);
    }

    const page = document.querySelector(".pgeservicos-chamado-page");
    if (page) {
      copyActionVars(page, region);
      ["--pgeservicos-content-left", "--pgeservicos-content-width", "--pgeservicos-toast-bottom"].forEach(function (name) {
        const value = page.style.getPropertyValue(name) || window.getComputedStyle(page).getPropertyValue(name);
        if (value) {
          region.style.setProperty(name, value.trim());
        }
      });
    }

    while (region.children.length >= 4) {
      region.firstElementChild?.remove();
    }

    const toast = document.createElement("div");
    const text = document.createElement("span");
    const close = document.createElement("button");

    toast.className = "pgeservicos-toast is-" + normalizedType;
    toast.setAttribute("role", normalizedType === "error" ? "alert" : "status");
    text.textContent = message || "Operação concluída.";
    close.type = "button";
    close.textContent = "×";
    close.setAttribute("aria-label", "Fechar notificação");
    close.addEventListener("click", function () {
      toast.remove();
    });
    toast.appendChild(text);
    toast.appendChild(close);
    region.appendChild(toast);

    window.clearTimeout(toast._pgeTimer);
    toast._pgeTimer = window.setTimeout(function () {
      toast.remove();
    }, normalizedType === "error" ? 7600 : 4800);
  }

  window.pgeservicosShowToast = pgeservicosShowToast;

  function showActorFeedback(page, message, type) {
    pgeservicosShowToast(type === "error" ? "error" : "success", message);
  }

  function actionFormRequest(form, submitter) {
    const actionUrl = form.getAttribute("action");

    if (!actionUrl) {
      return Promise.resolve({ ok: false, message: "Formulário sem destino definido." });
    }

    const body = new FormData(form);

    if (submitter && submitter.name && !body.has(submitter.name)) {
      body.append(submitter.name, submitter.value);
    }

    return fetch(actionUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body,
    })
      .then((response) => response.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (error) {
          console.error("Resposta inválida da ação do chamado:", text);
          return { ok: false, message: "Não foi possível concluir a ação. Recarregue a página e tente novamente." };
        }
      }))
      .catch(() => ({ ok: false, message: "Não foi possível comunicar com o servidor." }));
  }

  function timelineDeleteRequest(page, itemtype, itemsId) {
    const actionUrl = page.getAttribute("data-action-url");
    const ticketsId = page.getAttribute("data-tickets-id");
    const csrf = page.getAttribute("data-csrf-token");

    if (!actionUrl || !ticketsId || !csrf) {
      return Promise.resolve({ ok: false, message: "Configuração de exclusão indisponível." });
    }

    const body = new FormData();
    body.set("_glpi_csrf_token", csrf);
    body.set("tickets_id", ticketsId);
    body.set("pgeservicos_action", "delete_timeline_item");
    body.set("itemtype", itemtype || "");
    body.set("items_id", itemsId || "");

    return fetch(actionUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body,
    })
      .then((response) => response.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (error) {
          console.error("Resposta inválida ao excluir item da timeline:", text);
          return { ok: false, message: "Não foi possível excluir o item. Recarregue a página e tente novamente." };
        }
      }))
      .catch(() => ({ ok: false, message: "Não foi possível comunicar com o servidor." }));
  }

  function closeTimelineDeleteConfirm(article) {
    article?.querySelector("[data-pgeservicos-delete-confirm]")?.remove();
  }

  function attachmentDeleteRequest(page, attachment) {
    const actionUrl = page.getAttribute("data-action-url");
    const ticketsId = page.getAttribute("data-tickets-id");
    const csrf = page.getAttribute("data-csrf-token");

    if (!actionUrl || !ticketsId || !csrf || !attachment) {
      return Promise.resolve({ ok: false, message: "Configuração de remoção do anexo indisponível." });
    }

    const body = new FormData();
    body.set("_glpi_csrf_token", csrf);
    body.set("tickets_id", ticketsId);
    body.set("pgeservicos_action", "delete_attachment");
    body.set("itemtype", attachment.getAttribute("data-itemtype") || "");
    body.set("items_id", attachment.getAttribute("data-items-id") || "");
    body.set("documents_id", attachment.getAttribute("data-document-id") || "");
    body.set("document_items_id", attachment.getAttribute("data-document-item-id") || "");

    return fetch(actionUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body,
    })
      .then((response) => response.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (error) {
          console.error("Resposta inválida ao remover anexo:", text);
          return { ok: false, message: "Não foi possível remover o anexo. Recarregue a página e tente novamente." };
        }
      }))
      .catch(() => ({ ok: false, message: "Não foi possível comunicar com o servidor." }));
  }

  function closeAttachmentDeleteConfirm(attachment) {
    attachment?.querySelector("[data-pgeservicos-attachment-delete-confirm]")?.remove();
  }

  function showAttachmentDeleteConfirm(page, trigger) {
    const attachment = trigger.closest("[data-pgeservicos-edit-attachment]");

    if (!attachment) {
      pgeservicosShowToast("error", "Anexo inválido.");
      return;
    }

    closeAttachmentDeleteConfirm(attachment);

    const confirm = document.createElement("div");
    const text = document.createElement("p");
    const actions = document.createElement("div");
    const cancel = document.createElement("button");
    const remove = document.createElement("button");

    confirm.className = "pgeservicos-attachment-delete-confirm";
    confirm.dataset.pgeservicosAttachmentDeleteConfirm = "";
    confirm.setAttribute("role", "alertdialog");
    confirm.setAttribute("aria-label", "Confirmar remoção de anexo");
    text.textContent = "Tem certeza que deseja remover este anexo?";
    actions.className = "pgeservicos-timeline-delete-actions";

    cancel.type = "button";
    cancel.className = "pgeservicos-chamado-secondary";
    cancel.textContent = "Cancelar";
    cancel.addEventListener("click", function () {
      closeAttachmentDeleteConfirm(attachment);
    });

    remove.type = "button";
    remove.className = "pgeservicos-chamado-danger";
    remove.textContent = "Remover";
    remove.addEventListener("click", function () {
      remove.disabled = true;
      cancel.disabled = true;

      attachmentDeleteRequest(page, attachment).then((payload) => {
        const ok = Boolean(payload.ok || payload.success);
        pgeservicosShowToast(ok ? "success" : "error", payload.message || (ok ? "Anexo removido com sucesso." : "Não foi possível remover o anexo."));

        if (ok && payload.reload !== false) {
          window.setTimeout(function () {
            window.location.reload();
          }, 520);
          return;
        }

        if (ok) {
          attachment.remove();
          return;
        }

        remove.disabled = false;
        cancel.disabled = false;
      });
    });

    actions.appendChild(cancel);
    actions.appendChild(remove);
    confirm.appendChild(text);
    confirm.appendChild(actions);
    attachment.appendChild(confirm);
    cancel.focus();
  }

  function showTimelineDeleteConfirm(page, trigger) {
    const article = trigger.closest("[data-pgeservicos-timeline-item]");
    const bubble = article?.querySelector(".pgeservicos-chamado-message-bubble");

    if (!article || !bubble) {
      return;
    }

    const itemtype = article.getAttribute("data-pgeservicos-delete-itemtype") || "";
    const itemsId = article.getAttribute("data-pgeservicos-delete-id") || "";

    if (!itemtype || !itemsId) {
      pgeservicosShowToast("error", "Item inválido.");
      return;
    }

    closeTimelineDeleteConfirm(article);

    const confirm = document.createElement("div");
    const text = document.createElement("p");
    const actions = document.createElement("div");
    const cancel = document.createElement("button");
    const remove = document.createElement("button");

    confirm.className = "pgeservicos-timeline-delete-confirm";
    confirm.dataset.pgeservicosDeleteConfirm = "";
    confirm.setAttribute("role", "alertdialog");
    confirm.setAttribute("aria-label", "Confirmar exclusão");

    text.textContent = "Tem certeza que deseja excluir este item da linha do tempo?";
    actions.className = "pgeservicos-timeline-delete-actions";

    cancel.type = "button";
    cancel.className = "pgeservicos-chamado-secondary";
    cancel.textContent = "Cancelar";
    cancel.addEventListener("click", function () {
      closeTimelineDeleteConfirm(article);
    });

    remove.type = "button";
    remove.className = "pgeservicos-chamado-danger";
    remove.textContent = "Excluir";
    remove.addEventListener("click", function () {
      remove.disabled = true;
      cancel.disabled = true;

      timelineDeleteRequest(page, itemtype, itemsId).then((payload) => {
        const ok = Boolean(payload.ok || payload.success);
        pgeservicosShowToast(ok ? "success" : "error", payload.message || (ok ? "Item excluído com sucesso." : "Não foi possível excluir o item."));

        if (ok && payload.reload !== false) {
          window.setTimeout(function () {
            window.location.reload();
          }, 520);
          return;
        }

        remove.disabled = false;
        cancel.disabled = false;
      });
    });

    actions.appendChild(cancel);
    actions.appendChild(remove);
    confirm.appendChild(text);
    confirm.appendChild(actions);
    bubble.appendChild(confirm);
    cancel.focus();
  }

  function setFormBusy(form, busy) {
    form.classList.toggle("is-submitting", busy);
    form.querySelectorAll("button, input, select, textarea").forEach((control) => {
      if (busy) {
        control.dataset.pgeservicosWasDisabled = control.disabled ? "1" : "0";
        control.disabled = true;
      } else if (control.dataset.pgeservicosWasDisabled !== "1") {
        control.disabled = false;
        delete control.dataset.pgeservicosWasDisabled;
      }
    });
  }

  function actorRequest(page, payload) {
    const actionUrl = page.getAttribute("data-actor-update-url");
    const ticketsId = page.getAttribute("data-tickets-id");
    const csrf = page.getAttribute("data-csrf-token");

    if (!actionUrl || !ticketsId || !csrf) {
      return Promise.resolve({ ok: false, message: "Configuração de atores indisponível." });
    }

    const body = new FormData();
    body.set("_glpi_csrf_token", csrf);
    body.set("tickets_id", ticketsId);

    Object.keys(payload).forEach((key) => {
      body.set(key, payload[key]);
    });

    return fetch(actionUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
      body,
    })
      .then((response) => response.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (error) {
          console.error("Resposta inválida ao atualizar ator:", text);
          return {
            ok: false,
            message: "Não foi possível atualizar o ator. Recarregue a página e tente novamente.",
          };
        }
      }))
      .catch(() => ({ ok: false, message: "Não foi possível comunicar com o servidor." }));
  }

  function fieldRequest(page, field, value) {
    const actionUrl = page.getAttribute("data-field-update-url");
    const ticketsId = page.getAttribute("data-tickets-id");
    const csrf = page.getAttribute("data-csrf-token");

    if (!actionUrl || !ticketsId || !csrf) {
      return Promise.resolve({ ok: false, message: "Configuração de campos indisponível." });
    }

    const body = new FormData();
    body.set("_glpi_csrf_token", csrf);
    body.set("tickets_id", ticketsId);
    body.set("field", field);
    body.set("value", value);

    return fetch(actionUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
      body,
    })
      .then((response) => response.json().catch(() => ({ ok: false, message: "Resposta inválida do servidor." })))
      .catch(() => ({ ok: false, message: "Não foi possível comunicar com o servidor." }));
  }

  function fieldSearch(page, field, term) {
    const endpoint = page.getAttribute("data-field-search-url");
    const ticketsId = page.getAttribute("data-tickets-id");
    const csrf = page.getAttribute("data-csrf-token");

    if (!endpoint || !ticketsId || !csrf) {
      return Promise.resolve({ ok: false, results: [], message: "Configuração de pesquisa indisponível." });
    }

    const url = new URL(endpoint, window.location.origin);
    url.searchParams.set("_glpi_csrf_token", csrf);
    url.searchParams.set("tickets_id", ticketsId);
    url.searchParams.set("field", field);
    url.searchParams.set("q", term || "");

    return fetch(url.toString(), {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    })
      .then((response) => response.json().catch(() => ({ ok: false, results: [], message: "Resposta inválida do servidor." })))
      .catch(() => ({ ok: false, results: [], message: "Erro ao buscar opções." }));
  }

  function slaSearch(page, field, term) {
    const endpoint = page.getAttribute("data-sla-search-url");
    const ticketsId = page.getAttribute("data-tickets-id");
    const csrf = page.getAttribute("data-csrf-token");

    if (!endpoint || !ticketsId || !csrf) {
      return Promise.resolve({ ok: false, results: [], message: "Configuração de SLA indisponível." });
    }

    const url = new URL(endpoint, window.location.origin);
    url.searchParams.set("_glpi_csrf_token", csrf);
    url.searchParams.set("tickets_id", ticketsId);
    url.searchParams.set("field", field);
    url.searchParams.set("q", term || "");

    return fetch(url.toString(), {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    })
      .then((response) => response.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (error) {
          console.error("Resposta inválida ao buscar SLA/OLA:", text);
          return { ok: false, results: [], message: "Erro ao buscar SLA/OLA." };
        }
      }))
      .catch(() => ({ ok: false, results: [], message: "Erro ao buscar SLA/OLA." }));
  }

  function slaRequest(page, payload) {
    const actionUrl = page.getAttribute("data-sla-update-url");
    const ticketsId = page.getAttribute("data-tickets-id");
    const csrf = page.getAttribute("data-csrf-token");

    if (!actionUrl || !ticketsId || !csrf) {
      return Promise.resolve({ ok: false, message: "Configuração de SLA indisponível." });
    }

    const body = new FormData();
    body.set("_glpi_csrf_token", csrf);
    body.set("tickets_id", ticketsId);

    Object.keys(payload).forEach((key) => {
      body.set(key, payload[key]);
    });

    return fetch(actionUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
      body,
    })
      .then((response) => response.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (error) {
          console.error("Resposta inválida ao atualizar SLA/OLA:", text);
          return { ok: false, message: "Não foi possível atualizar a SLA/OLA." };
        }
      }))
      .catch(() => ({ ok: false, message: "Não foi possível comunicar com o servidor." }));
  }

  function actionTargetSearch(page, targetType, term) {
    const endpoint = page.getAttribute("data-action-target-search-url");
    const ticketsId = page.getAttribute("data-tickets-id");
    const csrf = page.getAttribute("data-csrf-token");

    if (!endpoint || !ticketsId || !csrf) {
      return Promise.resolve({ ok: false, results: [], message: "Configuração de pesquisa indisponível." });
    }

    const url = new URL(endpoint, window.location.origin);
    url.searchParams.set("_glpi_csrf_token", csrf);
    url.searchParams.set("tickets_id", ticketsId);
    url.searchParams.set("target_type", targetType || "");
    url.searchParams.set("q", term || "");

    return fetch(url.toString(), {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    })
      .then((response) => response.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (error) {
          console.error("Resposta inválida ao buscar alvo da ação:", text);
          return { ok: false, results: [], message: "Erro ao buscar opções." };
        }
      }))
      .catch(() => ({ ok: false, results: [], message: "Erro ao buscar opções." }));
  }

  function closeSlaPickers(page, except) {
    page.querySelectorAll("[data-pgeservicos-sla-picker]").forEach((picker) => {
      if (except && picker === except) {
        return;
      }

      const results = picker.querySelector("[data-pgeservicos-sla-results]");

      picker.hidden = true;

      if (results) {
        results.hidden = true;
        results.innerHTML = "";
      }
    });
  }

  function closeActionLookups(page, except) {
    page.querySelectorAll("[data-pgeservicos-action-lookup]").forEach((lookup) => {
      if (except && lookup === except) {
        return;
      }

      const results = lookup.querySelector("[data-pgeservicos-action-lookup-results]");

      lookup.classList.remove("is-open");

      if (results) {
        results.hidden = true;
        results.innerHTML = "";
      }
    });
  }

  function setActionLookupSelection(lookup, item) {
    const hidden = lookup.querySelector("[data-pgeservicos-action-lookup-value]");
    const selection = lookup.querySelector("[data-pgeservicos-action-lookup-selection]");
    const input = lookup.querySelector("[data-pgeservicos-action-lookup-search]");

    if (hidden) {
      hidden.value = item ? item.id : "";
    }

    if (input) {
      input.value = "";
    }

    if (!selection) {
      return;
    }

    selection.innerHTML = "";

    if (!item) {
      selection.hidden = true;
      return;
    }

    const label = document.createElement("span");
    const clear = document.createElement("button");
    label.className = "pgeservicos-action-lookup-label";
    label.textContent = item.label;
    clear.type = "button";
    clear.textContent = "×";
    clear.className = "pgeservicos-action-lookup-clear";
    clear.setAttribute("aria-label", "Limpar seleção");
    clear.addEventListener("click", function () {
      setActionLookupSelection(lookup, null);
      input?.focus();
    });

    selection.appendChild(label);
    selection.appendChild(clear);
    selection.hidden = false;
  }

  function renderActionLookupResults(page, lookup, items, message) {
    const results = lookup.querySelector("[data-pgeservicos-action-lookup-results]");
    const input = lookup.querySelector("[data-pgeservicos-action-lookup-search]");

    if (!results) {
      return;
    }

    results.innerHTML = "";

    if (!items.length) {
      const empty = document.createElement("span");
      empty.className = "pgeservicos-action-lookup-message";
      empty.textContent = message || "Nenhum resultado encontrado.";
      results.appendChild(empty);
      results.hidden = false;
      lookup.classList.add("is-open");
      return;
    }

    items.forEach((item) => {
      const button = document.createElement("button");
      const label = document.createElement("strong");
      const meta = document.createElement("small");

      button.type = "button";
      label.textContent = item.label;
      meta.textContent = item.entity ? (item.type_label + " · " + item.entity) : item.type_label;
      button.appendChild(label);
      button.appendChild(meta);
      button.addEventListener("click", function () {
        setActionLookupSelection(lookup, item.empty || String(item.id) === "0" ? null : item);
        results.hidden = true;
        results.innerHTML = "";
        lookup.classList.remove("is-open");
        input?.focus();
      });
      results.appendChild(button);
    });

    results.hidden = false;
    lookup.classList.add("is-open");
  }

  function isSlaLocked(row) {
    return row?.getAttribute("data-sla-locked") === "1" || row?.classList.contains("is-sla-locked");
  }

  function setDeadlineReadValue(row, label) {
    const read = row.querySelector("[data-pgeservicos-field-open]");

    if (!read) {
      return;
    }

    const normalized = label || "-";
    const empty = normalized === "-" || normalized === "";

    read.classList.toggle("is-empty", empty);

    if (!empty) {
      read.textContent = normalized;
      return;
    }

    read.innerHTML = "";

    const title = document.createElement("span");
    const icon = document.createElement("i");
    title.className = "pgeservicos-deadline-empty-title";
    icon.className = "ti ti-calendar-time";
    icon.setAttribute("aria-hidden", "true");
    title.appendChild(icon);
    title.appendChild(document.createTextNode(" Nenhum prazo definido"));
    read.appendChild(title);

    if (row.classList.contains("is-editable") && !isSlaLocked(row) && !isTicketClosed(row.closest(".pgeservicos-chamado-page"))) {
      const hint = document.createElement("span");
      hint.className = "pgeservicos-deadline-empty-hint";
      hint.textContent = "Clique para definir uma data manual";
      read.appendChild(hint);
    }
  }

  function updateSlaRow(row, payload) {
    const read = row.querySelector("[data-pgeservicos-field-open]");
    const control = row.querySelector("[data-pgeservicos-field-control]");
    const wrap = row.querySelector("[data-pgeservicos-sla-chip-wrap]");
    const assign = row.querySelector("[data-pgeservicos-sla-open]");
    const label = payload.date_label || "-";
    const hasAgreement = Number(payload.agreement_id || 0) > 0;
    const agreementName = payload.agreement_name || ((payload.agreement_label || "SLA/OLA") + " #" + payload.agreement_id);

    row.classList.toggle("is-sla-locked", hasAgreement);
    row.setAttribute("data-sla-locked", hasAgreement ? "1" : "0");

    if (read) {
      read.classList.toggle("is-readonly", hasAgreement);
      setDeadlineReadValue(row, label);
    }

    if (control) {
      const raw = String(payload.date_value || "").replace(" ", "T").slice(0, 16);
      control.value = raw;
      control.setAttribute("data-current-label", label);
      control.disabled = hasAgreement;

      if (hasAgreement) {
        control.setAttribute("aria-disabled", "true");
        control.setAttribute("data-sla-locked", "1");
      } else {
        control.removeAttribute("aria-disabled");
        control.removeAttribute("data-sla-locked");
      }
    }

    if (wrap) {
      wrap.innerHTML = "";

      if (hasAgreement) {
        const chip = document.createElement("span");
        const icon = document.createElement("i");
        const name = document.createElement("span");
        const remove = document.createElement("button");

        chip.className = "pgeservicos-sla-chip";
        chip.setAttribute("data-pgeservicos-sla-chip", "");
        chip.dataset.agreementId = payload.agreement_id;
        chip.title = agreementName;
        icon.className = "ti ti-stopwatch";
        name.className = "pgeservicos-sla-chip-label";
        name.textContent = agreementName;
        remove.type = "button";
        remove.className = "pgeservicos-sla-remove";
        remove.textContent = "×";
        remove.setAttribute("data-pgeservicos-sla-remove", "");
        remove.setAttribute("aria-label", "Remover " + (payload.agreement_label || "SLA/OLA"));
        chip.appendChild(icon);
        chip.appendChild(name);
        chip.appendChild(remove);
        wrap.appendChild(chip);
      }
    }

    if (assign) {
      assign.hidden = hasAgreement;
    }
  }

  function renderSlaResults(page, row, items, message) {
    const picker = row.querySelector("[data-pgeservicos-sla-picker]");
    const results = row.querySelector("[data-pgeservicos-sla-results]");

    if (!picker || !results) {
      return;
    }

    results.innerHTML = "";

    if (!items.length) {
      const empty = document.createElement("span");
      empty.className = "pgeservicos-sla-result-message";
      empty.textContent = message || "Nenhuma SLA/OLA encontrada.";
      results.appendChild(empty);
      results.hidden = false;
      picker.hidden = false;
      return;
    }

    items.forEach((item) => {
      const button = document.createElement("button");
      const label = document.createElement("strong");
      const meta = document.createElement("small");

      button.type = "button";
      label.textContent = item.label;
      meta.textContent = item.entity ? item.entity : (item.agreement_label || "SLA/OLA");
      button.appendChild(label);
      button.appendChild(meta);
      button.addEventListener("click", function () {
        slaRequest(page, {
          action: "assign_sla",
          field: row.getAttribute("data-sla-field") || row.getAttribute("data-field"),
          agreement_id: item.id,
        }).then((payload) => {
          showFieldMessage(row, payload.message || (payload.ok ? "SLA/OLA atribuída." : "Não foi possível atribuir."), payload.ok ? "success" : "error");

          if (payload.ok) {
            updateSlaRow(row, payload);
            closeSlaPickers(page);
          }
        });
      });
      results.appendChild(button);
    });

    picker.hidden = false;
    results.hidden = false;
  }

  function closeFieldDropdowns(page, except) {
    page.querySelectorAll("[data-pgeservicos-field-row]").forEach((row) => {
      if (except && row === except) {
        return;
      }

      const results = row.querySelector("[data-pgeservicos-field-results]");

      if (results) {
        results.hidden = true;
        results.innerHTML = "";
      }

      row.classList.remove("is-editing");
    });
  }

  function showFieldMessage(row, message, type) {
    pgeservicosShowToast(type === "error" ? "error" : "success", message);
  }

  function saveInlineField(page, row, value) {
    const field = row.getAttribute("data-field");

    if (!field) {
      return Promise.resolve();
    }

    row.classList.add("is-saving");

    return fieldRequest(page, field, value).then((payload) => {
      row.classList.remove("is-saving");

      if (!payload.ok) {
        showFieldMessage(row, payload.message || "Não foi possível salvar.", "error");
        return;
      }

      const read = row.querySelector("[data-pgeservicos-field-open]");
      const control = row.querySelector("[data-pgeservicos-field-control]");
      const hidden = row.querySelector("[data-pgeservicos-field-value]");
      const search = row.querySelector("[data-pgeservicos-field-search]");
      const label = payload.label || "-";

      if (read) {
        if (row.classList.contains("pgeservicos-deadline-card")) {
          setDeadlineReadValue(row, label);
        } else {
          read.textContent = label;
        }
      }

      if (control) {
        control.setAttribute("data-current-label", label);
      }

      if (hidden) {
        hidden.value = payload.value || "";
      }

      if (search) {
        search.value = label === "-" ? "" : label;
        search.setAttribute("data-current-label", label);
      }

      row.classList.remove("is-editing");
      closeFieldDropdowns(page);
      showFieldMessage(row, payload.message || "Salvo.", "success");
    });
  }

  function isVipTextDark(color) {
    const hex = String(color || "").replace("#", "");

    if (!/^[0-9a-f]{3}([0-9a-f]{3})?$/i.test(hex)) {
      return false;
    }

    const full = hex.length === 3
      ? hex.split("").map((char) => char + char).join("")
      : hex;
    const red = parseInt(full.slice(0, 2), 16);
    const green = parseInt(full.slice(2, 4), 16);
    const blue = parseInt(full.slice(4, 6), 16);

    return ((red * 299 + green * 587 + blue * 114) / 1000) > 150;
  }

  function createActorChip(page, actor) {
    const tag = document.createElement("span");
    const kind = document.createElement("span");
    const name = document.createElement("span");

    tag.className = "pgeservicos-actor-tag pgeservicos-actor-tag-existing is-" + actor.kind;
    tag.dataset.linkId = actor.link_id;

    if (actor.tooltip) {
      tag.title = actor.tooltip;
    }

    if (actor.kind === "user" && actor.vip && actor.vip.color) {
      tag.classList.add("is-vip");
      tag.classList.add(isVipTextDark(actor.vip.color) ? "is-vip-text-dark" : "is-vip-text-light");
      tag.style.setProperty("--pgeservicos-vip-color", actor.vip.color);
    }

    kind.className = "pgeservicos-chamado-actor-kind";
    kind.textContent = actor.type_label || (actor.kind === "group" ? "Grupo" : "Usuário");
    name.className = "pgeservicos-actor-name";
    name.textContent = actor.label;

    tag.appendChild(kind);
    tag.appendChild(name);

    if (actor.kind === "user" || actor.kind === "group") {
      const remove = document.createElement("button");
      remove.type = "button";
      remove.textContent = "×";
      remove.dataset.pgeservicosRemoveActor = "";
      remove.dataset.actorKind = actor.kind;
      remove.dataset.linkId = actor.link_id;
      remove.setAttribute("aria-label", "Remover " + actor.label);
      tag.appendChild(remove);
    }

    return tag;
  }

  function appendActorChip(page, picker, actor) {
    if (!picker || !actor || !actor.link_id) {
      return;
    }

    const scroll = picker.querySelector("[data-pgeservicos-actor-chip-scroll]") || picker;
    const existing = scroll.querySelector('[data-link-id="' + actor.link_id + '"]');
    const empty = picker.querySelector(".pgeservicos-actor-empty");

    if (existing) {
      return;
    }

    if (empty) {
      empty.remove();
    }

    scroll.appendChild(createActorChip(page, actor));
  }

  function setupActionLookups(page) {
    const endpoint = page.getAttribute("data-action-target-search-url");

    if (!endpoint || isTicketClosed(page)) {
      return;
    }

    page.querySelectorAll("[data-pgeservicos-action-lookup]").forEach((lookup) => {
      if (lookup.dataset.pgeservicosLookupReady === "1") {
        return;
      }

      const input = lookup.querySelector("[data-pgeservicos-action-lookup-search]");
      const type = lookup.getAttribute("data-target-type");

      if (!input || !type) {
        return;
      }

      lookup.dataset.pgeservicosLookupReady = "1";

      const search = debounce(function () {
        actionTargetSearch(page, type, input.value.trim()).then((payload) => {
          const items = Array.isArray(payload.results) ? payload.results : [];

          if (!payload.ok && payload.message) {
            pgeservicosShowToast("error", payload.message);
          }

          renderActionLookupResults(page, lookup, items, payload.message);
        });
      }, 220);

      input.addEventListener("focus", function () {
        closeActionLookups(page, lookup);
        search();
      });

      input.addEventListener("input", search);

      input.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          closeActionLookups(page);
        }
      });
    });
  }

  function closeOpeningAuthorPickers(page, except) {
    page.querySelectorAll("[data-pgeservicos-opening-author-picker]").forEach((picker) => {
      if (except && picker === except) {
        return;
      }

      const results = picker.querySelector("[data-pgeservicos-opening-author-results]");
      if (results) {
        results.hidden = true;
        results.innerHTML = "";
      }
      picker.classList.remove("is-open");
    });
  }

  function setupOpeningAuthorPicker(page) {
    const endpoint = page.getAttribute("data-opening-author-search-url");
    const csrf = page.getAttribute("data-csrf-token");
    const ticketsId = page.getAttribute("data-tickets-id");

    if (!endpoint || !csrf || !ticketsId || isTicketClosed(page)) {
      return;
    }

    page.querySelectorAll("[data-pgeservicos-opening-author-picker]").forEach((picker) => {
      const input = picker.querySelector("[data-pgeservicos-opening-author-input]");
      const hidden = picker.querySelector("[data-pgeservicos-opening-author-id]");
      const results = picker.querySelector("[data-pgeservicos-opening-author-results]");

      if (!input || !hidden || !results || input.readOnly) {
        return;
      }

      const renderMessage = function (message) {
        results.innerHTML = "";
        const node = document.createElement("span");
        node.className = "pgeservicos-actor-result-message";
        node.textContent = message;
        results.appendChild(node);
        results.hidden = false;
        picker.classList.add("is-open");
      };

      const search = debounce(function () {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set("q", input.value.trim());
        url.searchParams.set("_glpi_csrf_token", csrf);
        url.searchParams.set("tickets_id", ticketsId);
        url.searchParams.set("_", String(Date.now()));

        fetch(url.toString(), {
          credentials: "same-origin",
          cache: "no-store",
          headers: { Accept: "application/json" },
        })
          .then((response) => response.json().catch(() => ({
            ok: false,
            results: [],
            message: "Erro ao buscar usuários."
          })))
          .then((payload) => {
            results.innerHTML = "";
            const items = Array.isArray(payload.results) ? payload.results : [];

            if (!payload.ok && payload.message) {
              renderMessage(payload.message);
              return;
            }

            if (items.length === 0) {
              renderMessage(payload.message || "Nenhum usuário encontrado.");
              return;
            }

            items.forEach((item) => {
              const button = document.createElement("button");
              const label = document.createElement("strong");
              const meta = document.createElement("span");
              const type = document.createElement("small");
              button.type = "button";
              label.textContent = item.label || "Usuário";
              type.textContent = item.subtitle || item.login || "Usuário";
              meta.className = "pgeservicos-actor-result-meta";
              meta.appendChild(type);

              if (item.email) {
                const email = document.createElement("small");
                email.textContent = item.email;
                meta.appendChild(email);
              }

              button.appendChild(label);
              button.appendChild(meta);
              button.addEventListener("click", function () {
                hidden.value = item.items_id || item.id || "";
                input.value = item.label || "";
                input.dataset.selectedLabel = input.value;
                closeOpeningAuthorPickers(page);
                input.focus();
              });
              results.appendChild(button);
            });

            results.hidden = false;
            picker.classList.add("is-open");
          })
          .catch(() => {
            renderMessage("Erro ao buscar usuários.");
          });
      }, 220);

      input.addEventListener("focus", function () {
        closeOpeningAuthorPickers(page, picker);
        search();
      });

      input.addEventListener("input", function () {
        if (input.value !== (input.dataset.selectedLabel || "")) {
          hidden.value = "";
        }
        search();
      });

      input.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          closeOpeningAuthorPickers(page);
        }
      });
    });
  }

  function setupActorPicker(page) {
    const endpoint = page.getAttribute("data-actor-search-url");
    const csrf = page.getAttribute("data-csrf-token");
    const ticketsId = page.getAttribute("data-tickets-id");

    if (!endpoint || !csrf || !ticketsId || isTicketClosed(page)) {
      return;
    }

    page.querySelectorAll("[data-pgeservicos-actor-picker]").forEach((picker) => {
      const input = picker.querySelector("[data-pgeservicos-actor-search]");
      const results = picker.querySelector("[data-pgeservicos-actor-results]");
      const role = picker.getAttribute("data-actor-role");

      if (!input || !results || !role) {
        return;
      }

      const search = debounce(function () {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set("q", input.value.trim());
        url.searchParams.set("_glpi_csrf_token", csrf);
        url.searchParams.set("tickets_id", ticketsId);
        url.searchParams.set("actor_role", role);
        url.searchParams.set("_", String(Date.now()));

        fetch(url.toString(), {
          credentials: "same-origin",
          cache: "no-store",
          headers: { Accept: "application/json" },
        })
          .then((response) => response.json().catch(() => ({
            ok: false,
            results: [],
            message: "Erro ao buscar usuários e grupos."
          })))
          .then((payload) => {
            results.innerHTML = "";
            const items = Array.isArray(payload.results) ? payload.results : [];

            if (!payload.ok && payload.message) {
              const message = document.createElement("span");
              message.className = "pgeservicos-actor-result-message";
              message.textContent = payload.message;
              results.appendChild(message);
              results.hidden = false;
              picker.classList.add("is-open");
              return;
            }

            if (items.length === 0) {
              const message = document.createElement("span");
              message.className = "pgeservicos-actor-result-message";
              message.textContent = payload.message || "Nenhum resultado encontrado.";
              results.appendChild(message);
              results.hidden = false;
              picker.classList.add("is-open");
              return;
            }

            items.forEach((item) => {
              const button = document.createElement("button");
              const label = document.createElement("strong");
              const type = document.createElement("small");
              const meta = document.createElement("span");
              button.type = "button";
              label.textContent = item.label;
              type.textContent = item.type_label || item.type;
              meta.className = "pgeservicos-actor-result-meta";
              meta.appendChild(type);
              if (item.entity) {
                const entity = document.createElement("small");
                entity.textContent = item.entity;
                meta.appendChild(entity);
              }
              button.appendChild(label);
              button.appendChild(meta);
              button.addEventListener("click", function () {
                actorRequest(page, {
                  action: "add_actor",
                  actor_role: role,
                  actor_value: item.value,
                }).then((payload) => {
                  showActorFeedback(page, payload.message || (payload.ok ? "Ator adicionado." : "Não foi possível adicionar."), payload.ok ? "success" : "error");

                  if (payload.ok && payload.actor) {
                    appendActorChip(page, picker, payload.actor);
                    input.value = "";
                    results.hidden = true;
                    results.innerHTML = "";
                    picker.classList.remove("is-open");
                    input.focus();
                  }
                });
              });
              results.appendChild(button);
            });

            results.hidden = false;
            picker.classList.add("is-open");
          })
          .catch(() => {
            results.innerHTML = "";
            const message = document.createElement("span");
            message.className = "pgeservicos-actor-result-message";
            message.textContent = "Erro ao buscar usuários e grupos.";
            results.appendChild(message);
            results.hidden = false;
            picker.classList.add("is-open");
          });
      }, 220);

      input.addEventListener("focus", function () {
        closeActorDropdowns(page, picker);
        search();
      });
      input.addEventListener("input", search);
      input.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          results.hidden = true;
          results.innerHTML = "";
          picker.classList.remove("is-open");
        }
      });
    });
  }

  function closeActorDropdowns(page, except) {
    page.querySelectorAll("[data-pgeservicos-actor-picker]").forEach((picker) => {
      if (except && picker === except) {
        return;
      }

      const results = picker.querySelector("[data-pgeservicos-actor-results]");

      if (results) {
        results.hidden = true;
        results.innerHTML = "";
      }

      picker.classList.remove("is-open");
    });
  }

  function renderFieldResults(page, row, items, message) {
    const results = row.querySelector("[data-pgeservicos-field-results]");

    if (!results) {
      return;
    }

    results.innerHTML = "";

    if (!items.length) {
      const empty = document.createElement("span");
      empty.className = "pgeservicos-field-result-message";
      empty.textContent = message || "Nenhum resultado encontrado.";
      results.appendChild(empty);
      results.hidden = false;
      return;
    }

    items.forEach((item) => {
      const button = document.createElement("button");
      button.type = "button";
      button.textContent = item.label;
      button.addEventListener("click", function () {
        const hidden = row.querySelector("[data-pgeservicos-field-value]");
        const search = row.querySelector("[data-pgeservicos-field-search]");

        if (hidden) {
          hidden.value = item.id;
        }

        if (search) {
          search.value = item.label;
        }

        saveInlineField(page, row, item.id);
      });
      results.appendChild(button);
    });

    results.hidden = false;
  }

  function setupInlineFields(page) {
    const relationSearch = debounce(function (row) {
      const field = row.getAttribute("data-field");
      const input = row.querySelector("[data-pgeservicos-field-search]");

      if (!field || !input) {
        return;
      }

      fieldSearch(page, field, input.value.trim()).then((payload) => {
        const items = Array.isArray(payload.results) ? payload.results : [];
        renderFieldResults(page, row, items, payload.message);
      });
    }, 220);

    page.addEventListener("focusin", function (event) {
      const input = event.target.closest("[data-pgeservicos-field-search]");

      if (!input || isTicketClosed(page)) {
        return;
      }

      const row = input.closest("[data-pgeservicos-field-row]");

      if (row && row.classList.contains("is-editable")) {
        closeFieldDropdowns(page, row);
        input.value = "";
        row.classList.add("is-editing");
        relationSearch(row);
      }
    });

    page.addEventListener("input", function (event) {
      const input = event.target.closest("[data-pgeservicos-field-search]");
      const slaInput = event.target.closest("[data-pgeservicos-sla-search]");

      if (input) {
        const row = input.closest("[data-pgeservicos-field-row]");

        if (!isTicketClosed(page) && row && row.classList.contains("is-editable")) {
          relationSearch(row);
        }

        return;
      }

      if (slaInput) {
        const row = slaInput.closest("[data-pgeservicos-field-row]");

        if (!isTicketClosed(page) && row && row.classList.contains("is-editable")) {
          slaSearch(page, row.getAttribute("data-sla-field") || row.getAttribute("data-field"), slaInput.value.trim()).then((payload) => {
            const items = Array.isArray(payload.results) ? payload.results : [];

            if (!payload.ok && payload.message) {
              pgeservicosShowToast("error", payload.message);
            }

            renderSlaResults(page, row, items, payload.message);
          });
        }
      }
    });

    page.addEventListener("change", function (event) {
      const formatSelect = event.target.closest("[data-pgeservicos-format]");

      if (formatSelect) {
        applyRichFormat(formatSelect);
        return;
      }

      const control = event.target.closest("[data-pgeservicos-field-control]");

      if (!control || isTicketClosed(page)) {
        return;
      }

      const row = control.closest("[data-pgeservicos-field-row]");

      if (!row || !row.classList.contains("is-editable")) {
        return;
      }

      if (control.matches('input[type="datetime-local"]') && isSlaLocked(row)) {
        control.value = control.defaultValue || control.value;
        showFieldMessage(row, "Remova a SLA/OLA antes de alterar manualmente este prazo.", "error");
        return;
      }

      saveInlineField(page, row, control.value);
    });
  }

  function parseCssRgb(value) {
    const color = (value || "").trim();

    if (!color || color === "transparent") {
      return null;
    }

    const rgbMatch = color.match(/^rgba?\(([^)]+)\)$/i);

    if (rgbMatch) {
      const normalized = rgbMatch[1].replace("/", " ").replace(/,/g, " ");
      const parts = normalized.split(/\s+/).filter(Boolean);
      const alpha = parts.length > 3 ? Number.parseFloat(parts[3]) : 1;

      if (!Number.isFinite(alpha) || alpha <= 0) {
        return null;
      }

      return {
        r: Number.parseFloat(parts[0]),
        g: Number.parseFloat(parts[1]),
        b: Number.parseFloat(parts[2]),
      };
    }

    const hexMatch = color.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);

    if (hexMatch) {
      let hex = hexMatch[1];

      if (hex.length === 3) {
        hex = hex.split("").map((char) => char + char).join("");
      }

      return {
        r: Number.parseInt(hex.slice(0, 2), 16),
        g: Number.parseInt(hex.slice(2, 4), 16),
        b: Number.parseInt(hex.slice(4, 6), 16),
      };
    }

    return null;
  }

  function colorLuminance(value) {
    const rgb = parseCssRgb(value);

    if (!rgb) {
      return 0;
    }

    const channels = [rgb.r, rgb.g, rgb.b].map((channel) => {
      const normalized = channel / 255;
      return normalized <= 0.03928
        ? normalized / 12.92
        : Math.pow((normalized + 0.055) / 1.055, 2.4);
    });

    return (channels[0] * 0.2126) + (channels[1] * 0.7152) + (channels[2] * 0.0722);
  }

  function readableTextFor(value) {
    return colorLuminance(value) > 0.55 ? "#1f2937" : "#ffffff";
  }

  function cssVariable(name, fallback, scope) {
    const scoped = scope ? window.getComputedStyle(scope).getPropertyValue(name).trim() : "";
    const value = scoped || window.getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return value || fallback;
  }

  function rgbTriplet(value) {
    const rgb = parseCssRgb(value);
    return rgb ? [Math.round(rgb.r), Math.round(rgb.g), Math.round(rgb.b)].join(", ") : "254, 201, 92";
  }

  function syncThemeColors(page) {
    const primary = cssVariable(
      "--pgeservicos-action-bg",
      cssVariable("--pgeservicos-primary", cssVariable("--tblr-primary", "#fec95c", page), page),
      page
    );
    const primaryColor = cssVariable(
      "--pgeservicos-action-color",
      cssVariable("--pgeservicos-primary-contrast", readableTextFor(primary), page),
      page
    );
    const primaryRgb = cssVariable(
      "--pgeservicos-action-accent-rgb",
      cssVariable("--pgeservicos-primary-rgb", rgbTriplet(primary), page),
      page
    );
    page.style.removeProperty("--pgeservicos-ticket-header-bg");
    page.style.removeProperty("--pgeservicos-ticket-header-color");
    page.style.setProperty("--pgeservicos-action-bg", primary);
    page.style.setProperty("--pgeservicos-action-color", primaryColor);
    page.style.setProperty("--pgeservicos-action-accent", primary);
    page.style.setProperty("--pgeservicos-action-accent-rgb", primaryRgb);
    page.style.setProperty("--pgeservicos-action-accent-fg", primaryColor);
    page.style.setProperty("--pgeservicos-toast-bg", primary);
    page.style.setProperty("--pgeservicos-toast-color", primaryColor);
    page.style.setProperty("--pgeservicos-scroll-btn-bg", primary);
    page.style.setProperty("--pgeservicos-scroll-btn-color", primaryColor);
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

  function isVisibleTopElement(element, page) {
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

  function updateStickyOffset(page) {
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
    let top = 0;

    const candidates = [];
    const customTopbar = Array.prototype.slice.call(document.querySelectorAll(".pgeservicos-portal-topbar"))
      .find(function (element) {
        return isVisibleTopElement(element, page);
      });

    if (customTopbar) {
      top = customTopbar.getBoundingClientRect().bottom;

      if (customTopbar.classList.contains("pgeservicos-portal-topbar--horizontal")) {
        const host = customTopbar.closest(".pgeservicos-portal-topbar-native[data-pgeservicos-topbar-layout='horizontal']");

        if (host && (!page || !page.contains(host))) {
          const hostRect = host.getBoundingClientRect();
          const hostStyle = window.getComputedStyle(host);
          const visibleHost = hostRect.width > 0
            && hostRect.height > 0
            && hostRect.bottom > 0
            && hostRect.top <= 160
            && hostStyle.display !== "none"
            && hostStyle.visibility !== "hidden";

          if (visibleHost) {
            top = Math.max(top, hostRect.bottom);
          }
        }
      }
    }

    selectors.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (element) {
        if (seen.has(element) || !isVisibleTopElement(element, page)) {
          return;
        }

        seen.add(element);
        candidates.push(element);
      });
    });

    if (!top) {
      candidates
        .filter(function (element) {
          return !element.matches(".pgeservicos-portal-topbar");
        })
        .sort(function (first, second) {
          return first.getBoundingClientRect().top - second.getBoundingClientRect().top;
        })
        .forEach(function (element) {
          const rect = element.getBoundingClientRect();
          const style = window.getComputedStyle(element);
          const isFixed = style.position === "fixed";
          const isStackedSticky = style.position === "sticky" && rect.top <= top + 3;

          if (!isFixed && !isStackedSticky) {
            return;
          }

          top = Math.max(top, rect.bottom);
        });
    }

    if (top > 0) {
      page.style.setProperty("--pgeservicos-sticky-top", Math.ceil(top) + "px");
    } else {
      page.style.removeProperty("--pgeservicos-sticky-top");
    }
  }

  function updateFloatingBounds(page) {
    updateStickyOffset(page);
    const rect = page.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth;
    const left = Math.max(12, rect.left);
    const width = Math.min(rect.width, viewportWidth - left - 12);
    const actions = page.querySelector(".pgeservicos-chamado-actions");

    const bottom = (function () {
      if (!actions) {
        return "18px";
      }

      const actionsRect = actions.getBoundingClientRect();
      const overlap = Math.max(0, window.innerHeight - actionsRect.top);
      return Math.ceil(overlap + 12) + "px";
    })();
    const targets = [
      page,
      document.querySelector("[data-pgeservicos-toast-region]"),
      document.querySelector(".pgeservicos-scroll-bottom-btn"),
    ].filter(Boolean);

    targets.forEach(function (target) {
      copyActionVars(page, target);
      target.style.setProperty("--pgeservicos-content-left", left + "px");
      target.style.setProperty("--pgeservicos-content-width", width + "px");
      target.style.setProperty("--pgeservicos-toast-bottom", bottom);
    });
  }

  function updateScrollBottomButton(page, button) {
    if (!button) {
      return;
    }

    const doc = document.documentElement;
    const distanceToBottom = doc.scrollHeight - (window.scrollY + window.innerHeight);
    const hasRoomBelow = distanceToBottom > 180;

    button.hidden = !hasRoomBelow;
    button.classList.toggle("is-visible", hasRoomBelow);
  }

  function setupScrollBottomButton(page) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "pgeservicos-scroll-bottom-btn";
    button.setAttribute("aria-label", "Rolar até o final");
    button.innerHTML = '<i class="ti ti-arrow-down" aria-hidden="true"></i>';
    button.hidden = true;

    button.addEventListener("click", function () {
      const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      window.scrollTo({
        top: document.documentElement.scrollHeight,
        behavior: reduceMotion ? "auto" : "smooth",
      });
    });

    document.body.appendChild(button);
    updateFloatingBounds(page);
    updateScrollBottomButton(page, button);

    return button;
  }

  function getAttachmentLightbox() {
    let lightbox = document.getElementById("pgeservicos-attachment-lightbox");

    if (lightbox) {
      return lightbox;
    }

    lightbox = document.createElement("div");
    lightbox.id = "pgeservicos-attachment-lightbox";
    lightbox.className = "pgeservicos-lightbox";
    lightbox.hidden = true;
    lightbox.setAttribute("role", "dialog");
    lightbox.setAttribute("aria-modal", "true");
    lightbox.setAttribute("aria-label", "Visualização do anexo");
    lightbox.innerHTML = [
      '<div class="pgeservicos-lightbox__frame">',
      '  <button type="button" class="pgeservicos-lightbox__close" data-pgeservicos-lightbox-close aria-label="Fechar">',
      '    <i class="ti ti-x" aria-hidden="true"></i>',
      '  </button>',
      '  <img class="pgeservicos-lightbox__image" alt="">',
      '  <div class="pgeservicos-lightbox__caption"></div>',
      '</div>',
    ].join("");

    lightbox.addEventListener("click", function (event) {
      if (event.target === lightbox || event.target.closest("[data-pgeservicos-lightbox-close]")) {
        closeAttachmentLightbox();
      }
    });

    document.body.appendChild(lightbox);
    return lightbox;
  }

  function openAttachmentLightbox(trigger) {
    const src = trigger.getAttribute("data-pgeservicos-lightbox-src");
    const title = trigger.getAttribute("data-pgeservicos-lightbox-title") || "Imagem anexada";

    if (!src) {
      return;
    }

    const lightbox = getAttachmentLightbox();
    const image = lightbox.querySelector(".pgeservicos-lightbox__image");
    const caption = lightbox.querySelector(".pgeservicos-lightbox__caption");

    if (image) {
      image.src = src;
      image.alt = title;
    }

    if (caption) {
      caption.textContent = title;
    }

    lightbox.hidden = false;
    lightbox.classList.add("is-open");
    lightbox.querySelector("[data-pgeservicos-lightbox-close]")?.focus();
  }

  function closeAttachmentLightbox() {
    const lightbox = document.getElementById("pgeservicos-attachment-lightbox");

    if (!lightbox) {
      return;
    }

    const image = lightbox.querySelector(".pgeservicos-lightbox__image");

    lightbox.classList.remove("is-open");
    lightbox.hidden = true;

    if (image) {
      image.removeAttribute("src");
      image.alt = "";
    }
  }

  function scrollToLastTimelineItem(page) {
    const messages = page.querySelectorAll(".pgeservicos-chamado-message");
    const lastMessage = messages[messages.length - 1];

    if (!lastMessage) {
      return;
    }

    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    lastMessage.scrollIntoView({
      behavior: reduceMotion ? "auto" : "smooth",
      block: "center",
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const page = document.querySelector(".pgeservicos-chamado-page");

    if (!page) {
      return;
    }

    const hero = page.querySelector(".pgeservicos-chamado-hero");
    const compactAt = function () {
      const threshold = hero ? hero.offsetTop + Math.max(hero.offsetHeight - 80, 80) : 140;
      page.classList.toggle("is-compact", window.scrollY > threshold);
    };

    syncThemeColors(page);
    compactAt();
    updateFloatingBounds(page);
    const scrollBottomButton = setupScrollBottomButton(page);
    window.addEventListener("scroll", function () {
      compactAt();
      updateStickyOffset(page);
      updateScrollBottomButton(page, scrollBottomButton);
    }, { passive: true });
    window.addEventListener("resize", function () {
      syncThemeColors(page);
      compactAt();
      updateFloatingBounds(page);
      updateScrollBottomButton(page, scrollBottomButton);
    });
    document.addEventListener("click", function (event) {
      if (!event.target.closest("[data-pgeservicos-actor-picker]")) {
        closeActorDropdowns(page);
      }

      if (!event.target.closest("[data-pgeservicos-opening-author-picker]")) {
        closeOpeningAuthorPickers(page);
      }

      if (!event.target.closest("[data-pgeservicos-field-row]")) {
        closeFieldDropdowns(page);
        closeSlaPickers(page);
      }

      if (!event.target.closest("[data-pgeservicos-sla-picker], [data-pgeservicos-sla-open], [data-pgeservicos-sla-remove]")) {
        closeSlaPickers(page);
      }

      if (!event.target.closest("[data-pgeservicos-action-lookup]")) {
        closeActionLookups(page);
      }

      if (!event.target.closest("[data-pgeservicos-delete-confirm], [data-pgeservicos-delete-trigger]")) {
        page.querySelectorAll("[data-pgeservicos-delete-confirm]").forEach((confirm) => confirm.remove());
      }

      window.setTimeout(function () {
        updateFloatingBounds(page);
        updateScrollBottomButton(page, scrollBottomButton);
      }, 180);
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeActorDropdowns(page);
        closeOpeningAuthorPickers(page);
        closeFieldDropdowns(page);
        closeSlaPickers(page);
        closeActionLookups(page);
        page.querySelectorAll("[data-pgeservicos-delete-confirm]").forEach((confirm) => confirm.remove());
        closeAttachmentLightbox();
      }
    });
    if (window.MutationObserver) {
      const observer = new MutationObserver(function () {
        updateFloatingBounds(page);
      });
      const observerOptions = {
        attributes: true,
        attributeFilter: ["class", "style"],
      };
      observer.observe(document.body, observerOptions);
    }
    window.setTimeout(function () {
      syncThemeColors(page);
      updateFloatingBounds(page);
      updateScrollBottomButton(page, scrollBottomButton);
    }, 250);
    setupActorPicker(page);
    setupOpeningAuthorPicker(page);
    setupActionLookups(page);
    setupInlineFields(page);

    page.addEventListener("click", function (event) {
      const tab = event.target.closest("[data-pgeservicos-action-tab]");
      const close = event.target.closest("[data-pgeservicos-close-panel]");
      const commandButton = event.target.closest("[data-pgeservicos-command]");
      const editButton = event.target.closest("[data-pgeservicos-edit]");
      const cancelEdit = event.target.closest("[data-pgeservicos-cancel-edit]");
      const actorMe = event.target.closest("[data-pgeservicos-actor-me]");
      const removeActor = event.target.closest("[data-pgeservicos-remove-actor]");
      const attachmentPreview = event.target.closest("[data-pgeservicos-lightbox-src]");
      const deleteTrigger = event.target.closest("[data-pgeservicos-delete-trigger]");
      const attachmentDeleteTrigger = event.target.closest("[data-pgeservicos-attachment-delete-trigger]");

      if (attachmentDeleteTrigger) {
        event.preventDefault();
        showAttachmentDeleteConfirm(page, attachmentDeleteTrigger);
        return;
      }

      if (deleteTrigger) {
        event.preventDefault();
        showTimelineDeleteConfirm(page, deleteTrigger);
        return;
      }

      if (attachmentPreview) {
        event.preventDefault();
        openAttachmentLightbox(attachmentPreview);
        return;
      }
      const sideTab = event.target.closest("[data-pgeservicos-side-tab]");
      const inlineEdit = event.target.closest("[data-pgeservicos-inline-edit]");
      const inlineCancel = event.target.closest("[data-pgeservicos-inline-cancel]");
      const fieldOpen = event.target.closest("[data-pgeservicos-field-open]");
      const slaOpen = event.target.closest("[data-pgeservicos-sla-open]");
      const slaRemove = event.target.closest("[data-pgeservicos-sla-remove]");

      if (sideTab) {
        const tabsRoot = sideTab.closest("[data-pgeservicos-side-tabs]");
        const key = sideTab.getAttribute("data-pgeservicos-side-tab");

        if (tabsRoot && key) {
          tabsRoot.querySelectorAll("[data-pgeservicos-side-tab]").forEach((button) => {
            const active = button === sideTab;
            button.classList.toggle("is-active", active);
            button.setAttribute("aria-selected", active ? "true" : "false");
          });

          tabsRoot.querySelectorAll("[data-pgeservicos-side-panel]").forEach((panel) => {
            const active = panel.getAttribute("data-pgeservicos-side-panel") === key;
            panel.hidden = !active;
            panel.classList.toggle("is-active", active);
          });

          closeActorDropdowns(page);
          closeFieldDropdowns(page);
          closeSlaPickers(page);
        }
      }

      if (slaOpen) {
        const row = slaOpen.closest("[data-pgeservicos-field-row]");
        const picker = row?.querySelector("[data-pgeservicos-sla-picker]");
        const input = row?.querySelector("[data-pgeservicos-sla-search]");

        if (!isTicketClosed(page) && row && row.classList.contains("is-editable") && picker && input) {
          event.preventDefault();
          closeSlaPickers(page, picker);
          closeFieldDropdowns(page);
          picker.hidden = false;
          input.focus();
          slaSearch(page, row.getAttribute("data-sla-field") || row.getAttribute("data-field"), input.value.trim()).then((payload) => {
            const items = Array.isArray(payload.results) ? payload.results : [];

            if (!payload.ok && payload.message) {
              pgeservicosShowToast("error", payload.message);
            }

            renderSlaResults(page, row, items, payload.message);
          });
        }
      }

      if (slaRemove) {
        const row = slaRemove.closest("[data-pgeservicos-field-row]");

        if (!isTicketClosed(page) && row && row.classList.contains("is-editable")) {
          event.preventDefault();
          slaRequest(page, {
            action: "remove_sla",
            field: row.getAttribute("data-sla-field") || row.getAttribute("data-field"),
          }).then((payload) => {
            showFieldMessage(row, payload.message || (payload.ok ? "SLA/OLA removida." : "Não foi possível remover."), payload.ok ? "success" : "error");

            if (payload.ok) {
              updateSlaRow(row, payload);
              closeSlaPickers(page);
            }
          });
        }
      }

      if (fieldOpen && !fieldOpen.classList.contains("is-readonly") && !isTicketClosed(page)) {
        const row = fieldOpen.closest("[data-pgeservicos-field-row]");

        if (row && isSlaLocked(row)) {
          event.preventDefault();
          showFieldMessage(row, "Remova a SLA/OLA antes de alterar manualmente este prazo.", "error");
          return;
        }

        if (row && row.classList.contains("is-editable")) {
          closeFieldDropdowns(page, row);
          row.classList.add("is-editing");

          const select = row.querySelector("select[data-pgeservicos-field-control]");
          const date = row.querySelector('input[type="datetime-local"][data-pgeservicos-field-control]');
          const search = row.querySelector("[data-pgeservicos-field-search]");

          if (select) {
            select.focus();

            if (typeof select.showPicker === "function") {
              try {
                select.showPicker();
              } catch (error) {
                // Browser may block programmatic picker opening; focus still enables keyboard selection.
              }
            }
          } else if (date) {
            date.focus();

            if (typeof date.showPicker === "function") {
              try {
                date.showPicker();
              } catch (error) {
                // Keep the field focused when the native picker cannot be opened programmatically.
              }
            }
          } else if (search) {
            search.value = "";
            search.focus();
          }
        }
      }

      if (inlineEdit) {
        inlineEdit.closest(".pgeservicos-side-inline-form")?.classList.add("is-editing");
      }

      if (inlineCancel) {
        const form = inlineCancel.closest(".pgeservicos-side-inline-form");

        if (form) {
          form.reset();
          form.classList.remove("is-editing");
        }
      }

      if (tab) {
        const key = tab.getAttribute("data-pgeservicos-action-tab");
        const panel = page.querySelector('[data-pgeservicos-action-panel="' + key + '"]');
        const shouldOpen = panel ? panel.hidden : false;

        closeActionPanels(page);

        if (panel && shouldOpen) {
          panel.hidden = false;
          tab.classList.add("is-active");
          scrollToLastTimelineItem(page);
          panel.querySelector(".pgeservicos-rich-editor, textarea, input, select")?.focus();
        }
      }

      if (close) {
        closeActionPanels(page);
      }

      if (commandButton) {
        runCommand(commandButton);
      }

      if (actorMe && !isTicketClosed(page)) {
        const role = actorMe.getAttribute("data-actor-role");
        const picker = page.querySelector('[data-pgeservicos-actor-picker][data-actor-role="' + role + '"]');

        actorRequest(page, {
          action: "add_self",
          actor_role: role,
        }).then((payload) => {
          showActorFeedback(page, payload.message || (payload.ok ? "Ator adicionado." : "Não foi possível adicionar."), payload.ok ? "success" : "error");

          if (payload.ok && payload.actor) {
            appendActorChip(page, picker, payload.actor);
          }
        });
      }

      if (removeActor && !isTicketClosed(page)) {
        const chip = removeActor.closest(".pgeservicos-actor-tag");

        actorRequest(page, {
          action: "remove_actor",
          actor_kind: removeActor.getAttribute("data-actor-kind"),
          link_id: removeActor.getAttribute("data-link-id"),
        }).then((payload) => {
          showActorFeedback(page, payload.message || (payload.ok ? "Ator removido." : "Não foi possível remover."), payload.ok ? "success" : "error");

          if (payload.ok && chip) {
            const picker = chip.closest("[data-pgeservicos-actor-picker]");
            chip.remove();

            if (picker && !picker.querySelector(".pgeservicos-actor-tag-existing")) {
              const empty = document.createElement("span");
              const scroll = picker.querySelector("[data-pgeservicos-actor-chip-scroll]") || picker;
              empty.className = "pgeservicos-actor-empty";
              empty.textContent = "Nenhum registro.";
              scroll.appendChild(empty);
            }
          }
        });
      }

      if (editButton) {
        const id = editButton.getAttribute("data-pgeservicos-edit");
        const message = editButton.closest(".pgeservicos-chamado-message-bubble");
        const form = page.querySelector('[data-pgeservicos-edit-form="' + id + '"]');

        if (message && form) {
          message.classList.add("is-editing");
          form.querySelector(".pgeservicos-rich-editor")?.focus();
        }
      }

      if (cancelEdit) {
        cancelEdit.closest(".pgeservicos-chamado-message-bubble")?.classList.remove("is-editing");
      }
    });

    page.addEventListener("submit", function (event) {
      const form = event.target;

      if (!(form instanceof HTMLFormElement)) {
        return;
      }

      syncEditor(form);

      if (!form.querySelector('[name="pgeservicos_action"]')) {
        return;
      }

      event.preventDefault();

      if (form.classList.contains("is-submitting")) {
        return;
      }

      const uploadValidation = validateFormUploads(page, form);

      if (!uploadValidation.ok) {
        pgeservicosShowToast("error", uploadValidation.message || "Arquivo inválido ou acima do tamanho permitido.");
        return;
      }

      const request = actionFormRequest(form, event.submitter);
      setFormBusy(form, true);

      request.then((payload) => {
        const ok = Boolean(payload.ok || payload.success);
        pgeservicosShowToast(ok ? "success" : "error", payload.message || (ok ? "Operação concluída." : "Não foi possível concluir a ação."));

        if (ok && payload.redirect) {
          window.setTimeout(function () {
            window.location.assign(payload.redirect);
          }, 420);
          return;
        }

        if (ok && payload.reload !== false) {
          window.setTimeout(function () {
            window.location.reload();
          }, 520);
          return;
        }

        setFormBusy(form, false);
      });
    });

  });
})();
