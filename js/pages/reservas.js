document.addEventListener("DOMContentLoaded", function () {
  const rows = document.querySelectorAll(".pgegestor-clickable-row");

  rows.forEach(function (row) {
    row.addEventListener("click", function (event) {
      if (event.target.closest("a, button, input, select, textarea, label")) {
        return;
      }

      const href = row.getAttribute("data-href");

      if (href) {
        window.location.href = href;
      }
    });

    row.addEventListener("keydown", function (event) {
      if (event.key !== "Enter" && event.key !== " ") {
        return;
      }

      if (event.target.closest("a, button, input, select, textarea, label")) {
        return;
      }

      const href = row.getAttribute("data-href");

      if (href) {
        event.preventDefault();
        window.location.href = href;
      }
    });
  });
});
