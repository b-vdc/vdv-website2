
  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-logo">
          <img src="https://vandervolpi.com/assets/logo/VDV_Logo1_off-white_RGB.png" alt="Van der Volpi" width="2001" height="1653" loading="lazy">
        </div>
        <div>
          <h4>Get in touch</h4>
          <ul>
            <li><a href="mailto:info@vandervolpi.com">info@vandervolpi.com</a></li>
            <li><a href="tel:+32474055052">+32 474 055 052</a></li>
            <li>Alijt Bakehof 10, 9030 Ghent</li>
            <li>BE 1001.894.390</li>
          </ul>
        </div>
        <div>
          <h4>Explore</h4>
          <ul>
            <li><a href="Training.php">Training</a></li>
            <li><a href="Advice.php">Advice</a></li>
            <li><a href="About.php">About</a></li>
            <li><a href="Contact.php">Contact</a></li>
          </ul>
        </div>
        <div>
          <h4>Follow along</h4>
          <ul>
            <li><a href="https://www.instagram.com/vandervolpi/" target="_blank" rel="noopener">Instagram</a></li>
            <li><a href="https://www.linkedin.com/company/van-der-volpi/" target="_blank" rel="noopener">LinkedIn</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <span>&copy; <span data-year>2026</span> Van der Volpi – Your brand online. <em>Legal.</em></span>
        <nav aria-label="Legal">
          <a href="Privacy-Policy.php">Privacy policy</a>
          <a href="Cookie-Policy.php">Cookie policy</a>
          <a href="Disclaimer.php">Terms &amp; disclaimer</a>
        </nav>
      </div>
    </div>
  </footer>

  <script src="js/consent.js"></script>
  <script src="js/main.js"></script>
<?php if (!empty($pageScripts)): foreach ($pageScripts as $pageScript): ?>
  <script src="<?= $pageScript ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
