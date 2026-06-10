(function () {
  "use strict";

  function updateNoteFields(row) {
    var type = row.querySelector("[data-pgeservicos-question-type]");
    var noteFields = Array.prototype.slice.call(row.querySelectorAll("[data-pgeservicos-note-field]"));
    var isNote = type && type.value === "note";

    noteFields.forEach(function (field) {
      field.hidden = !isNote;
    });
  }

  function bindQuestionRow(row) {
    var type = row.querySelector("[data-pgeservicos-question-type]");
    var remove = row.querySelector("[data-pgeservicos-remove-question]");

    if (type && type.dataset.pgeservicosBound !== "1") {
      type.dataset.pgeservicosBound = "1";
      type.addEventListener("change", function () {
        updateNoteFields(row);
      });
    }

    if (remove && remove.dataset.pgeservicosBound !== "1") {
      remove.dataset.pgeservicosBound = "1";
      remove.addEventListener("click", function () {
        var list = row.closest("[data-pgeservicos-question-list]");
        if (list && list.querySelectorAll("[data-pgeservicos-question-row]").length > 1) {
          row.remove();
        }
      });
    }

    updateNoteFields(row);
  }

  function clearQuestionRow(row) {
    Array.prototype.slice.call(row.querySelectorAll("textarea, input")).forEach(function (input) {
      if (input.type === "number") {
        input.value = input.name.indexOf("question_number") !== -1 ? "5" : "1";
        return;
      }

      input.value = "";
    });

    var type = row.querySelector("[data-pgeservicos-question-type]");
    if (type) {
      type.value = "note";
      delete type.dataset.pgeservicosBound;
    }

    var remove = row.querySelector("[data-pgeservicos-remove-question]");
    if (remove) {
      delete remove.dataset.pgeservicosBound;
    }
  }

  function initSurveyForm(form) {
    var list = form.querySelector("[data-pgeservicos-question-list]");
    var add = form.querySelector("[data-pgeservicos-add-question]");

    if (!list || !add) {
      return;
    }

    Array.prototype.slice.call(list.querySelectorAll("[data-pgeservicos-question-row]")).forEach(bindQuestionRow);

    add.addEventListener("click", function () {
      var first = list.querySelector("[data-pgeservicos-question-row]");
      if (!first) {
        return;
      }

      var clone = first.cloneNode(true);
      clearQuestionRow(clone);
      list.appendChild(clone);
      bindQuestionRow(clone);
      var textarea = clone.querySelector("textarea[name='question_name[]']");
      if (textarea) {
        textarea.focus();
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    Array.prototype.slice.call(document.querySelectorAll("[data-pgeservicos-survey-form]")).forEach(initSurveyForm);
  });
}());
