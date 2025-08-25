// CivicPulse Platform - Enhanced Theme System & Sidebar Management

document.addEventListener('DOMContentLoaded', () => {
  initializeTheme();
  initializeSidebar();
});

function initializeTheme() {
  const root = document.documentElement;
  const themeToggle = document.getElementById('themeToggle');
  const themeCheckbox = document.getElementById('themeToggleCheckbox');
  
  // Set initial theme
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme === 'dark') {
    root.setAttribute('data-theme', 'dark');
    themeCheckbox.checked = true;
  }
  
  // Theme toggle functionality
  themeToggle.addEventListener('click', () => {
    const isDark = root.getAttribute('data-theme') === 'dark';
    
    if (isDark) {
      root.removeAttribute('data-theme');
      localStorage.setItem('theme', 'light');
      themeCheckbox.checked = false;
    } else {
      root.setAttribute('data-theme', 'dark');
      localStorage.setItem('theme', 'dark');
      themeCheckbox.checked = true;
    }
    
    // Add transition effect
    root.style.transition = 'all 0.3s ease-in-out';
    setTimeout(() => {
      root.style.transition = '';
    }, 300);
  });
  
  // Keyboard support
  themeToggle.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      themeToggle.click();
    }
  });
}

function initializeSidebar() {
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');
  const appContainer = document.querySelector('.app-container');
  
  // Toggle sidebar
  sidebarToggle.addEventListener('click', () => {
    const isOpen = sidebar.classList.contains('open');
    
    if (isOpen) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });
  
  // Close sidebar when clicking backdrop
  sidebarBackdrop.addEventListener('click', closeSidebar);
  
  // Close sidebar on escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) {
      closeSidebar();
    }
  });
  
  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768) {
      const isClickInsideSidebar = sidebar.contains(e.target);
      const isClickOnToggle = sidebarToggle.contains(e.target);
      
      if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('open')) {
        closeSidebar();
      }
    }
  });
  
  function openSidebar() {
    sidebar.classList.add('open');
    sidebarBackdrop.classList.add('open');
    appContainer.classList.add('sidebar-open');
    sidebarToggle.setAttribute('aria-expanded', 'true');
    
    // Focus management
    const firstNavItem = sidebar.querySelector('.nav-item');
    if (firstNavItem) {
      firstNavItem.focus();
    }
  }
  
  function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarBackdrop.classList.remove('open');
    appContainer.classList.remove('sidebar-open');
    sidebarToggle.setAttribute('aria-expanded', 'false');
    
    // Return focus to toggle button
    sidebarToggle.focus();
  }
  
  // Handle window resize
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
      closeSidebar();
    }
  });
}

// Enhanced accessibility features
document.addEventListener('DOMContentLoaded', () => {
  // Add skip link for keyboard users
  const skipLink = document.createElement('a');
  skipLink.href = '#main-content';
  skipLink.textContent = 'Skip to main content';
  skipLink.className = 'skip-link';
  skipLink.style.cssText = `
    position: absolute;
    top: -40px;
    left: 6px;
    background: var(--accent-color);
    color: white;
    padding: 8px;
    text-decoration: none;
    border-radius: 4px;
    z-index: 1000;
    transition: top 0.3s;
  `;
  
  skipLink.addEventListener('focus', () => {
    skipLink.style.top = '6px';
  });
  
  skipLink.addEventListener('blur', () => {
    skipLink.style.top = '-40px';
  });
  
  document.body.insertBefore(skipLink, document.body.firstChild);
  
  // Add focus indicators for interactive elements
  const interactiveElements = document.querySelectorAll('button, a, input, textarea, select');
  interactiveElements.forEach(el => {
    el.addEventListener('focus', () => {
      el.style.outline = '2px solid var(--accent-color)';
      el.style.outlineOffset = '2px';
    });
    
    el.addEventListener('blur', () => {
      el.style.outline = '';
      el.style.outlineOffset = '';
    });
  });
});

// Performance optimizations
document.addEventListener('DOMContentLoaded', () => {
  // Lazy load images
  const images = document.querySelectorAll('img[data-src]');
  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        img.src = img.dataset.src;
        img.removeAttribute('data-src');
        observer.unobserve(img);
      }
    });
  });
  
  images.forEach(img => imageObserver.observe(img));
  
  // Preload critical resources
  const criticalLinks = [
    '/untitled_folder/assets/css/style.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
  ];
  
  criticalLinks.forEach(href => {
    const link = document.createElement('link');
    link.rel = 'preload';
    link.href = href;
    link.as = href.endsWith('.css') ? 'style' : 'font';
    document.head.appendChild(link);
  });
});


