document.addEventListener("DOMContentLoaded", function () {
  function setCapacityValue(element, value) {
    if ("value" in element) {
      element.value = value;
      return;
    }

    element.textContent = value;
  }

  function updateCapacityInfo() {
    const select = document.getElementById("reservationitems_id");
    const text = document.getElementById("pgegestor-capacity-text");

    if (!text || !select) {
      return;
    }

    const selected = select.options[select.selectedIndex];

    if (!selected || !selected.value) {
      setCapacityValue(text, "0 participantes");
      return;
    }

    const capacity = parseInt(
      selected.getAttribute("data-capacity") || "0",
      10,
    );

    if (capacity > 0) {
      setCapacityValue(text, "Até " + capacity + " participantes");
      return;
    }

    setCapacityValue(text, "0 participantes");
  }

  const capacitySelect = document.getElementById("reservationitems_id");

  if (capacitySelect) {
    capacitySelect.addEventListener("change", updateCapacityInfo);
    updateCapacityInfo();
  }

  const deleteButtons = document.querySelectorAll(
    '[data-pgegestor-confirm-delete="1"]',
  );
  let pendingDeleteButton = null;
  let lastFocusedElement = null;

  if (!deleteButtons.length) {
    return;
  }

  const overlay = document.createElement("div");
  overlay.className = "pgegestor-confirm-overlay";
  overlay.setAttribute("aria-hidden", "true");
  overlay.hidden = true;
  let closeTimer = null;

  overlay.innerHTML = [
    '<div class="pgegestor-confirm-card" role="dialog" aria-modal="true" aria-labelledby="pgegestor-confirm-title" aria-describedby="pgegestor-confirm-message">',
    '    <button type="button" class="pgegestor-confirm-close" aria-label="Fechar confirmação">',
    '        <i class="ti ti-x"></i>',
    "    </button>",
    '    <div class="pgegestor-confirm-icon" aria-hidden="true">',
    '        <i class="ti ti-alert-triangle"></i>',
    "    </div>",
    '    <h2 id="pgegestor-confirm-title">Excluir reserva?</h2>',
    '    <p id="pgegestor-confirm-message">Tem certeza de que deseja excluir esta reserva? Os serviços gerados a partir do cadastro da reserva serão cancelados.</p>',
    '    <div class="pgegestor-confirm-actions">',
    '        <button type="button" class="btn btn-secondary pgegestor-confirm-cancel">Cancelar</button>',
    '        <button type="button" class="btn btn-danger pgegestor-confirm-delete">',
    '            <i class="ti ti-trash"></i> Excluir reserva',
    "        </button>",
    "    </div>",
    "</div>",
  ].join("");

  document.body.appendChild(overlay);

  const card = overlay.querySelector(".pgegestor-confirm-card");
  const closeButton = overlay.querySelector(".pgegestor-confirm-close");
  const cancelButton = overlay.querySelector(".pgegestor-confirm-cancel");
  const confirmButton = overlay.querySelector(".pgegestor-confirm-delete");

  function closeDialog(restoreFocus) {
    overlay.classList.remove("is-visible");
    overlay.setAttribute("aria-hidden", "true");
    document.body.classList.remove("pgegestor-confirm-open");
    pendingDeleteButton = null;

    closeTimer = window.setTimeout(function () {
      if (!overlay.classList.contains("is-visible")) {
        overlay.hidden = true;
      }
    }, 240);

    if (restoreFocus !== false && lastFocusedElement) {
      lastFocusedElement.focus();
    }

    lastFocusedElement = null;
  }

  function openDialog(button) {
    pendingDeleteButton = button;
    lastFocusedElement = document.activeElement;
    window.clearTimeout(closeTimer);
    overlay.hidden = false;
    overlay.setAttribute("aria-hidden", "false");
    document.body.classList.add("pgegestor-confirm-open");

    window.requestAnimationFrame(function () {
      overlay.classList.add("is-visible");
      cancelButton.focus();
    });
  }

  function submitDelete() {
    const deleteButton = pendingDeleteButton;

    if (!deleteButton || !deleteButton.form) {
      closeDialog();
      return;
    }

    closeDialog(false);

    if (typeof deleteButton.form.requestSubmit === "function") {
      deleteButton.form.requestSubmit(deleteButton);
      return;
    }

    if (deleteButton.name) {
      const fallbackInput = document.createElement("input");
      fallbackInput.type = "hidden";
      fallbackInput.name = deleteButton.name;
      fallbackInput.value = deleteButton.value;
      deleteButton.form.appendChild(fallbackInput);
    }

    deleteButton.form.submit();
  }

  deleteButtons.forEach(function (button) {
    button.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();
      openDialog(button);
    });
  });

  overlay.addEventListener("click", function (event) {
    if (event.target === overlay) {
      closeDialog();
    }
  });

  card.addEventListener("click", function (event) {
    event.stopPropagation();
  });

  closeButton.addEventListener("click", function () {
    closeDialog();
  });

  cancelButton.addEventListener("click", function () {
    closeDialog();
  });

  confirmButton.addEventListener("click", submitDelete);

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && overlay.classList.contains("is-visible")) {
      closeDialog();
    }
  });
});
