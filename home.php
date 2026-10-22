
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneLara | Barangay Lara Digital Services</title>
    <meta name="description" content="OneLara is the official digital services platform of Barangay Lara, City of San Fernando, Pampanga — document requests, complaints, announcements, and more in one place.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css"><link rel="stylesheet" href="assets/css/home.css">
</head>
<body class="home-page">

<nav class="navbar">
    <div class="nav-container">

        <a href="home.php" class="nav-logo">
            <i class="fas fa-pepper-hot"></i>
            <span>One<span class="chili-text">Lara</span></span>
        </a>

        <ul class="nav-menu">
            <li>
                <a href="#home">
                    <i class="fas fa-house"></i> Home
                </a>
            </li>

            <li>
                <a href="#services">
                    <i class="fas fa-layer-group"></i> Services
                </a>
            </li>

            <li>
                <a href="#about">
                    <i class="fas fa-circle-info"></i> About
                </a>
            </li>

            <li>
                <a href="#contact">
                    <i class="fas fa-envelope"></i> Contact
                </a>
            </li>

        </ul>

        <div class="hamburger" aria-label="Toggle navigation menu" role="button" tabindex="0">
            <span></span>
            <span></span>
            <span></span>
        </div>

    </div>
</nav>


<section id="home" class="hero">
    <div class="hero-content">
        <span class="hero-badge">
            <i class="fas fa-location-dot" aria-hidden="true"></i> Barangay Lara &middot; City of San Fernando, Pampanga
        </span>
        <h1>Welcome to <span class="chili-text">Barangay Lara</span></h1>
        <p>One platform for document requests, complaints, announcements, and every service your barangay offers &mdash; the hottest community in town.🌶️</p>
        <div class="hero-buttons">
            <a href="#services" class="btn btn-primary">
                <i class="fas fa-layer-group"></i> Our Services
            </a>
            <button type="button" id="viewMapBtn" class="btn btn-secondary">
                <i class="fas fa-location-dot"></i> View Map
            </button>
        </div>
    </div>
    <div id="map" class="map-container" role="img" aria-label="Map showing the location of Barangay Lara"></div>
</section>


<section id="services" class="services">
    <div class="container">
        <span class="section-label" style="display:block; text-align:center;">WHAT WE OFFER</span>
        <h2 class="reveal">Your Guide to Digital Services</h2>
        <p class="section-intro">Learn how to use each barangay service. Open a guide below to see what to prepare and the steps to follow.</p>
        <div class="service-guides">
            <details>
                <summary><i class="fas fa-file-alt" aria-hidden="true"></i><span>Request a document<small>Prepare and submit a barangay document request</small></span></summary>
                <div class="service-guide-body"><p><strong>Prepare:</strong> Your purpose, the number of copies needed, and any supporting files.</p><ol><li>Sign in to your resident account and open Document Requests.</li><li>Choose the document type, enter your purpose and quantity, and attach supporting files if needed.</li><li>Submit your request. Check My Requests for fees, status updates, and staff remarks.</li><li>If a fee applies, follow the payment instructions and wait for verification. Check that the document is ready before collecting it.</li></ol></div>
            </details>
            <details>
                <summary><i class="fas fa-user-circle" aria-hidden="true"></i><span>Update your resident profile<small>Keep your personal and contact details current</small></span></summary>
                <div class="service-guide-body"><p><strong>Prepare:</strong> Your current address and contact details.</p><ol><li>Sign in and open your profile.</li><li>Review your information and update the editable fields.</li><li>Save your changes. Contact the barangay office if a detail cannot be corrected through your profile.</li></ol></div>
            </details>
            <details>
                <summary><i class="fas fa-building" aria-hidden="true"></i><span>Reserve a facility<small>Plan a community activity or gathering</small></span></summary>
                <div class="service-guide-body"><p><strong>Prepare:</strong> Your preferred facility, activity details, and schedule.</p><ol><li>Sign in and open Facility Reservations.</li><li>Select the facility and complete the schedule and purpose fields.</li><li>Submit the reservation and check My Reservations for its status and any payment instructions.</li><li>Confirm that the reservation is approved before making final arrangements.</li></ol></div>
            </details>
            <details>
                <summary><i class="fas fa-calendar-check" aria-hidden="true"></i><span>Book an appointment<small>Arrange a visit for barangay or health services</small></span></summary>
                <div class="service-guide-body"><p><strong>Prepare:</strong> The purpose of your visit and a preferred date and time.</p><ol><li>Sign in and open Book an Appointment.</li><li>Choose the appointment type, enter your purpose, and select a date and time slot.</li><li>Submit and check My Appointments for confirmation or status changes before your visit.</li></ol></div>
            </details>
            <details>
                <summary><i class="fas fa-bullhorn" aria-hidden="true"></i><span>Read community announcements<small>Stay informed about notices and activities</small></span></summary>
                <div class="service-guide-body"><ol><li>Sign in and open Announcements.</li><li>Read the notice carefully, including any dates, locations, and participation instructions.</li><li>Contact the barangay office if you need clarification about an announcement.</li></ol></div>
            </details>
            <details>
                <summary><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><span>Report an incident or concern<small>Give the barangay the details needed to review your report</small></span></summary>
                <div class="service-guide-body"><p><strong>Prepare:</strong> A clear description of what happened, where and when it happened, and any relevant supporting information.</p><ol><li>Sign in and open Report an Incident.</li><li>Complete the report form with accurate details.</li><li>Submit your report and keep its reference number.</li><li>Check My Reports for progress and resolution details.</li></ol></div>
            </details>
            <details>
                <summary><i class="fas fa-list-check" aria-hidden="true"></i><span>Track requests and payments<small>Understand the next step after submitting</small></span></summary>
                <div class="service-guide-body"><ol><li>Sign in and open My Requests or My Reservations, depending on the service.</li><li>Review the status, amount due, and remarks.</li><li>If payment is required, open the payment option and follow the cash or GCash instructions.</li><li>Wait for staff verification and check back for the next status update.</li></ol></div>
            </details>
            <details>
                <summary><i class="fas fa-heartbeat" aria-hidden="true"></i><span>View your consultations<small>Review information recorded by the health team</small></span></summary>
                <div class="service-guide-body"><ol><li>Sign in and open My Consultations.</li><li>Review your recorded consultations and the available details.</li><li>Contact the health team if you have questions or believe a record needs correcting.</li></ol></div>
            </details>
        </div>
        <p class="service-guide-note">Not sure which service you need? <a href="#contact">Get in touch with the barangay office &rarr;</a></p>
    </div>
