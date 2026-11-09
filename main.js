/* ==========================================================================
   HLB PORTFOLIO — main.js
   Handles: session-aware nav, CSRF tokens for the static pages,
            password strength, confirm-match, show/hide password
   ========================================================================== */
(function () {
    "use strict";

    /* ---------- where the PHP folder is, relative to this script ----------
       Works whether the page lives at the site root (index.html) or one level
       down (php/login.php), because it is derived from main.js's own URL
       rather than from the page's. */
    var SCRIPT = document.currentScript
        || (function () {
            var all = document.getElementsByTagName("script");
            return all[all.length - 1];
        })();
    var ROOT = SCRIPT ? SCRIPT.src.replace(/\/[^/]*$/, "/") : "./";
    var PHP = ROOT + "php/";

    /* ---------- helpers ---------- */
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    /* ---------- session-aware navigation ---------- */
    function hydrateNav() {
        var loginLink = document.getElementById("navLogin");
        if (!loginLink) return;

        fetch(PHP + "session-status.php", {
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.authenticated) return;

                var name = escapeHtml(data.display_name || data.username || "Account");
                var initial = escapeHtml((data.display_name || data.username || "?").charAt(0).toUpperCase());

                var group = document.createElement("div");
                group.className = "NavAuth-Group";
                group.innerHTML =
                    '<a href="' + PHP + 'profile.php" class="NavButton NavAccount" title="My account">' +
                        '<span class="NavAvatar" aria-hidden="true">' + initial + "</span>" +
                        "<p>" + name + "</p>" +
                    "</a>" +
                    '<a href="' + PHP + 'logout.php" class="NavButton NavLogout" title="Log out">' +
                        "<p>Log Out</p>" +
                    "</a>";

                loginLink.replaceWith(group);
            })
            .catch(function () { /* silent — stay on the Log In link */ });
    }

    /* ---------- CSRF for the forms on the static .html pages ----------
       The PHP pages print their own token. These forms can't, so fetch one.
       Submission still works without it (see the note in contact.php). */
    function injectCsrfTokens() {
        var forms = Array.prototype.filter.call(
            document.querySelectorAll("form[method]"),
            function (form) {
                return /post/i.test(form.getAttribute("method") || "")
                    && /(^|\/)php\//.test(form.getAttribute("action") || "")
                    && !form.querySelector('input[name="csrf_token"]');
            }
        );
        if (!forms.length) return;

        fetch(PHP + "csrf-token.php", {
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.token) return;
                forms.forEach(function (form) {
                    var input = document.createElement("input");
                    input.type = "hidden";
                    input.name = "csrf_token";
                    input.value = data.token;
                    form.appendChild(input);
                });
            })
            .catch(function () { /* silent — the server tolerates a missing token */ });
    }

    /* ---------- password strength ----------
       Scores the same things the server checks, so the meter can't call a
       password "Strong" and then have PHP reject it. */
    function initPasswordMeter() {
        var pw = document.getElementById("password");
        var meter = document.getElementById("pwMeter");
        var label = document.getElementById("pwLabel");
        if (!pw || !meter || !label) return;

        var bar = meter.firstElementChild;
        var base = label.textContent;

        pw.addEventListener("input", function () {
            var v = pw.value;

            if (!v) {
                meter.dataset.score = "0";
                bar.style.width = "0";
                label.textContent = base;
                return;
            }

            var score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v) || v.length >= 14) score++;

            meter.dataset.score = String(score);
            bar.style.width = (score / 4) * 100 + "%";

            // Name what's still missing instead of only grading it.
            var missing = [];
            if (v.length < 8) missing.push("8 characters");
            if (!/[A-Z]/.test(v) || !/[a-z]/.test(v)) missing.push("upper and lower case");
            if (!/\d/.test(v)) missing.push("a number");

            label.textContent = missing.length
                ? "Still needs " + missing.join(", ")
                : ["", "Weak", "Fair", "Good", "Strong"][score] + " — meets the requirements";
        });
    }

    /* ---------- confirm-password match ---------- */
    function initRegisterForm() {
        var form = document.getElementById("registerForm");
        if (!form) return;

        var pw = form.querySelector("#password");
        var confirm = form.querySelector("#confirm");
        var hint = document.getElementById("confirmHint");
        if (!pw || !confirm || !hint) return;

        function checkMatch() {
            if (!confirm.value) {
                hint.textContent = "";
                hint.removeAttribute("data-state");
                confirm.setCustomValidity("");
                return;
            }
            if (confirm.value !== pw.value) {
                hint.textContent = "Passwords do not match.";
                hint.setAttribute("data-state", "bad");
                confirm.setCustomValidity("Passwords do not match.");
            } else {
                hint.textContent = "Passwords match.";
                hint.setAttribute("data-state", "good");
                confirm.setCustomValidity("");
            }
        }

        pw.addEventListener("input", checkMatch);
        confirm.addEventListener("input", checkMatch);

        form.addEventListener("submit", function (e) {
            checkMatch();
            if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
            }
        });
    }

    /* ---------- show / hide password ---------- */
    function initPasswordToggles() {
        Array.prototype.forEach.call(
            document.querySelectorAll("[data-pw-toggle]"),
            function (button) {
                var field = document.getElementById(button.getAttribute("data-pw-toggle"));
                if (!field) return;

                button.addEventListener("click", function () {
                    var reveal = field.type === "password";
                    field.type = reveal ? "text" : "password";
                    button.textContent = reveal ? "Hide" : "Show";
                    button.setAttribute("aria-pressed", String(reveal));
                    field.focus();
                });
            }
        );
    }

    /* ---------- boot ---------- */
    document.addEventListener("DOMContentLoaded", function () {
        hydrateNav();
        injectCsrfTokens();
        initPasswordMeter();
        initRegisterForm();
        initPasswordToggles();
    });
})();
