<footer class="text-center mt-5 pt-4" style="border-top: 1px solid rgba(255,255,255,0.1); color: #666; font-size: 0.8rem;">
        <p>&copy; <?php echo date("Y"); ?> SENTEC Admin Portal. Secure & Powered by Future Tech.</p>
    </footer>

</div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function(){
        const btn = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        if (!btn || !sidebar) return;
        let overlay = document.querySelector('.admin-overlay');
        if (!overlay){
                overlay = document.createElement('div');
                overlay.className = 'admin-overlay';
                document.body.appendChild(overlay);
        }
        const close = () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); };
        const open  = () => { sidebar.classList.add('open'); overlay.classList.add('show'); };
        btn.addEventListener('click', () => {
                if (sidebar.classList.contains('open')) close(); else open();
        });
        overlay.addEventListener('click', close);
    })();
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


