(function () {
  "use strict";

  function syncEditor(form) {
    const editor = form.querySelector(".pgeservicos-rich-editor");
    const input = form.querySelector(".pgeservicos-rich-input");

    if (editor && input) {
      input.value = editor.innerHTML.trim();
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
    const editor = button.closest("label, form")?.querySelector(".pgeservicos-rich-editor");

    if (!command || !editor) {
      return;
    }

    editor.focus();

    if (command === "createLink") {
      const url = window.prompt("Informe o link:");

      if (url) {
        document.execCommand(command, false, url);
      }

      return;
    }

    document.execCommand(command, false, null);
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

  function showActorFeedback(page, message, type) {
    let feedback = page.querySelector("[data-pgeservicos-actor-feedback]");

    if (!feedback) {
      feedback = document.createElement("div");
      feedback.className = "pgeservicos-actor-feedback";
      feedback.setAttribute("data-pgeservicos-actor-feedback", "");
      page.appendChild(feedback);
    }

    feedback.textContent = message;
    feedback.classList.toggle("is-error", type === "error");
    feedback.hidden = false;

    window.clearTimeout(feedback._pgeTimer);
    feedback._pgeTimer = window.setTimeout(function () {
      feedback.hidden = true;
    }, 3200);
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
      .then((response) => response.json().catch(() => ({ ok: false, message: "Resposta inválida do servidor." })))
      .catch(() => ({ ok: false, message: "Não foi possível comunicar com o servidor." }));
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
      tag.dataset.tooltip = actor.tooltip;
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

    const existing = picker.querySelector('[data-link-id="' + actor.link_id + '"]');
    const input = picker.querySelector("[data-pgeservicos-actor-search]");
    const empty = picker.querySelector(".pgeservicos-actor-empty");

    if (existing) {
      return;
    }

    if (empty) {
      empty.remove();
    }

    picker.insertBefore(createActorChip(page, actor), input || null);
  }

  function setupActorPicker(page) {
    const endpoint = page.getAttribute("data-actor-search-url");
    const csrf = page.getAttribute("data-csrf-token");
    const ticketsId = page.getAttribute("data-tickets-id");

    if (!endpoint || !csrf || !ticketsId) {
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

        fetch(url.toString(), {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        })
          .then((response) => (response.ok ? response.json() : { results: [] }))
          .then((payload) => {
            results.innerHTML = "";
            const items = Array.isArray(payload.results) ? payload.results : [];

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

            results.hidden = items.length === 0;
            picker.classList.toggle("is-open", items.length > 0);
          })
          .catch(() => {
            results.hidden = true;
            picker.classList.remove("is-open");
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

  function updateFloatingBounds(page) {
    const rect = page.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth;
    const left = Math.max(12, rect.left);
    const width = Math.min(rect.width, viewportWidth - left - 12);

    page.style.setProperty("--pgeservicos-content-left", left + "px");
    page.style.setProperty("--pgeservicos-content-width", width + "px");
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

    compactAt();
    updateFloatingBounds(page);
    window.addEventListener("scroll", compactAt, { passive: true });
    window.addEventListener("resize", function () {
      compactAt();
      updateFloatingBounds(page);
    });
    document.addEventListener("click", function (event) {
      if (!event.target.closest("[data-pgeservicos-actor-picker]")) {
        closeActorDropdowns(page);
      }

      window.setTimeout(function () {
        updateFloatingBounds(page);
      }, 180);
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeActorDropdowns(page);
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
      observer.observe(document.documentElement, observerOptions);
    }
    window.setTimeout(function () {
      updateFloatingBounds(page);
    }, 250);
    setupActorPicker(page);

    page.addEventListener("click", function (event) {
      const tab = event.target.closest("[data-pgeservicos-action-tab]");
      const close = event.target.closest("[data-pgeservicos-close-panel]");
      const commandButton = event.target.closest("[data-pgeservicos-command]");
      const editButton = event.target.closest("[data-pgeservicos-edit]");
      const cancelEdit = event.target.closest("[data-pgeservicos-cancel-edit]");
      const actorMe = event.target.closest("[data-pgeservicos-actor-me]");
      const removeActor = event.target.closest("[data-pgeservicos-remove-actor]");

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

      if (actorMe) {
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

      if (removeActor) {
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
              const input = picker.querySelector("[data-pgeservicos-actor-search]");
              empty.className = "pgeservicos-actor-empty";
              empty.textContent = "Nenhum registro.";
              picker.insertBefore(empty, input || null);
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

      if (form instanceof HTMLFormElement) {
        syncEditor(form);
      }
    });
  });
})();
