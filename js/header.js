AOS.init({
    duration: 1200, // Animation duration (in milliseconds)
    once: true      // Animation happens only once when scrolled
});

document.addEventListener("DOMContentLoaded", () => {
    const preloader = document.getElementById("preloader");
    if (preloader) {
        setTimeout(() => {
            preloader.style.opacity = 0;
            preloader.style.transition = "opacity 1s ease";
            setTimeout(() => {
                preloader.style.display = "none";
            }, 1000); // Matches the fade-out duration
        }, 3000); // Total time for the animation (spinning + text reveal)
    }

    // Mobile navigation toggle
    const menuToggle = document.getElementById("mobile-menu");
    const navLinks = document.querySelector(".nav-links");
    if (menuToggle && navLinks) {
        menuToggle.addEventListener("click", () => {
            navLinks.classList.toggle("active");
            menuToggle.classList.toggle("open");
        });

        // Close menu on link click (better UX) - except dropdown toggles
        navLinks.querySelectorAll("a").forEach(link => {
            if (!link.classList.contains("dropdown-toggle")) {
                link.addEventListener("click", () => {
                    navLinks.classList.remove("active");
                    menuToggle.classList.remove("open");
                });
            }
        });
    }

    // Dropdown toggle for mobile
    const dropdownToggles = document.querySelectorAll(".dropdown-toggle");
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener("click", (e) => {
            // Only handle click on mobile
            if (window.innerWidth <= 768) {
                e.preventDefault();
                const parent = toggle.closest(".nav-dropdown");
                parent.classList.toggle("active");
            }
        });
    });
});