</section>

<section id="getting-started" class="home-guide"><div class="container">
<div class="section-heading contact-heading"><span class="section-label">YOUR FIRST VISIT</span><h2>Barangay services, step by step.</h2><p>Prepare your details, choose what you need, and follow your request from your dashboard.</p></div>
<ol class="home-steps">
<li><span class="step-number">01</span><h3>Create your account</h3><p>Register with your details and a photo of an ID or utility bill showing your Barangay Lara address. Wait for system admin approval before signing in.</p><a href="register.php">Register now &rarr;</a></li>
<li><span class="step-number">02</span><h3>Choose a service</h3><p>Complete the service form and check the required information or supporting documents before submitting.</p><a href="#services">Explore services &rarr;</a></li>
<li><span class="step-number">03</span><h3>Follow your progress</h3><p>Visit your dashboard to review request status, appointment schedules, and remarks from barangay staff.</p><a href="login.php">Sign in &rarr;</a></li>
</ol>
<div class="home-help-strip"><div><h3>Need a hand getting started?</h3><p>Contact the office for help with your account or choosing a service.</p></div><a href="#contact" class="btn btn-primary">Get in touch &rarr;</a></div>
</div></section><section id="about" class="about">
    <div class="container about-container">

        <div class="about-visual reveal">
            <div class="about-main-card">
                <div class="about-chili">
                    <i class="fas fa-pepper-hot"></i>
                </div>
                <span>ONE PLATFORM</span>
                <h3>Connecting Barangay Lara</h3>
                <p>Simple. Accessible. Connected.</p>
            </div>

            <div class="about-small-card">
                <i class="fas fa-users"></i>
                <strong>Community First</strong>
                <span>Designed around residents</span>
            </div>
        </div>

        <div class="about-content reveal">
            <span class="section-label">ABOUT ONELARA</span>
            <h2>A smarter way to connect with your barangay.</h2>
            <p>
                OneLara is a web-based barangay management platform
                designed to bring important community services into
                one centralized system.
            </p>
            <p>
                Through OneLara, residents can access available
                barangay services, submit requests, receive updates,
                and stay informed about community activities.
            </p>

            <div class="about-features">
                <div>
                    <i class="fas fa-circle-check"></i>
                    <span>Convenient online services</span>
                </div>
                <div>
                    <i class="fas fa-circle-check"></i>
                    <span>Centralized information</span>
                </div>
                <div>
                    <i class="fas fa-circle-check"></i>
                    <span>Better community communication</span>
                </div>
                <div>
                    <i class="fas fa-circle-check"></i>
                    <span>Accessible barangay services</span>
                </div>
            </div>
        </div>

    </div>
