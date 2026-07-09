
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="index.php" aria-label="Van der Volpi – home">
        <img src="https://vandervolpi.com/assets/logo/VDV_Logo2_oranje_RGB.png" alt="Van der Volpi" width="4001" height="404">
      </a>
      <button class="nav-toggle" aria-expanded="false" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>
      <nav class="site-nav" aria-label="Main">
        <a href="index.php"<?= $active === 'Home' ? ' class="active"' : '' ?>>Home</a>
        <a href="Training.php"<?= $active === 'Training' ? ' class="active"' : '' ?>>Training</a>
        <a href="Advice.php"<?= $active === 'Advice' ? ' class="active"' : '' ?>>Advice</a>
        <a href="About.php"<?= $active === 'About' ? ' class="active"' : '' ?>>About</a>
        <a href="Contact.php" class="nav-cta<?= $active === 'Contact' ? ' active' : '' ?>">Contact</a>
      </nav>
    </div>
  </header>
