<?php
include 'header.php';
include 'recaptcha_config.php';
?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<section id="contact-page">
    <div class="container">
        <div class="section-header"><h2>Contact Us</h2></div>

        <div class="glass-panel" style="max-width: 800px; margin: 0 auto;">
            <p class="text-center mb-5" style="color:#aaa;">The best way to contact us is to use our contact form below.</p>

            <form action="contact_work.php" method="POST">
                <!-- Honeypot + timestamp (fallback when reCAPTCHA is not configured) -->
                <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off">
                <input type="hidden" name="_ts" value="<?php echo time(); ?>">
                <div class="row gy-4">
                    <div class="col-12">
                        <label style="color: var(--accent);">Full Name</label>
                        <input type="text" class="form-control form-control-dark" name="fullname" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label style="color: var(--accent);">Email</label>
                        <input type="email" class="form-control form-control-dark" name="email" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label style="color: var(--accent);">Phone</label>
                        <input type="tel" class="form-control form-control-dark" name="phone">
                    </div>
                    
                    <div class="col-12">
                        <label style="color: var(--accent);">Message</label>
                        <textarea class="form-control form-control-dark" name="message" rows="4" required></textarea>
                    </div>

                    <?php if (function_exists('recaptcha_enabled') && recaptcha_enabled()): ?>
                        <div class="col-12 mt-4 text-center">
                            <div class="g-recaptcha d-inline-block" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                        </div>
                    <?php else: ?>
                        <div class="col-12 mt-4 text-center" style="color:#888; font-size:0.95rem;">
                            Security: Using lightweight spam protection.
                        </div>
                    <?php endif; ?>

                    <div class="col-12 text-center mt-4">
                        <button class="btn-clear" type="submit">Submit Message</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
