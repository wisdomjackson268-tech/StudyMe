
(function () {

    const savedTheme = localStorage.getItem("studyme_theme") || localStorage.getItem("theme");
    const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    const initialTheme = savedTheme ? savedTheme : (prefersDark ? "dark" : "light");

    function applyTheme(theme) {
        const isDark = theme === "dark";

        document.documentElement.setAttribute("data-bs-theme", theme);
        if (isDark) {
            document.documentElement.classList.add("dark");
            document.documentElement.classList.remove("light");
        } else {
            document.documentElement.classList.add("light");
            document.documentElement.classList.remove("dark");
        }

        if (document.body) {
            if (isDark) {
                document.body.classList.add("dark");
                document.body.classList.remove("light");
            } else {
                document.body.classList.add("light");
                document.body.classList.remove("dark");
            }
        }

        localStorage.setItem("studyme_theme", theme);
        localStorage.setItem("theme", theme);

        const allThemeButtons = document.querySelectorAll(".theme-btn, [data-theme-toggle]");
        allThemeButtons.forEach(btn => {
            if (isDark) {
                btn.innerHTML = '<i class="bi bi-sun-fill text-warning"></i>';
                btn.setAttribute("title", "Switch to Light Mode");
                btn.setAttribute("aria-label", "Switch to Light Mode");
            } else {
                btn.innerHTML = '<i class="bi bi-moon-stars-fill"></i>';
                btn.setAttribute("title", "Switch to Dark Mode");
                btn.setAttribute("aria-label", "Switch to Dark Mode");
            }
        });
    }

    applyTheme(initialTheme);

    document.addEventListener("DOMContentLoaded", () => {

        const currentStored = localStorage.getItem("studyme_theme") || localStorage.getItem("theme") || initialTheme;
        applyTheme(currentStored);

        document.addEventListener("click", (e) => {
            const btn = e.target.closest(".theme-btn, [data-theme-toggle]");
            if (!btn) return;

            e.preventDefault();
            const isCurrentlyDark = document.body.classList.contains("dark") || document.documentElement.getAttribute("data-bs-theme") === "dark";
            const newTheme = isCurrentlyDark ? "light" : "dark";

            applyTheme(newTheme);

            if (typeof StudyMeFeedback !== "undefined" && StudyMeFeedback.click) {
                StudyMeFeedback.click();
            }
        });
    });
})();
