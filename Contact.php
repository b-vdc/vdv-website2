<?php
$title = "Contact – Van der Volpi";
$description = "Get in touch with Van der Volpi: book a call, ask a question or plan a training. Based in Ghent, working with brands and creators everywhere.";
$ogDescription = "Get in touch: book a call, ask a question or plan a training.";
$active = "Contact";
$pageScripts = ["js/contact.js", "js/booking.js"];
include __DIR__ . "/includes/head.php";
?>

<?php include __DIR__ . "/includes/header.php"; ?>

  <main>

    <section class="page-hero">
      <div class="container hero-split">
        <div class="hero-inner">
          <h1>Get in touch</h1>
          <p class="lead">Do you have a question, a contract that needs a second look, a doubt about a campaign or a trademark that needs to be protected? Send an email or book a call, let's discuss where you are at and what you need.</p>
        </div>
        <div class="hero-media">
          <div class="hero-portrait">
            <img src="images/contact.jpg" alt="Elisa Volpi taking a phone call at her desk" width="1000" height="1200">
          </div>
        </div>
      </div>
    </section>

    <!-- Contact details -->
    <section class="section-tight">
      <div class="container split">
        <div class="reveal">
          <h2>Reach me directly</h2>
          <ul class="contact-details">
            <li>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
              <a href="mailto:info@vandervolpi.com">info@vandervolpi.com</a>
            </li>
            <li>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
              <a href="tel:+32474055052">+32 474 055 052</a>
            </li>
            <li>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
              Alijt Bakehof 10, 9030 Ghent
            </li>
            <li>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 10h8M8 14h5"/></svg>
              Business number: BE 1001.894.390
            </li>
            <li>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1"/></svg>
              <a href="https://www.instagram.com/vandervolpi/" target="_blank" rel="noopener">@vandervolpi</a>
            </li>
            <li>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10.5V17M8 7.5v.01M12 17v-4a2 2 0 0 1 4 0v4"/></svg>
              <a href="https://www.linkedin.com/company/van-der-volpi/" target="_blank" rel="noopener">Van der Volpi on LinkedIn</a>
            </li>
          </ul>
        </div>

        <!-- Contact form -->
        <div class="form-card reveal">
          <h4>Send a message</h4>
          <p>Fill in the form below and I will get back to you within 48 hours.</p>

          <form id="contact-form" class="contact-form" novalidate>
            <div class="form-field">
              <label for="cf-name">Name</label>
              <input type="text" id="cf-name" name="name" autocomplete="name" required maxlength="120">
            </div>
            <div class="form-field">
              <label for="cf-email">Email</label>
              <input type="email" id="cf-email" name="email" autocomplete="email" required maxlength="180">
            </div>
            <div class="form-field">
              <label for="cf-message">Message</label>
              <textarea id="cf-message" name="message" rows="5" required maxlength="5000"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Send message</button>

            <p class="form-recaptcha-note">This form is protected by reCAPTCHA. The <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms of Service</a> apply. See our <a href="/privacy-policy">privacy policy</a> for how your message is used.</p>

            <div id="form-status" class="form-status" role="status" aria-live="polite"></div>
          </form>

          <div id="consent-gate" class="consent-gate" hidden>
            <p>To protect this form from spam, sending a message needs Google reCAPTCHA, which needs your consent first.</p>
            <button type="button" id="consent-gate-accept" class="btn btn-outline">Accept and continue</button>
            <p class="meta-note">Prefer not to? Email me directly at <a href="mailto:info@vandervolpi.com">info@vandervolpi.com</a>.</p>
          </div>
      </div>
    </section>

    <!-- Booking -->
    <section class="section-periwinkle">
      <div class="container">
        <div class="section-head reveal">
          <h2>Or book a call</h2>
          <p>Three kinds of calls, depending on what you need. Pick one to see when I'm available.</p>
        </div>
        <div class="reveal">
          <div class="call-types" id="booking-types">
            <button type="button" class="call-type" data-type="intake" aria-pressed="false">
              <h4>Intake call</h4>
              <p class="price">Free – 20 minutes</p>
              <p>A first conversation to look at your situation and figure out the next step. No strings attached.</p>
            </button>
            <button type="button" class="call-type" data-type="training" aria-pressed="false">
              <h4>Training booking</h4>
              <p class="price">Free</p>
              <p>Planning a training for your team, agency or class? We'll pick the track, the format and a date.</p>
            </button>
            <button type="button" class="call-type" data-type="legal" aria-pressed="false">
              <h4>Legal session</h4>
              <p class="price">&euro;170/hour</p>
              <p>A working session on your specific case. Charged per started 15 minutes, with a 30-minute minimum.</p>
            </button>
          </div>

          <div id="booker" class="form-card booker" hidden>
            <div id="booking-picker">
              <h4>Pick a date and time</h4>
              <p class="booker-note">All times are Brussels time.</p>
              <div class="booker-grid">
                <div class="booker-days" id="booking-days"></div>
                <div class="booker-times" id="booking-times"></div>
              </div>
            </div>

            <form id="booking-form" class="booking-form" novalidate hidden>
              <p class="booker-summary" id="booking-summary"></p>
              <div class="form-field">
                <label for="bk-name">Name</label>
                <input type="text" id="bk-name" name="name" autocomplete="name" required maxlength="120">
              </div>
              <div class="form-field">
                <label for="bk-email">Email</label>
                <input type="email" id="bk-email" name="email" autocomplete="email" required maxlength="180">
              </div>
              <div class="form-field">
                <label for="bk-phone">Phone</label>
                <input type="tel" id="bk-phone" name="phone" autocomplete="tel" required maxlength="40">
              </div>
              <div class="form-field">
                <label for="bk-message">What would you like to discuss? (optional)</label>
                <textarea id="bk-message" name="message" rows="3" maxlength="2000"></textarea>
              </div>

              <button type="submit" class="btn btn-primary">Confirm booking</button>

              <p class="form-recaptcha-note">This form is protected by reCAPTCHA. The <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms of Service</a> apply. See our <a href="/privacy-policy">privacy policy</a> for how your booking is used.</p>
            </form>

            <div id="booking-status" class="form-status" role="status" aria-live="polite"></div>

            <div id="booking-consent-gate" class="consent-gate" hidden>
              <p>To protect bookings from spam, confirming needs Google reCAPTCHA, which needs your consent first.</p>
              <button type="button" id="booking-consent-accept" class="btn btn-outline">Accept and continue</button>
              <p class="meta-note">Prefer not to? Email me at <a href="mailto:info@vandervolpi.com">info@vandervolpi.com</a> or call <a href="tel:+32474055052">+32 474 055 052</a>.</p>
            </div>

            <div id="booking-success" class="booking-success" hidden>
              <h4>Booked, see you then</h4>
              <p id="booking-success-summary"></p>
              <p id="booking-success-meet"></p>
              <p>You'll get a confirmation email and a Google Calendar invite shortly. Need to move the call? Just reply to the confirmation email.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

<?php include __DIR__ . "/includes/footer.php"; ?>
