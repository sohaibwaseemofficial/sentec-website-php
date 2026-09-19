document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('eventRegistrationForm');
    const submitBtn = document.getElementById('submitBtn');
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    const errorMsg = document.getElementById('errorMessage');

    if (!form) return; // Stop if form is missing

    form.addEventListener('submit', function(e) {
        e.preventDefault(); // STOP the page from reloading

        // 1. Show Loading State
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';

        // 2. Gather Data
        const formData = new FormData(form);

        // 3. Send to PHP
        fetch('submit_registration.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text()) // Get text first to debug if JSON fails
        .then(text => {
            try {
                return JSON.parse(text); // Try parsing JSON
            } catch (e) {
                console.error('Server returned invalid JSON:', text);
                throw new Error('Server Error. Check console for details.');
            }
        })
        .then(data => {
            if (data.success) {
                // 4. Success!
                successModal.show();
                form.reset();
                // Redirect or reset view after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                // 5. Server Error (e.g., Missing fields)
                throw new Error(data.message || 'Unknown error occurred');
            }
        })
        .catch(error => {
            // 6. Network/Code Error
            errorMsg.textContent = error.message;
            errorModal.show();
        })
        .finally(() => {
            // 7. Reset Button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
});
