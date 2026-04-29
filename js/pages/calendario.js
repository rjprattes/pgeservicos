document.addEventListener("DOMContentLoaded", function () {
  const reservationCards = document.querySelectorAll(
    ".pgegestor-reservation-card",
  );
  const globalTooltip = document.getElementById("pgegestor-global-tooltip");

  if (!globalTooltip) {
    return;
  }

  function positionTooltip(event) {
    const margin = 12;
    const offsetX = 18;
    const offsetY = 18;

    const tooltipWidth = globalTooltip.offsetWidth;
    const tooltipHeight = globalTooltip.offsetHeight;

    let left = event.clientX + offsetX;
    let top = event.clientY + offsetY;

    if (left + tooltipWidth + margin > window.innerWidth) {
      left = event.clientX - tooltipWidth - offsetX;
    }

    if (top + tooltipHeight + margin > window.innerHeight) {
      top = event.clientY - tooltipHeight - offsetY;
    }

    if (left < margin) {
      left = margin;
    }

    if (top < margin) {
      top = margin;
    }

    globalTooltip.style.left = left + "px";
    globalTooltip.style.top = top + "px";
  }

  function showTooltip(event, card) {
    const content = card.querySelector(".pgegestor-tooltip-content");

    if (!content) {
      return;
    }

    globalTooltip.innerHTML = content.innerHTML;
    globalTooltip.classList.add("is-visible");
    positionTooltip(event);
  }

  function hideTooltip() {
    globalTooltip.classList.remove("is-visible");
    globalTooltip.innerHTML = "";
    globalTooltip.style.left = "0px";
    globalTooltip.style.top = "0px";
  }

  reservationCards.forEach(function (card) {
    card.addEventListener("mouseenter", function (event) {
      showTooltip(event, card);
    });

    card.addEventListener("mousemove", function (event) {
      if (globalTooltip.classList.contains("is-visible")) {
        positionTooltip(event);
      }
    });

    card.addEventListener("mouseleave", function () {
      hideTooltip();
    });
  });

  window.addEventListener(
    "scroll",
    function () {
      hideTooltip();
    },
    true,
  );

  window.addEventListener("resize", function () {
    hideTooltip();
  });
});
