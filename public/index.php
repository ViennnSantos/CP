<?php
// /public/index.php - PUBLIC landing page (no auth required)
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/lib/cms_helper.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// CRITICAL: Check if we're in preview mode
$isPreview = isset($GLOBALS['cms_preview_content']) && !empty($GLOBALS['cms_preview_content']);

// If in preview, use the content passed by cms_preview.php
// Otherwise, fetch published content for public view
if ($isPreview) {
  $cmsContent = $GLOBALS['cms_preview_content'];
} else {
  $cmsContent = getCMSContent('home_public'); // Gets published by default
}

$user = $_SESSION['user'] ?? null;
$isCustomer = $user && (($user['aud'] ?? '') === 'customer');

// If customer is logged in, redirect to customer homepage
if ($isCustomer) {
  header('Location: /RADS-TOOLING/customer/homepage.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rads Tooling - Custom Cabinet Solutions</title>
  <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/Homepage.css" />
  <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/chat-widget.css">
  <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/responsive.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
</head>

<body>
  <div class="page-wrapper">

    <!-- HEADER -->
    <header class="navbar">
      <div class="navbar-container">
        <div class="navbar-brand">
          <a href="/RADS-TOOLING/public/index.php" class="logo-link">
            <span class="logo-text">R</span>ADS <span class="logo-text">T</span>OOLING
          </a>
        </div>

        <form class="search-container" action="/RADS-TOOLING/public/products.php" method="get">
          <input type="text" name="q" class="search-input" placeholder="Search cabinets..." />
          <button type="submit" class="search-btn" aria-label="Search">
            <span class="material-symbols-rounded">search</span>
          </button>
        </form>

        <div class="navbar-actions">
          <a href="/RADS-TOOLING/customer/cust_login.php" class="nav-link">
            <span class="material-symbols-rounded">login</span>
            <span>Login</span>
          </a>
          <a href="/RADS-TOOLING/customer/register.php" class="nav-link">
            <span class="material-symbols-rounded">person_add</span>
            <span>Sign Up</span>
          </a>
          <a href="/RADS-TOOLING/admin/login.php" class="nav-link-icon" title="Staff Login">
            <span class="material-symbols-rounded">admin_panel_settings</span>
          </a>
        </div>
      </div>

      <nav class="navbar-menu">
        <a href="/RADS-TOOLING/public/index.php" class="nav-menu-item active">Home</a>
        <a href="/RADS-TOOLING/public/about.php" class="nav-menu-item">About Us</a>
        <a href="/RADS-TOOLING/public/products.php" class="nav-menu-item">Products</a>
        <a href="/RADS-TOOLING/public/testimonials.php" class="nav-menu-item">Testimonials</a>
      </nav>
    </header>

    <main>
      <!-- HERO SECTION -->
      <section class="hero-section">
        <div class="hero-content-wrapper">
          <div class="hero-text-content">
            <?php echo $cmsContent['hero_headline'] ?? '<h1>Welcome to RADS Tooling</h1>'; ?>
            <?php echo $cmsContent['hero_subtitle'] ?? '<p>Your trusted partner in custom cabinets</p>'; ?>
            <div class="hero-actions">
              <a href="/RADS-TOOLING/customer/register.php" class="btn-cta-primary">
                <i class="fas fa-rocket"></i> Get Started Free
              </a>
              <a href="/RADS-TOOLING/public/products.php" class="btn-cta-secondary">
                <i class="fas fa-th-large"></i> Browse Gallery
              </a>
            </div>

            <!-- Trust Indicators -->
            <div class="trust-badges">
              <div class="badge">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $cmsContent['trust_badge_1'] ?? '17+ Years Experience'; ?></span>
              </div>
              <div class="badge">
                <i class="fas fa-star"></i>
                <span><?php echo $cmsContent['trust_badge_2'] ?? '100+ Happy Clients'; ?></span>
              </div>
              <div class="badge">
                <i class="fas fa-tools"></i>
                <span><?php echo $cmsContent['trust_badge_3'] ?? 'Premium Quality'; ?></span>
              </div>
            </div>
          </div>
          <div class="hero-image-content">
            <?php
            $heroMedia = $cmsContent['hero_image'] ?? '/RADS-TOOLING/assets/images/cabinet-hero.jpg';
            $heroPath  = parse_url($heroMedia, PHP_URL_PATH) ?: $heroMedia;
            $ext       = strtolower(pathinfo($heroPath, PATHINFO_EXTENSION));
            $isGLB     = ($ext === 'glb');
            ?>
            <div class="hero-image-content">
              <?php if ($isGLB): ?>
                <div id="hero3d" style="width:100%;max-width:580px;height:460px;border-radius:16px;overflow:hidden;background:#f8fafc"></div>
                <script type="importmap">
                  {
                  "imports": {
                      "three": "/RADS-TOOLING/assets/vendor_js/three/three.module.js",
                      "three/addons/": "/RADS-TOOLING/assets/vendor_js/three/"
                  }
                }
                </script>
                <script type="module">
                  import * as THREE from 'three';
                  import {
                    OrbitControls
                  } from 'three/addons/controls/OrbitControls.js';
                  import {
                    GLTFLoader
                  } from 'three/addons/loaders/GLTFLoader.js';

                  const MODEL_URL = <?= json_encode($heroMedia) ?>;
                  const wrap = document.getElementById('hero3d');

                  const scene = new THREE.Scene();
                  scene.background = new THREE.Color(0xffffff);

                  const renderer = new THREE.WebGLRenderer({
                    antialias: true
                  });
                  renderer.setPixelRatio(Math.min(2, window.devicePixelRatio || 1));
                  renderer.outputColorSpace = THREE.SRGBColorSpace;
                  wrap.appendChild(renderer.domElement);

                  const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
                  camera.position.set(3, 2, 3);

                  const controls = new OrbitControls(camera, renderer.domElement);
                  controls.enableZoom = false; 
                  controls.enablePan = false; 
                  controls.enableRotate = false;
                  controls.autoRotate = true;
                  controls.autoRotateSpeed = 0.8;

                  scene.add(new THREE.HemisphereLight(0xffffff, 0xb0b0b0, 0.9));
                  const dir = new THREE.DirectionalLight(0xffffff, 0.8);
                  dir.position.set(5, 5, 5);
                  scene.add(dir);

                  const loader = new GLTFLoader();
                  console.log('[HERO3D] Loading:', MODEL_URL);

                  loader.load(
                    MODEL_URL,
                    (gltf) => {
                      const model = gltf.scene;
                      const box = new THREE.Box3().setFromObject(model);
                      const size = box.getSize(new THREE.Vector3()).length();
                      const center = box.getCenter(new THREE.Vector3());
                      model.position.sub(center);
                      model.scale.multiplyScalar(2.2 / size);
                      scene.add(model);
                      console.log('[HERO3D] Loaded OK');
                    },
                    undefined,
                    (err) => {
                      console.error('[HERO3D] Load failed:', err);
                      wrap.innerHTML = '<div style="padding:16px;color:#ef4444;background:#fff1f2;border-radius:12px">Failed to load 3D model. Check path/MIME.</div>';
                    }
                  );

                  function resize() {
                    const w = wrap.clientWidth,
                      h = wrap.clientHeight || 460; // guard
                    renderer.setSize(w, h, false);
                    camera.aspect = w / h;
                    camera.updateProjectionMatrix();
                  console.log('[HERO3D] Resized to', w, 'x', h);

                  }
                  resize();
                  window.addEventListener('resize', resize);
                  
                  // Fix: Resize when page becomes visible again (e.g., after navigating back)
                  document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                      setTimeout(resize, 100); // Small delay to ensure container has correct size
                    }
                  });

                  // Fix: Observe container size changes using ResizeObserver
                  if (typeof ResizeObserver !== 'undefined') {
                    const resizeObserver = new ResizeObserver(() => {
                      resize();
                    });
                    resizeObserver.observe(wrap);
                  }
                  (function animate() {
                    requestAnimationFrame(animate);
                    controls.update();
                    renderer.render(scene, camera);
                  })();
                </script>
              <?php else: ?>
                <img src="<?= htmlspecialchars($heroMedia) ?>" alt="Custom Cabinets">
              <?php endif; ?>
            </div>
          </div>
        </div>
  </div>
  </section>

  <!-- FEATURES SECTION -->
  <section class="features-section">
    <div class="section-header">
      <?php echo $cmsContent['features_title'] ?? '<h2>Why Choose RADS TOOLING?</h2>'; ?>
      <?php echo $cmsContent['features_subtitle'] ?? '<p>Everything you need to create your perfect cabinet</p>'; ?>
    </div>

    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-cube"></i>
        </div>
        <h3>3D Customization</h3>
        <p>Visualize your cabinet in real-time with our 360° preview tool. Choose colors, materials, and dimensions.</p>
        <a href="/RADS-TOOLING/customer/register.php" class="feature-link">Start Customizing →</a>
      </div>

      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-shipping-fast"></i>
        </div>
        <h3>Track Your Orders</h3>
        <p>Monitor every step from production to delivery with real-time status updates.</p>
        <a href="/RADS-TOOLING/customer/cust_login.php" class="feature-link">Login to Track →</a>
      </div>

      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-credit-card"></i>
        </div>
        <h3>Fast Checkout</h3>
        <p>Secure payment via GCash QR code. Pay 30% down payment to start production.</p>
        <a href="/RADS-TOOLING/customer/register.php" class="feature-link">Create Account →</a>
      </div>
    </div>
  </section>

  <!-- CABINETS GALLERY CAROUSEL -->
  <section class="cabinets-section">
    <div class="section-header">
      <h2>Featured <span class="highlight">Cabinets</span></h2>
      <p>Explore our collection of premium custom cabinets</p>
    </div>

    <div class="carousel-container">
      <button class="carousel-btn prev" type="button" aria-label="Previous">
        <span class="material-symbols-rounded">chevron_left</span>
      </button>

      <div class="carousel-track">
        <?php
        $carouselImages = $cmsContent['carousel_images'] ?? [];
        foreach ($carouselImages as $image):
        ?>
          <div class="carousel-item">
            <img src="<?php echo htmlspecialchars($image['image']); ?>"
              alt="<?php echo htmlspecialchars($image['title']); ?>">
            <div class="carousel-caption">
              <h4><?php echo htmlspecialchars($image['title']); ?></h4>
              <p><?php echo htmlspecialchars($image['description']); ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <button class="carousel-btn next" type="button" aria-label="Next">
        <span class="material-symbols-rounded">chevron_right</span>
      </button>
    </div>

    <div class="carousel-dots"></div>
  </section>

  <!-- VIDEO SECTION -->
  <section class="craft">
    <div class="craft-grid">
      <div class="craft-text">
        <h2>Crafted with Passion &amp; Precision</h2>
        <p>Every cabinet is handcrafted by skilled artisans using premium materials. Watch our craftsmen bring your vision to life.</p>
        <ul class="craft-list">
          <li>Premium hardwood materials</li>
          <li>Expert craftsmanship</li>
          <li>Quality assurance tested</li>
          <li>Custom finishing options</li>
        </ul>
      </div>

      <div class="craft-media">
        <div class="craft-media-box">
          <video class="craft-video"
            src="/RADS-TOOLING/uploads/general/Cabinets.mp4"
            muted autoplay loop playsinline controls
            preload="metadata"
            poster="/RADS-TOOLING/assets/images/cab1.jpg"></video>
        </div>
      </div>
    </div>
  </section>


  <!-- CTA BANNER -->
  <section class="cta-banner">
    <div class="cta-content">
      <?php echo $cmsContent['cta_headline'] ?? '<h2>Ready to Design Your Dream Cabinet?</h2>'; ?>
      <?php echo $cmsContent['cta_text'] ?? '<p>Join hundreds of satisfied customers</p>'; ?>
      <div class="cta-buttons">
        <a href="/RADS-TOOLING/customer/register.php" class="btn-cta-primary btn-large">
          <i class="fas fa-user-plus"></i> Create Free Account
        </a>
        <a href="/RADS-TOOLING/public/testimonials.php" class="btn-cta-secondary btn-large">
          <i class="fas fa-images"></i> View Gallery
        </a>
      </div>
    </div>
  </section>

  <!-- RADS-TOOLING Chat Support Widget (Guest Mode) -->
  <button id="rtChatBtn" class="rt-chat-btn">
    <span class="material-symbols-rounded">chat</span>
    Need Help?
  </button>

  <div id="rtChatPopup" class="rt-chat-popup">
    <div class="rt-chat-header">
      <span>Rads Tooling - Chat Support</span>
    </div>

    <!-- Message: Please Login -->
    <div class="rt-chat-messages" style="padding: 40px 20px; text-align: center;">
      <div style="margin-bottom: 20px;">
        <i class="fas fa-lock" style="font-size: 48px; color: #1f4e74; opacity: 0.6;"></i>
      </div>
      <h3 style="color: #333; margin-bottom: 10px;">Chat Support Unavailable</h3>
      <p style="color: #666; margin-bottom: 20px;">
        Please login or create an account to chat with our support team and get instant answers to your questions.
      </p>
      <a href="/RADS-TOOLING/customer/cust_login.php"
        style="display: inline-block; padding: 12px 24px; background: #1f4e74; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">
        <i class="fas fa-sign-in-alt"></i> Login Now
      </a>
    </div>
  </div>

  <script>
    // Simple toggle for public page (no chat functionality until login)
    document.addEventListener('DOMContentLoaded', function() {
      const chatBtn = document.getElementById('rtChatBtn');
      const chatPopup = document.getElementById('rtChatPopup');
      const chatClose = document.getElementById('rtChatClose');

      if (chatBtn && chatPopup && chatClose) {
        chatBtn.addEventListener('click', function() {
          chatPopup.style.display = 'flex';
          chatBtn.style.display = 'none';
        });

        chatClose.addEventListener('click', function() {
          chatPopup.style.display = 'none';
          chatBtn.style.display = 'flex';
        });
      }
    });
  </script>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-content">
      <!-- About Section -->
      <div class="footer-section">
        <h3><?php echo $cmsContent['footer_company_name'] ?? 'About RADS TOOLING'; ?></h3>
        <p class="footer-description">
          <?php echo $cmsContent['footer_description'] ?? 'Premium custom cabinet manufacturer serving clients since 2007.
            Quality craftsmanship, affordable prices, and exceptional service.'; ?>
        </p>
        <div class="footer-social">
          <a href="#" class="social-icon" aria-label="Facebook">
            <span class="material-symbols-rounded">facebook</span>
          </a>
          <a href="mailto:RadsTooling@gmail.com" class="social-icon" aria-label="Email">
            <span class="material-symbols-rounded">mail</span>
          </a>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="footer-section">
        <h3>Quick Links</h3>
        <ul class="footer-links">
          <li><a href="/RADS-TOOLING/public/index.php">Home</a></li>
          <li><a href="/RADS-TOOLING/public/about.php">About Us</a></li>
          <li><a href="/RADS-TOOLING/public/products.php">Products</a></li>
          <li><a href="/RADS-TOOLING/public/testimonials.php">Testimonials</a></li>
          <li><a href="/RADS-TOOLING/customer/register.php">Sign Up</a></li>
          <li><a href="/RADS-TOOLING/customer/cust_login.php">Login</a></li>
        </ul>
      </div>

      <!-- Categories -->
      <div class="footer-section">
        <h3>Categories</h3>
        <ul class="footer-links">
          <li><a href="/RADS-TOOLING/public/products.php?type=Kitchen">Kitchen Cabinet</a></li>
          <li><a href="/RADS-TOOLING/public/products.php?type=Wardrobe">Wardrobe</a></li>
          <li><a href="/RADS-TOOLING/public/products.php?type=Office Cabinet">Office Cabinet</a></li>
          <li><a href="/RADS-TOOLING/public/products.php?type=Bathroom Cabinet">Bathroom Cabinet</a></li>
          <li><a href="/RADS-TOOLING/public/products.php?type=Storage Cabinet">Storage Cabinet</a></li>
        </ul>
      </div>

      <!-- Contact Info -->
      <div class="footer-section">
        <h3>Contact Info</h3>
        <div class="contact-info-item">
          <span class="material-symbols-rounded">location_on</span>
          <span><?php echo $cmsContent['footer_address'] ?? 'Green Breeze, Piela, Dasmariñas, Cavite'; ?></span>
        </div>
        <div class="contact-info-item">
          <span class="material-symbols-rounded">mail</span>
          <a href="mailto:<?php echo $cmsContent['footer_email'] ?? 'RadsTooling@gmail.com'; ?>">
            <?php echo $cmsContent['footer_email'] ?? 'RadsTooling@gmail.com'; ?>
          </a>
        </div>
        <div class="contact-info-item">
          <span class="material-symbols-rounded">phone</span>
          <span><?php echo $cmsContent['footer_phone'] ?? '+63 976 228 4270'; ?></span>
        </div>
        <div class="contact-info-item">
          <span class="material-symbols-rounded">schedule</span>
          <span><?php echo $cmsContent['footer_hours'] ?? 'Mon-Sat: 8:00 AM - 5:00 PM'; ?></span>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <p class="footer-copyright">
        <?php echo $cmsContent['footer_copyright'] ?? '© 2025 RADS TOOLING INC. All rights reserved.'; ?>
      </p>
      <div class="footer-legal">
        <a href="/RADS-TOOLING/public/privacy.php">Privacy Policy</a>
        <a href="/RADS-TOOLING/public/terms.php">Terms & Conditions</a>
      </div>
    </div>
  </footer>

  </div><!-- /.page-wrapper -->

  <script>
    (() => {
      const track = document.querySelector('.carousel-track');
      const items0 = [...document.querySelectorAll('.carousel-item')];
      const nextBtn = document.querySelector('.carousel-btn.next');
      const prevBtn = document.querySelector('.carousel-btn.prev');
      const dotsBox = document.querySelector('.carousel-dots');

      if (!track || items0.length === 0) return;

      // --- sizing helpers ---
      const stepWidth = () => (items0[1] ? items0[1].offsetLeft - items0[0].offsetLeft :
        items0[0].offsetWidth + 20);
      const perView = Math.max(1, Math.round(track.parentElement.offsetWidth / stepWidth()));

      // --- clone edges for seamless loop ---
      for (let i = 0; i < perView; i++) {
        const head = items0[i].cloneNode(true);
        const tail = items0[items0.length - 1 - i].cloneNode(true);
        head.classList.add('is-clone');
        tail.classList.add('is-clone');
        track.appendChild(head); // first N -> end
        track.insertBefore(tail, track.firstChild); // last N  -> start
      }

      const origCount = items0.length;
      let index = perView; // start at first real slide
      let isAnimating = false,
        tEndTimer;

      // dots
      dotsBox.innerHTML = '';
      const dots = items0.map((_, i) => {
        const d = document.createElement('span');
        d.className = 'dot' + (i === 0 ? ' active' : '');
        d.addEventListener('click', () => goTo(perView + i, true, true));
        dotsBox.appendChild(d);
        return d;
      });

      const setTransform = (withTransition) => {
        track.style.transition = withTransition ? 'transform .45s ease' : 'none';
        track.style.transform = `translateX(-${index * stepWidth()}px)`;
        const active = ((index - perView) % origCount + origCount) % origCount;
        dots.forEach((d, i) => d.classList.toggle('active', i === active));
      };

      const goTo = (i, withTransition = true, resetAuto = false) => {
        index = i;
        setTransform(withTransition);
        if (withTransition) watchTransition();
        if (resetAuto) restartAuto();
      };

      const watchTransition = () => {
        clearTimeout(tEndTimer);
        tEndTimer = setTimeout(onTransitionEnd, 600); // fallback if transitionend doesn’t fire
        isAnimating = true;
        toggleBtns(true);
      };

      const onTransitionEnd = () => {
        // snap back from clones (no transition so walang “jump”)
        if (index >= origCount + perView) index = perView;
        if (index < perView) index = origCount + perView - 1;
        setTransform(false);
        // re-enable clicks
        isAnimating = false;
        toggleBtns(false);
      };

      const step = (dir) => {
        if (isAnimating) return;
        goTo(index + dir, true, true);
      };

      const toggleBtns = (lock) => {
        [nextBtn, prevBtn].forEach(b => {
          if (!b) return;
          b.disabled = lock;
        });
      };

      track.addEventListener('transitionend', (e) => {
        if (e.propertyName === 'transform') onTransitionEnd();
      });

      nextBtn?.addEventListener('click', () => step(1));
      prevBtn?.addEventListener('click', () => step(-1));

      // autoplay
      let auto;
      const startAuto = () => auto = setInterval(() => step(1), 3500);
      const stopAuto = () => clearInterval(auto);
      const restartAuto = () => {
        stopAuto();
        startAuto();
      };

      track.parentElement.addEventListener('mouseenter', stopAuto);
      track.parentElement.addEventListener('mouseleave', startAuto);

      // init
      setTransform(false);
      startAuto();

      // keep position on resize
      window.addEventListener('resize', () => setTransform(false));
    })();
  </script>



  <script src="/RADS-TOOLING/assets/JS/chat_widget.js"></script>

</body>

</html>