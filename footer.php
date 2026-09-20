    </div><!-- /flex-grow -->

    <!-- Modern Dark Industrial Footer matching SiteChrome.tsx -->
    <footer class="w-full bg-[#0d1215] border-t border-white/[0.08] text-neutral-400 font-sans mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-10">
                
                <!-- Col 1: Brand & Bio -->
                <div class="md:col-span-5 space-y-4">
                    <a href="index.php" class="flex items-center gap-3 group text-decoration-none inline-flex">
                        <div class="w-9 h-9 rounded-full bg-transparent border border-white/[0.12] flex items-center justify-center p-1 overflow-hidden">
                            <img src="images/SENTECNEWWHITELOGO.webp" alt="SENTEC" class="w-full h-full object-contain">
                        </div>
                        <div class="flex items-baseline">
                            <span class="font-display font-extrabold text-xl tracking-tight text-white">SENTEC</span>
                            <span class="text-[#f15a24] font-black text-xl leading-none">.</span>
                        </div>
                    </a>
                    <p class="text-sm text-neutral-400 leading-relaxed max-w-sm">
                        The Society for Promotion of Science, Engineering and Technology at NED University. Empowering undergraduate engineers to build scalable, real-world systems since 1997.
                    </p>
                    <div class="pt-2 text-xs font-mono text-neutral-500 tracking-wider">
                        STATUS: ACTIVE // KARACHI, PAKISTAN
                    </div>
                </div>

                <!-- Col 2: Quick Links -->
                <div class="md:col-span-3 space-y-3">
                    <span class="text-xs font-mono text-[#f15a24] uppercase tracking-widest block mb-4">NAVIGATION</span>
                    <ul class="space-y-2.5 text-sm font-sans">
                        <li><a href="index.php#about" class="hover:text-white transition-colors">About SENTEC</a></li>
                        <li><a href="team.php" class="hover:text-white transition-colors">Executive Team</a></li>
                        <li><a href="index.php#events" class="hover:text-white transition-colors">Events & Olympiads</a></li>
                        <li><a href="OurPartners.php" class="hover:text-white transition-colors">Industry Partners</a></li>
                        <li><a href="gallery.php" class="hover:text-white transition-colors">Media Gallery</a></li>
                        <li><a href="contact.php" class="hover:text-white transition-colors">Contact Us</a></li>
                    </ul>
                </div>

                <!-- Col 3: Contact & Base -->
                <div class="md:col-span-4 space-y-3">
                    <span class="text-xs font-mono text-[#f15a24] uppercase tracking-widest block mb-4">CONTACT / BASE</span>
                    <div class="text-sm space-y-2">
                        <div>
                            <a href="mailto:info@sentecneduet.live" class="text-white hover:text-[#f15a24] transition-colors font-mono text-xs">
                                info@sentecneduet.live
                            </a>
                        </div>
                        <p class="text-xs text-neutral-400">
                            Student Affairs Department,<br>NED University of Engineering & Technology, Karachi.
                        </p>
                    </div>

                    <div class="pt-3 flex flex-wrap gap-4 text-xs font-semibold text-[#f15a24]">
                        <a href="https://www.linkedin.com/company/sentecneduet" target="_blank" rel="noopener noreferrer" class="hover:underline flex items-center gap-1">
                            LinkedIn <span>↗</span>
                        </a>
                        <a href="https://www.facebook.com/sentecneduet" target="_blank" rel="noopener noreferrer" class="hover:underline flex items-center gap-1">
                            Facebook <span>↗</span>
                        </a>
                        <a href="https://www.instagram.com/sentecneduet/" target="_blank" rel="noopener noreferrer" class="hover:underline flex items-center gap-1">
                            Instagram <span>↗</span>
                        </a>
                    </div>
                </div>

            </div>

            <!-- Bottom Industrial Bar -->
            <div class="mt-12 pt-6 border-t border-white/[0.06] flex flex-col sm:flex-row items-center justify-between text-xs font-mono text-neutral-500 gap-4">
                <span>© <?php echo date('Y'); ?> SENTEC / NED UNIVERSITY OF ENGINEERING & TECHNOLOGY</span>
                <span class="tracking-widest text-neutral-400">INNOVATE · ENGINEER · IMPACT</span>
            </div>
        </div>
    </footer>

    <!-- Mobile Navigation Toggle Script -->
    <script>
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenuDropdown = document.getElementById('mobileMenuDropdown');
        const mobileMenuIcon = document.getElementById('mobileMenuIcon');

        if (mobileMenuBtn && mobileMenuDropdown) {
            mobileMenuBtn.addEventListener('click', function() {
                const isHidden = mobileMenuDropdown.classList.contains('hidden');
                if (isHidden) {
                    mobileMenuDropdown.classList.remove('hidden');
                    mobileMenuIcon.classList.remove('fa-bars');
                    mobileMenuIcon.classList.add('fa-times');
                } else {
                    mobileMenuDropdown.classList.add('hidden');
                    mobileMenuIcon.classList.remove('fa-times');
                    mobileMenuIcon.classList.add('fa-bars');
                }
            });
        }
    </script>

    <!-- Full Proof Automatic Client-Side WebP Converter & Compressor -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', async function(e) {
                const originalFile = e.target.files[0];
                if (!originalFile) return;

                const parent = input.parentElement;
                let status = parent.querySelector('.conversion-status');
                if (!status) {
                    status = document.createElement('div');
                    status.className = 'conversion-status text-xs mt-1.5 font-mono';
                    parent.appendChild(status);
                }
                status.innerHTML = '<span class="text-[#00ff94]"><i class="fas fa-circle-notch fa-spin mr-1"></i> Optimizing image (WebP)...</span>';

                try {
                    const convertedFile = await convertToWebP(originalFile);
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(convertedFile);
                    input.files = dataTransfer.files;
                    status.innerHTML = `<span class="text-[#00ff94]">✔ Ready! ${(originalFile.size/1024/1024).toFixed(1)}MB → ${(convertedFile.size/1024).toFixed(0)}KB</span>`;
                } catch (err) {
                    console.warn("Client WebP conversion fallback:", err);
                    status.innerHTML = '<span class="text-orange-400">⚠ Original file preserved</span>';
                }
            });
        });

        function convertToWebP(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = function(event) {
                    const img = new Image();
                    img.src = event.target.result;
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
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
                        }, 'image/webp', 0.82);
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
