(function () {
    "use strict";

    var LINK_TARGETS = [
        {
            label: "meus chamados",
            path: "/plugins/pgeservicos/front/meus_chamados.php",
            nativePatterns: [
                /\/front\/ticket\.php(?:$|[?#])/,
                /\/front\/ticket\.form\.php(?:$|[?#])/,
                /\/front\/helpdesk(?:\.public)?\.php(?:$|[?#])/,
                /\/front\/tracking\.injector\.php(?:$|[?#])/,
                /\/plugins\/formcreator\/front\/issue\.php(?:$|[?#])/,
                /\/plugins\/formcreator\/front\/wizard\.php(?:$|[?#])/
            ]
        },
        {
            label: "aguardando aprovacao",
            path: "/plugins/pgeservicos/front/meus_chamados.php?awaiting_approval=1&status=5",
            nativePatterns: [
                /\/front\/ticket\.php(?:$|[?#])/,
                /\/front\/ticket\.form\.php(?:$|[?#])/,
                /\/front\/helpdesk(?:\.public)?\.php(?:$|[?#])/
            ]
        },
        {
            labels: ["pesquisa de satisfacao", "pesquisas de satisfacao"],
            path: "/plugins/pgeservicos/front/meus_chamados.php?satisfaction=1",
            nativePatterns: [
                /\/front\/ticket\.php(?:$|[?#]).*(?:status=survey|criteria)/,
                /\/front\/ticket\.php(?:$|[?#])/,
                /\/front\/helpdesk(?:\.public)?\.php(?:$|[?#])/,
                /\/marketplace\/satisfaction\/front\/survey(?:\.form)?\.php(?:$|[?#])/
            ]
        }
    ];

    function normalizeText(value) {
        return String(value || "")
            .replace(/\s+/g, " ")
            .trim()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase();
    }

    function getRootDoc() {
        var scripts = Array.prototype.slice.call(
            document.querySelectorAll("script[src*='/plugins/pgeservicos/']")
        );
        var marker = "/plugins/pgeservicos/";

        for (var index = 0; index < scripts.length; index++) {
            var source = scripts[index].getAttribute("src") || "";
            var markerIndex = source.indexOf(marker);

            if (markerIndex !== -1) {
                return source.slice(0, markerIndex);
            }
        }

        var path = window.location.pathname || "";
        var pluginsIndex = path.indexOf("/plugins/");

        if (pluginsIndex !== -1) {
            return path.slice(0, pluginsIndex);
        }

        return "";
    }

    function findVerticalSidebar(element) {
        if (!element || !element.closest) {
            return null;
        }

        return element.closest(
            "aside.navbar-vertical, aside.sidebar, .navbar-vertical.sidebar, .sidebar.navbar-vertical, .layout-sidebar"
        );
    }

    function isInsideHorizontalNavigation(element) {
        if (!element || !element.closest) {
            return false;
        }

        return Boolean(
            element.closest(".navbar-horizontal, .topbar, header.navbar, header.topbar")
        );
    }

    function collectAccessibleLabels(link) {
        var candidates = [
            link.getAttribute("aria-label"),
            link.getAttribute("title"),
            link.getAttribute("data-bs-original-title"),
            link.getAttribute("data-original-title"),
            link.textContent
        ];
        var labelledBy = link.getAttribute("aria-labelledby");

        if (labelledBy) {
            labelledBy.split(/\s+/).forEach(function (id) {
                var element = document.getElementById(id);

                if (element) {
                    candidates.push(element.textContent);
                }
            });
        }

        Array.prototype.slice
            .call(link.querySelectorAll("[aria-label], [title], [data-bs-original-title], [data-original-title]"))
            .forEach(function (element) {
                candidates.push(element.getAttribute("aria-label"));
                candidates.push(element.getAttribute("title"));
                candidates.push(element.getAttribute("data-bs-original-title"));
                candidates.push(element.getAttribute("data-original-title"));
                candidates.push(element.textContent);
            });

        return candidates
            .map(normalizeText)
            .filter(Boolean);
    }

    function hrefLooksLikeNativeTarget(link, target) {
        var href = link.getAttribute("href") || "";

        if (!href || href.indexOf(target.path) !== -1) {
            return true;
        }

        try {
            var url = new URL(href, window.location.origin);
            var route = url.pathname + url.search;

            return target.nativePatterns.some(function (pattern) {
                return pattern.test(route);
            });
        } catch (error) {
            return target.nativePatterns.some(function (pattern) {
                return pattern.test(href);
            });
        }
    }

    function findTargetForSidebarLink(link) {
        if (!findVerticalSidebar(link) || isInsideHorizontalNavigation(link)) {
            return null;
        }

        var labels = collectAccessibleLabels(link);

        for (var index = 0; index < LINK_TARGETS.length; index++) {
            var target = LINK_TARGETS[index];
            var targetLabels = target.labels || [target.label];
            var hasExactLabel = labels.some(function (label) {
                return targetLabels.indexOf(label) !== -1;
            });

            if (hasExactLabel && hrefLooksLikeNativeTarget(link, target)) {
                return target;
            }
        }

        return null;
    }

    function rewriteSidebarLinks() {
        var rootDoc = getRootDoc();

        Array.prototype.slice.call(document.querySelectorAll("a[href]")).forEach(function (link) {
            var target = findTargetForSidebarLink(link);

            if (!target) {
                return;
            }

            var targetHref = rootDoc + target.path;

            if (!link.dataset.pgeservicosNativeHref) {
                link.dataset.pgeservicosNativeHref = link.getAttribute("href") || "";
            }

            if (link.getAttribute("href") !== targetHref) {
                link.setAttribute("href", targetHref);
            }
        });
    }

    function startObserver() {
        var timeoutId = null;
        var observer = new MutationObserver(function () {
            window.clearTimeout(timeoutId);
            timeoutId = window.setTimeout(rewriteSidebarLinks, 80);
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ["href", "title", "aria-label", "data-bs-original-title", "data-original-title", "class"]
        });
    }

    function init() {
        rewriteSidebarLinks();
        startObserver();
        window.setTimeout(rewriteSidebarLinks, 250);
        window.setTimeout(rewriteSidebarLinks, 900);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
