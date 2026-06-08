/* =============================================
   EZY KEYS DRIVING SCHOOL — Main JavaScript
   ============================================= */

/* --- Navbar scroll effect --- */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  if (window.scrollY > 40) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});

/* --- Mobile nav toggle --- */
const navToggle = document.getElementById('navToggle');
if (navToggle) {
  navToggle.addEventListener('click', () => {
    document.body.classList.toggle('nav-open');
    const isOpen = document.body.classList.contains('nav-open');
    navToggle.setAttribute('aria-expanded', isOpen);
  });

  // Close nav when a link is clicked
  document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', () => {
      document.body.classList.remove('nav-open');
    });
  });

  // Close nav when clicking outside
  document.addEventListener('click', (e) => {
    if (!navbar.contains(e.target)) {
      document.body.classList.remove('nav-open');
    }
  });
}

/* --- Scroll to top button --- */
const scrollTopBtn = document.getElementById('scrollTop');
if (scrollTopBtn) {
  window.addEventListener('scroll', () => {
    if (window.scrollY > 400) {
      scrollTopBtn.classList.add('visible');
    } else {
      scrollTopBtn.classList.remove('visible');
    }
  });

  scrollTopBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

/* --- FAQ accordion --- */
document.querySelectorAll('.faq-question').forEach(button => {
  button.addEventListener('click', () => {
    const item = button.closest('.faq-item');
    const isOpen = item.classList.contains('open');

    // Close all open items
    document.querySelectorAll('.faq-item.open').forEach(openItem => {
      openItem.classList.remove('open');
      openItem.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
    });

    // Open clicked item if it was closed
    if (!isOpen) {
      item.classList.add('open');
      button.setAttribute('aria-expanded', 'true');
    }
  });
});

/* --- Animate elements on scroll (Intersection Observer) --- */
const observerOptions = {
  root: null,
  rootMargin: '0px',
  threshold: 0.12,
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('in-view');
      observer.unobserve(entry.target);
    }
  });
}, observerOptions);

// Add animation CSS inline so it works without extra files
const animStyle = document.createElement('style');
animStyle.textContent = `
  .fade-up {
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.55s ease, transform 0.55s ease;
  }
  .fade-up.in-view {
    opacity: 1;
    transform: translateY(0);
  }
  .fade-up:nth-child(2) { transition-delay: 0.08s; }
  .fade-up:nth-child(3) { transition-delay: 0.16s; }
  .fade-up:nth-child(4) { transition-delay: 0.24s; }
  .fade-up:nth-child(5) { transition-delay: 0.32s; }
  .fade-up:nth-child(6) { transition-delay: 0.40s; }
`;
document.head.appendChild(animStyle);

// Apply animation class to key elements
const animateTargets = [
  '.service-card',
  '.step-card',
  '.area-card',
  '.pricing-card',
  '.review-card',
  '.why-point',
  '.why-small-card',
  '.faq-item',
];

animateTargets.forEach(selector => {
  document.querySelectorAll(selector).forEach(el => {
    el.classList.add('fade-up');
    observer.observe(el);
  });
});

/* --- Contact form submission (demo) --- */
const contactForm = document.getElementById('contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const btn = contactForm.querySelector('button[type="submit"]');
    btn.textContent = '✅ Message Sent!';
    btn.disabled = true;
    btn.style.background = 'var(--success)';
    btn.style.borderColor = 'var(--success)';
    setTimeout(() => {
      btn.textContent = 'Send Message';
      btn.disabled = false;
      btn.style.background = '';
      btn.style.borderColor = '';
      contactForm.reset();
    }, 3500);
  });
}

/* --- Set active nav link based on current page --- */
const currentPage = window.location.pathname.split('/').pop();
document.querySelectorAll('.nav-links a').forEach(link => {
  const linkPage = link.getAttribute('href').split('/').pop();
  if (linkPage === currentPage || (currentPage === '' && linkPage === 'index.html')) {
    link.classList.add('active');
  } else {
    link.classList.remove('active');
  }
});
