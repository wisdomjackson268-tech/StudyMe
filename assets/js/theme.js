document.addEventListener("DOMContentLoaded", () => {
    const themeBtn = document.querySelector(".theme-btn");
    const body = document.body;

    function updateThemeUI() {
        if (!themeBtn) return;
        if (body.classList.contains("dark")) {
            themeBtn.innerHTML = '<i class="bi bi-sun-fill"></i>';
            themeBtn.setAttribute("aria-label", "Switch to Light Mode");
        } else {
            themeBtn.innerHTML = '<i class="bi bi-moon-stars-fill"></i>';
            themeBtn.setAttribute("aria-label", "Switch to Dark Mode");
        }
    }

    // Initialize stored theme state
    const savedTheme = localStorage.getItem("theme");
    if (savedTheme === "dark") {
        body.classList.add("dark");
    } else if (savedTheme === "light") {
        body.classList.remove("dark");
    }
    updateThemeUI();

    if (themeBtn) {
        themeBtn.addEventListener("click", () => {
            body.classList.toggle("dark");
            const currentTheme = body.classList.contains("dark") ? "dark" : "light";
            localStorage.setItem("theme", currentTheme);
            updateThemeUI();
        });
    }
});