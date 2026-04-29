document.addEventListener("DOMContentLoaded", function () {
  const input = document.getElementById("pgeservicos-search-input");
  const clearButton = document.getElementById("pgeservicos-search-clear");
  const resultsBox = document.getElementById("pgeservicos-search-results");
  const dataNode = document.getElementById("pgeservicos-search-data");

  if (!input || !clearButton || !resultsBox || !dataNode) {
    return;
  }

  let searchResults = [];

  try {
    searchResults = JSON.parse(dataNode.getAttribute("data-results") || "[]");
  } catch (error) {
    searchResults = [];
  }

  function normalize(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();
  }

  function clearResults() {
    resultsBox.hidden = true;
    resultsBox.innerHTML = "";
    input.setAttribute("aria-expanded", "false");
  }

  function updateClearButton() {
    clearButton.hidden = input.value.length === 0;
  }

  function buildResult(result) {
    const link = document.createElement("a");
    link.className = "pgeservicos-search-result";
    link.href = result.url || "#";

    const header = document.createElement("span");
    header.className = "pgeservicos-search-result-header";

    const title = document.createElement("strong");
    title.textContent = result.title || "Resultado sem título";

    const type = document.createElement("span");
    type.textContent = result.type || "Portal";

    header.appendChild(title);
    header.appendChild(type);
    link.appendChild(header);

    if (result.description) {
      const description = document.createElement("span");
      description.className = "pgeservicos-search-result-description";
      description.textContent = result.description;
      link.appendChild(description);
    }

    return link;
  }

  function search(query) {
    const normalizedQuery = normalize(query);

    if (normalizedQuery.length < 2) {
      clearResults();
      return;
    }

    const matches = searchResults
      .map(function (result) {
        const title = normalize(result.title);
        const description = normalize(result.description);
        const keywords = normalize(result.keywords);
        const haystack = [title, description, keywords].join(" ");

        if (!haystack.includes(normalizedQuery)) {
          return null;
        }

        let score = 1;

        if (title.startsWith(normalizedQuery)) {
          score += 4;
        } else if (title.includes(normalizedQuery)) {
          score += 2;
        }

        if (description.includes(normalizedQuery)) {
          score += 1;
        }

        return {
          result: result,
          score: score,
        };
      })
      .filter(Boolean)
      .sort(function (a, b) {
        return b.score - a.score;
      })
      .slice(0, 8);

    resultsBox.innerHTML = "";

    if (!matches.length) {
      const empty = document.createElement("div");
      empty.className = "pgeservicos-search-empty";
      empty.textContent = "Nenhum resultado encontrado.";
      resultsBox.appendChild(empty);
    } else {
      matches.forEach(function (match) {
        resultsBox.appendChild(buildResult(match.result));
      });
    }

    resultsBox.hidden = false;
    input.setAttribute("aria-expanded", "true");
  }

  input.addEventListener("input", function () {
    updateClearButton();
    search(input.value);
  });

  input.addEventListener("keydown", function (event) {
    if (event.key !== "Escape") {
      return;
    }

    input.value = "";
    updateClearButton();
    clearResults();
  });

  clearButton.addEventListener("click", function () {
    input.value = "";
    updateClearButton();
    clearResults();
    input.focus();
  });

  document.addEventListener("click", function (event) {
    if (event.target.closest(".pgeservicos-search")) {
      return;
    }

    clearResults();
  });

  updateClearButton();
});
