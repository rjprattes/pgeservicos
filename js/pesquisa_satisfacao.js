(function () {
  "use strict";

  const page = document.querySelector(".pgeservicos-satisfaction-page");

  if (!page) {
    return;
  }

  const form = page.querySelector(".pgeservicos-satisfaction-form");

  if (!form) {
    return;
  }

  const submitButton = form.querySelector("button[type='submit']");
  const originalSubmitHtml = submitButton ? submitButton.innerHTML : "";

  form.addEventListener("submit", () => {
    if (!submitButton || submitButton.disabled) {
      return;
    }

    submitButton.disabled = true;
    submitButton.innerHTML = "<i class='ti ti-loader-2' aria-hidden='true'></i>Enviando...";

    window.setTimeout(() => {
      submitButton.disabled = false;
      submitButton.innerHTML = originalSubmitHtml;
    }, 9000);
  });
})();