</section>

<section id="faq" class="home-faq"><div class="container">
<div class="section-heading contact-heading"><span class="section-label">BEFORE YOU START</span><h2>A little help goes a long way.</h2><p>Quick answers to common questions about using OneLara.</p></div>
<div class="faq-list">
<details><summary>Do I need an account to use digital services?</summary><p>Yes. <a href="register.php">Create a resident account</a> or <a href="login.php">sign in</a> to submit requests and view your records. Office contact details are available on this page without signing in.</p></details>
<details><summary>What should I prepare for a document request?</summary><p>Have your purpose and any supporting documents ready. The request form lists available document types and fees. Contact the office if you are unsure which document you need.</p></details>
<details><summary>How can I check my request or reservation?</summary><p>Sign in and open My Requests or My Reservations in your resident dashboard. Review the status and any remarks before visiting the office.</p></details>
<details><summary>How do I settle a service fee?</summary><p>Open the payment option on an eligible document request or reservation. Follow the displayed instructions for cash or GCash. Payments are reviewed by barangay staff.</p></details>
<details><summary>Can I ask the barangay a question online?</summary><p>Yes. Use <a href="resident/messages.php">Messages in your resident account</a>, email the office, or call using the contact links below.</p></details>
</div></div></section><section id="contact" class="contact">
    <div class="container">

        <div class="section-heading contact-heading reveal">
            <span class="section-label">GET IN TOUCH</span>
            <h2>Have a question?</h2>
            <p>For inquiries, assistance, or barangay-related concerns, you may contact the Barangay Lara office.</p>
        </div>

        <div class="contact-grid">
            <a href="https://www.google.com/maps/dir/?api=1&amp;destination=15.0876516%2C120.617113" class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-location-dot"></i>
                </div><h3>Visit Us</h3><p>Barangay Lara<br>City of San Fernando, Pampanga</p><span class="card-action">Get directions &rarr;</span></a>

            <a href="tel:+639243102696" class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-phone"></i>
                </div><h3>Call Us</h3><p>(Viber) 0924 -310-2696<br>Monday &ndash; Friday</p><span class="card-action">Call the office &rarr;</span></a>

            <a href="mailto:barangaylaracouncil@gmail.com" class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-envelope"></i>
                </div><h3>Email Us</h3><p>barangaylaracouncil@gmail.com<br>We will be happy to assist you.</p><span class="card-action">Write an email &rarr;</span></a>
        </div>


    </div>
</section>

<footer class="footer">
    <div class="container footer-container">

        <div class="footer-brand">
            <a href="home.php" class="footer-logo">
                <i class="fas fa-pepper-hot"></i>
                <span>One<span class="chili-text">Lara</span></span>
            </a>
            <p>
                The official digital services platform of Barangay Lara,
                City of San Fernando, Pampanga &mdash; bringing community
                services online, all in one place.
            </p>
            <div class="footer-social">
                <a href="https://www.facebook.com/profile.php?id=61572534703210" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="https://m.me/61572534703210" aria-label="Messenger"><i class="fab fa-facebook-messenger"></i></a>
                <a href="mailto:barangaylaracouncil@gmail.com" aria-label="Email"><i class="fas fa-envelope"></i></a>
            </div>
        </div>

        <div class="footer-links">
            <h3>Quick Links</h3>
            <a href="#home">Home</a>
            <a href="#services">Services</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
        </div>

        <div class="footer-links">
            <h3>Get Started</h3>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </div>

    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> OneLara &mdash; Barangay Lara, City of San Fernando, Pampanga. All rights reserved.</p>
        <p class="footer-credits">System developed by Jomar C. Bangajon, Kenji C. Bangajon, and Lemuel P. Esguerra.</p>
    </div>
</footer>

<div id="mapModal" class="map-modal" aria-hidden="true">
    <div class="map-modal-backdrop" data-close-map></div>
    <div class="map-modal-box" role="dialog" aria-modal="true" aria-label="Barangay Lara map">
        <button type="button" class="map-modal-close" data-close-map aria-label="Close map">
            <i class="fas fa-xmark"></i>
        </button>
        <div class="map-modal-header">
            <i class="fas fa-location-dot"></i>
            <div>
                <h3>Barangay Lara</h3>
                <span>City of San Fernando, Pampanga</span>
            </div>
        </div>
        <div id="mapModalCanvas" class="map-modal-canvas"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const ONELARA_COORDS = [15.0876516, 120.617113];
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
