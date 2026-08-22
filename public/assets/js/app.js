document.addEventListener('DOMContentLoaded', function () {
  // Header scroll
  var header = document.querySelector('.site-header');
  if (header) {
    window.addEventListener('scroll', function () {
      header.classList.toggle('scrolled', window.scrollY > 20);
    }, { passive: true });
  }

  // Theme switcher
  var ts = document.querySelector('.theme-switcher');
  var tb = document.querySelector('.theme-btn');
  if (tb && ts) {
    tb.addEventListener('click', function (e) {
      e.stopPropagation();
      ts.classList.toggle('open');
    });
    document.addEventListener('click', function () {
      ts.classList.remove('open');
    });
    var opts = document.querySelectorAll('.theme-option');
    for (var i = 0; i < opts.length; i++) {
      opts[i].addEventListener('click', function () {
        var slug = this.getAttribute('data-theme');
        fetch('/api-theme.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ theme: slug })
        }).then(function (r) { return r.json(); }).then(function (d) {
          if (d.success) location.reload();
        }).catch(function () {
          var f = document.createElement('form');
          f.method = 'POST';
          f.action = '/api-theme.php';
          var inp = document.createElement('input');
          inp.type = 'hidden';
          inp.name = 'theme';
          inp.value = slug;
          f.appendChild(inp);
          document.body.appendChild(f);
          f.submit();
        });
      });
    }
  }

  // Size / color pills
  document.querySelectorAll('.option-pill').forEach(function (pill) {
    pill.addEventListener('click', function () {
      var group = this.parentElement;
      group.querySelectorAll('.option-pill').forEach(function (p) {
        p.classList.remove('selected');
      });
      this.classList.add('selected');
      var input = document.getElementById(this.getAttribute('data-input'));
      if (input) input.value = this.getAttribute('data-value');
    });
  });

  // Qty buttons
  document.querySelectorAll('.qty-minus').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var inp = this.parentElement.querySelector('input');
      var v = parseInt(inp.value, 10) || 1;
      if (v > 1) inp.value = v - 1;
    });
  });
  document.querySelectorAll('.qty-plus').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var inp = this.parentElement.querySelector('input');
      var v = parseInt(inp.value, 10) || 1;
      inp.value = v + 1;
    });
  });

  // Mobile menu
  var menuToggle = document.getElementById('menu-toggle') || document.querySelector('.menu-toggle');
  var nav = document.getElementById('main-nav') || document.querySelector('.nav');
  if (menuToggle && nav) {
    menuToggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      nav.classList.toggle('open');
      if (nav.classList.contains('open')) {
        nav.style.display = 'flex';
        nav.style.flexDirection = 'column';
        nav.style.position = 'absolute';
        nav.style.top = '70px';
        nav.style.left = '0';
        nav.style.right = '0';
        nav.style.background = 'var(--color-surface)';
        nav.style.padding = '16px';
        nav.style.borderBottom = '1px solid var(--color-border)';
        nav.style.zIndex = '999';
      } else {
        nav.style.display = '';
        nav.style.flexDirection = '';
        nav.style.position = '';
      }
    });
  }

  // Cart button
  var cartBtn = document.getElementById('cart-btn');
  if (cartBtn) {
    cartBtn.style.pointerEvents = 'auto';
  }


  // Categories dropdown
  document.querySelectorAll('.nav-drop-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var parent = this.closest('.nav-dropdown');
      document.querySelectorAll('.nav-dropdown').forEach(function (d) {
        if (d !== parent) d.classList.remove('open');
      });
      parent.classList.toggle('open');
    });
  });

  // Hero slider + blurred background
  (function () {
    var root = document.getElementById('hero-slider');
    if (!root) return;
    var slides = root.querySelectorAll('.slide');
    var dots = root.querySelectorAll('.dot');
    var blur = document.getElementById('hero-blur-bg');
    if (slides.length < 1) return;
    var i = 0;
    var timer = null;

    function setBlur(idx) {
      if (!blur) return;
      var slide = slides[idx];
      if (!slide) return;
      var bg = slide.getAttribute('data-bg') || '';
      if (!bg) {
        var img = slide.querySelector('.slide-img');
        if (img) bg = img.src || '';
      }
      if (bg) {
        blur.style.backgroundImage = 'url("' + bg + '")';
        blur.classList.add('is-ready');
      }
    }

    function go(n) {
      if (slides.length < 2) {
        setBlur(0);
        return;
      }
      slides[i].classList.remove('active');
      if (dots[i]) dots[i].classList.remove('active');
      i = (n + slides.length) % slides.length;
      slides[i].classList.add('active');
      if (dots[i]) dots[i].classList.add('active');
      setBlur(i);
    }

    function next() { go(i + 1); }
    function prev() { go(i - 1); }
    function start() {
      if (slides.length < 2) return;
      timer = setInterval(next, 4000);
    }
    function stop() {
      if (timer) clearInterval(timer);
    }

    var nextBtn = document.getElementById('slide-next');
    var prevBtn = document.getElementById('slide-prev');
    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        e.preventDefault();
        stop();
        next();
        start();
      });
    }
    if (prevBtn) {
      prevBtn.addEventListener('click', function (e) {
        e.preventDefault();
        stop();
        prev();
        start();
      });
    }
    for (var d = 0; d < dots.length; d++) {
      dots[d].addEventListener('click', function () {
        stop();
        go(parseInt(this.getAttribute('data-index'), 10) || 0);
        start();
      });
    }

    root.addEventListener('mouseenter', stop);
    root.addEventListener('touchstart', stop, { passive: true });
    root.addEventListener('mouseleave', start);
    setBlur(0);
    start();
  })();
});
