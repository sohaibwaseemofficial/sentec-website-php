<?php
include 'header.php';
include 'db_connection.php';

// Fetch upcoming events
$sql = "SELECT * FROM events WHERE status = 'upcoming' ORDER BY event_date ASC LIMIT 3";
$result = $conn->query($sql);
?>

<style>
/* Featured Event Banner Styles */
.featured-event-card {
    display: flex;
    background: rgba(255, 255, 255, 0.05); /* Adjust based on your glass-panel theme */
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    overflow: hidden;
    text-decoration: none;
    color: white;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    width: 100%;
}

.featured-event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    color: white;
}

.featured-image-wrapper {
    flex: 0 0 45%;
    position: relative;
    overflow: hidden;
}

.featured-image-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    min-height: 400px;
}

.featured-content {
    flex: 1;
    padding: 3rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.featured-badge {
    background: linear-gradient(90deg, #00d2ff 0%, #3a7bd5 100%);
    color: white;
    padding: 6px 18px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: inline-block;
    margin-bottom: 15px;
    width: fit-content;
}

.featured-content h3 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
}

.featured-content p {
    color: #ccc;
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 0;
}

/* Responsive adjustment for mobile */
@media (max-width: 768px) {
    .featured-event-card {
        flex-direction: column;
    }
    .featured-image-wrapper img {
        min-height: 250px;
    }
    .featured-content {
        padding: 2rem;
    }
    .featured-content h3 {
        font-size: 2rem;
    }
}
</style>

<div class="hero-section" id="home">
    <div class="bg-slideshow">
        <div class="slide active" style="background-image: url('images/hero/1.webp');"></div>
        <div class="slide" style="background-image: url('images/hero/2.webp');"></div>
        <div class="slide" style="background-image: url('images/hero/3.webp');"></div>
    </div>
    <div class="overlay-gradient"></div>
    <div class="pill">NED UNIVERSITY • EST. 1997</div>
    <h1>Future Tech<br>Starts Here.</h1>
</div>

<section id="about">
    <div class="section-header"><h2>Who We Are</h2></div>
    <div class="highlight-text-container" id="highlight-text">
        The Society for Promotion of Science Engineering and Technology (SENTEC) stands as the sole Science and Technology Society of NED University. We aim to impart students with exposure to the practical implementation of their courses. Throughout the years, we have served as the definitive platform for students to showcase their scientific talents and refine their technical expertise across all engineering disciplines.
    </div>
</section>

<section id="events">
    <div class="section-header"><h2>Upcoming Events</h2></div>
    <div class="container">
        <div class="glass-panel">
            <div class="row gx-4 gy-4">
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $title = htmlspecialchars($row['title']);
                        $date = date('d M Y', strtotime($row['event_date']));
                        $image = str_replace('../', './', $row['image_url']);
                        $event_link = !empty($row['event_link']) ? htmlspecialchars($row['event_link']) : '#';
                        
                        // Determine if link should open in new tab
                        $target = ($event_link !== '#') ? 'target="_blank" rel="noopener noreferrer"' : '';
                        
                        // Check if the event is PROXION to feature it
                        if (stripos($title, 'PROXION') !== false) {
                            // Display more text for the main event
                            $desc = htmlspecialchars(substr($row['description'], 0, 300)) . '...';
                            ?>
                            
                            <div class="col-12 mb-4">
                                <a href="<?php echo $event_link; ?>" <?php echo $target; ?> class="featured-event-card">
                                    <div class="featured-image-wrapper">
                                        <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>">
                                    </div>
                                    <div class="featured-content">
                                        <span class="featured-badge">Flagship Event</span>
                                        <span class="date" style="color: #00d2ff; font-weight: bold; margin-bottom: 10px; display: block; font-size: 1.1rem;"><?php echo $date; ?></span>
                                        <h3><?php echo $title; ?></h3>
                                        <p><?php echo $desc; ?></p>
                                    </div>
                                </a>
                            </div>
                            
                            <?php
                        } else {
                            // STANDARD EVENT LAYOUT
                            $desc = htmlspecialchars(substr($row['description'], 0, 100)) . '...';
                            ?>
                            
                            <div class="col-md-4">
                                <a href="<?php echo $event_link; ?>" <?php echo $target; ?> class="event-card" style="text-decoration: none;">
                                    <div class="event-image-wrapper">
                                        <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>">
                                    </div>
                                    <span class="date"><?php echo $date; ?></span>
                                    <h3><?php echo $title; ?></h3>
                                    <p><?php echo $desc; ?></p>
                                </a>
                            </div>
                            
                            <?php
                        }
                    }
                } else {
                    echo '<div class="col-12 text-center"><p style="color:#ccc; font-size:1.2rem;">No upcoming events scheduled right now.</p></div>';
                }
                ?>
            </div>
        </div>
    </div>
</section>

<section id="faculty">
    <div class="container">
        <div class="section-header"><h2>Our Faculty Incharge</h2></div>
        
        <div class="profile-card">
            <div class="profile-glow"></div>
            <img src="images/facinc.png" class="profile-img" alt="Prof Dr Murtuza">
            
            <div class="faculty-content">
                <i class="fas fa-quote-left quote-icon-new"></i>
                <p class="profile-quote">
                    "A good balance between academics and activities is crucial. SENTEC can be a great platform for students to develop their capabilities as engineering graduates."
                </p>
                <div>
                    <h3 class="faculty-name">Prof. Dr. Murtuza</h3>
                    <span class="faculty-designation">Faculty Incharge SENTEC</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="contact">
    <div class="container">
        <div class="glass-panel">
            <div class="contact-layout">
                
                <div class="contact-left">
                    <h2 style="font-size: 3.5rem; font-family: 'Outfit', sans-serif; font-weight: 800; margin-bottom: 20px; color:#fff; line-height: 1.1;">Get In<br>Touch.</h2>
                    <p style="color: #ccc; margin-bottom: 30px; font-size: 1.1rem;">Have questions? We are here to help.</p>
                    <a href="contact" class="btn-clear contact-btn">Contact Us Now</a>
                </div>

                <div class="contact-right">
                    <details class="faq-item">
                        <summary>How do I join SENTEC?</summary>
                        <p>Recruitment drives happen annually. Stay tuned to our social media channels.</p>
                    </details>
                    <details class="faq-item">
                        <summary>Where is the office located?</summary>
                        <p>We are located at the Student Affairs Department premises within the NED University main campus.</p>
                    </details>
                     <details class="faq-item">
                        <summary>Do I need prior experience?</summary>
                        <p>Absolutely not! SENTEC is a learning society designed to help you build skills from scratch.</p>
                    </details>
                </div>

            </div>
        </div>
    </div>
</section>

<?php 
$conn->close();
include 'footer.php'; 
?>