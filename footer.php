<footer>
        <div class="social-container">
            <a href="https://www.facebook.com/sentecneduet" class="social-icon"><i class="fab fa-facebook-f"></i></a>
            <a href="https://www.instagram.com/sentecneduet/" class="social-icon"><i class="fab fa-instagram"></i></a>
            <a href="https://www.linkedin.com/company/sentecneduet/" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
            <a href="mailto:neduetsentec@gmail.com" class="social-icon"><i class="fas fa-envelope"></i></a>
        </div>

        <div class="footer-text">
            &copy; 2025 SENTEC. Designed with <span>Future Tech</span>.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 1. MOBILE MENU TOGGLE
        const menuToggle = document.getElementById('mobile-menu');
        const navLinks = document.querySelector('.nav-links');
        const navOverlay = document.getElementById('nav-overlay');

        if (menuToggle) {
            const closeMenu = () => {
                navLinks.classList.remove('active');
                menuToggle.classList.remove('active');
                document.body.classList.remove('nav-open');
                if (navOverlay) { navOverlay.style.display = 'none'; }
            };
            const openMenu = () => {
                navLinks.classList.add('active');
                menuToggle.classList.add('active');
                document.body.classList.add('nav-open');
                if (navOverlay) { navOverlay.style.display = 'block'; }
            };
            menuToggle.addEventListener('click', function() {
                if (navLinks.classList.contains('active')) closeMenu(); else openMenu();
            });
            if (navOverlay) {
                navOverlay.addEventListener('click', closeMenu);
            }
        }

        // Close menu when clicking links (except dropdown toggles)
        document.querySelectorAll('.nav-links a').forEach(link => {
            if (!link.classList.contains('dropdown-toggle')) {
                link.addEventListener('click', () => {
                    navLinks.classList.remove('active');
                    menuToggle.classList.remove('active');
                    document.body.classList.remove('nav-open');
                    if (navOverlay) { navOverlay.style.display = 'none'; }
                });
            }
        });

        // 1B. DROPDOWN TOGGLE FOR MOBILE
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                // Only handle click on mobile (768px and below)
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    const parent = toggle.closest('.nav-dropdown');
                    parent.classList.toggle('active');
                }
            });
        });

        // 2. SLIDESHOW
        const slides = document.querySelectorAll('.slide');
        let current = 0;
        if(slides.length > 0) {
            setInterval(() => {
                slides[current].classList.remove('active');
                current = (current + 1) % slides.length;
                slides[current].classList.add('active');
            }, 5000);
        }

        // 3. TEXT HIGHLIGHT ANIMATION (Fixed Trigger)
        const textContainer = document.getElementById('highlight-text');
        if(textContainer) {
            const textContent = textContainer.innerText;
            textContainer.innerHTML = ''; 
            textContent.split(' ').forEach(word => {
                const span = document.createElement('span');
                span.innerText = word + ' ';
                span.className = 'highlight-word';
                if(['SENTEC', 'Science', 'Technology', 'NED', 'Engineering'].includes(word.replace(/[.,()]/g, ''))) {
                    span.classList.add('accent');
                }
                textContainer.appendChild(span);
            });

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('lit');
                    }
                });
            }, { threshold: 0.1 }); // Trigger as soon as 10% is visible

            document.querySelectorAll('.highlight-word').forEach(word => observer.observe(word));
        }
    </script>


<script>
// "Full Proof" Automatic WebP Converter & Compressor
document.addEventListener('DOMContentLoaded', function() {
    // 1. Find ALL file inputs on the page (Registration, Team, Gallery, etc.)
    const fileInputs = document.querySelectorAll('input[type="file"]');

    fileInputs.forEach(input => {
        input.addEventListener('change', async function(e) {
            const originalFile = e.target.files[0];
            if (!originalFile) return;

            // Visual feedback for the user
            const parent = input.parentElement;
            let status = parent.querySelector('.conversion-status');
            if (!status) {
                status = document.createElement('div');
                status.className = 'conversion-status small mt-1';
                parent.appendChild(status);
            }
            status.innerHTML = '<span style="color:#00ff94"><i class="fas fa-circle-notch fa-spin"></i> Optimizing image...</span>';

            try {
                // 2. Convert to WebP
                const convertedFile = await convertToWebP(originalFile);

                // 3. Replace the original file in the form with the new WebP file
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(convertedFile);
                input.files = dataTransfer.files;

                status.innerHTML = `<span style="color:#00ff94">✔ Ready! ${(originalFile.size/1024/1024).toFixed(1)}MB → ${(convertedFile.size/1024).toFixed(0)}KB</span>`;
                
                // Clear any previous error colors
                input.style.borderColor = '#444'; 

            } catch (err) {
                console.error("Conversion failed:", err);
                status.innerHTML = '<span style="color:orange">⚠ could not convert (sending original)</span>';
            }
        });
    });

    // Helper Function: The Conversion Logic
    function convertToWebP(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function(event) {
                const img = new Image();
                img.src = event.target.result;
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    
                    // Smart Resize: If image is massive (>1920px), shrink it to save space
                    let width = img.width;
                    let height = img.height;
                    const MAX_WIDTH = 1920;
                    
                    if (width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    }

                    canvas.width = width;
                    canvas.height = height;
                    
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Convert to WebP with 80% quality
                    canvas.toBlob(function(blob) {
                        if (blob) {
                            const newFile = new File([blob], file.name.split('.')[0] + ".webp", {
                                type: "image/webp",
                                lastModified: Date.now()
                            });
                            resolve(newFile);
                        } else {
                            reject("Blob creation failed");
                        }
                    }, 'image/webp', 0.8);
                };
                img.onerror = reject;
            };
            reader.onerror = reject;
        });
    }
});
</script>

</body>
</html>

