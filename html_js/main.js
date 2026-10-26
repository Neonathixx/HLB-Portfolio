/* ==========================================================================
   HLB PORTFOLIO — main.js
   Handles: session-aware nav, password strength, register form validation
   ========================================================================== */
   (function () {
    "use strict";

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

        fetch("session-status.php", {
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
                    '<a href="account.php" class="NavButton NavAccount" title="My account">' +
                        '<span class="NavAvatar">' + initial + "</span>" +
                        "<p>" + name + "</p>" +
                    "</a>" +
                    '<a href="logout.php" class="NavButton NavLogout" title="Log out">' +
                        "<p>Log Out</p>" +
                    "</a>";

                loginLink.replaceWith(group);
            })
            .catch(function () { /* silent — stay on Log In link */ });
    }

    /* ---------- register page: password strength ---------- */
    function initPasswordMeter() {
        var pw = document.getElementById("password");
        var meter = document.getElementById("pwMeter");
        var label = document.getElementById("pwLabel");
        if (!pw || !meter || !label) return;

        pw.addEventListener("input", function () {
            var v = pw.value;
            var score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;

            meter.dataset.score = String(score);
            meter.firstElementChild.style.width = (score / 4) * 100 + "%";

            var txt = ["Too short", "Weak", "Fair", "Good", "Strong"][score];
            label.textContent = "Password strength: " + txt;
        });
    }

    /* ---------- register page: confirm-match + client validation ---------- */
    function initRegisterForm() {
        var form = document.getElementById("registerForm");
        if (!form) return;

        var pw = form.querySelector("#password");
        var confirm = form.querySelector("#confirm");
        var hint = document.getElementById("confirmHint");

        function checkMatch() {
            if (!confirm.value) { hint.textContent = ""; confirm.setCustomValidity(""); return; }
            if (confirm.value !== pw.value) {
                hint.textContent = "Passwords do not match.";
                hint.style.color = "#b3261e";
                confirm.setCustomValidity("mismatch");
            } else {
                hint.textContent = "Passwords match ✓";
                hint.style.color = "#0a7d3a";
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

    /* ---------- boot ---------- */
    document.addEventListener("DOMContentLoaded", function () {
        hydrateNav();
        initPasswordMeter();
        initRegisterForm();
    });
})();