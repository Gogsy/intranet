// Minimal script for the Web Tools / Documentation pages: just the mobile menu toggle.
document.addEventListener("DOMContentLoaded", function () {
    const menuToggle = document.querySelector(".menu-toggle");
    const navMenu = document.querySelector(".nav-menu");

    if (menuToggle && navMenu) {
        menuToggle.addEventListener("click", function (e) {
            e.preventDefault();
            navMenu.classList.toggle("active");
        });
    }
});
