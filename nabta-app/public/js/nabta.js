/* ============================================================================
   NABTA — storefront behaviour
   ----------------------------------------------------------------------------
   Ported from the Design Canvas component in NABTA.dc.html. The motion is
   copied verbatim (same GSAP timelines, same easings, same numbers); what
   changed is where state lives:

     - the cart is the server session, not localStorage, so prices and totals
       can never be edited in devtools;
     - isDesktop/isMobile are CSS media queries, not React state;
     - sc-if / sc-for became Alpine templates over the same shapes.

   Everything hangs off one global, NB, because the markup binds to plain
   onclick="NB.x(event)" attributes and needs no Alpine scope to work.
   ========================================================================== */
(function (w, d) {
  'use strict';

  var CFG = w.NABTA || {};
  var NB = {};
  w.NB = NB;

  /* ---------- money ------------------------------------------------------ */
  NB.fmt = function (n) {
    return Number(n).toLocaleString('en-US') + ' ج.م';
  };

  /* ---------- server-backed cart ----------------------------------------- */
  var cartState = CFG.cart || { count: 0, subtotal: 0, lines: [] };

  function url(template, value) {
    return String(template).replace('__ID__', value).replace('__SLUG__', value);
  }

  function post(endpoint, method, body) {
    return fetch(endpoint, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': CFG.csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined
    }).then(function (res) {
      if (!res.ok) throw new Error('cart request failed: ' + res.status);
      return res.json();
    });
  }

  function applyCart(data) {
    cartState = data;
    if (w.Alpine && w.Alpine.store('cart')) {
      var s = w.Alpine.store('cart');
      s.count = data.count;
      s.subtotal = data.subtotal;
      s.lines = data.lines;
    }
    bumpBadge();
    w.dispatchEvent(new CustomEvent('nabta:cart', { detail: data }));
  }

  function bumpBadge() {
    if (!w.gsap) return;
    var badge = d.querySelector('[data-cart-badge]');
    if (badge) w.gsap.fromTo(badge, { scale: .5 }, { scale: 1, duration: .5, ease: 'back.out(3)' });
  }

  NB.cart = {
    state: function () { return cartState; },
    add: function (id, qty) {
      return post(url(CFG.routes.cartAdd, id), 'POST', { qty: qty || 1 }).then(applyCart);
    },
    set: function (id, qty) {
      return post(url(CFG.routes.cartItem, id), 'PATCH', { qty: qty }).then(applyCart);
    },
    remove: function (id) {
      return post(url(CFG.routes.cartItem, id), 'DELETE').then(applyCart);
    },
    refresh: function () {
      return fetch(CFG.routes.cart, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      }).then(function (r) { return r.json(); }).then(applyCart);
    }
  };

  /* ---------- Alpine stores ---------------------------------------------- */
  d.addEventListener('alpine:init', function () {
    w.Alpine.store('cart', {
      count: cartState.count,
      subtotal: cartState.subtotal,
      lines: cartState.lines,
      get isEmpty() { return this.lines.length === 0; },
      get subtotalLabel() { return NB.fmt(this.subtotal); },
      lineLabel: function (line) { return NB.fmt(line.line_total); },
      inc: function (line) { NB.cart.set(line.id, line.qty + 1); },
      dec: function (line) { NB.cart.set(line.id, line.qty - 1); },
      remove: function (line) { NB.cart.remove(line.id); }
    });

    w.Alpine.store('search', {
      q: '',
      get results() {
        var term = (this.q || '').trim();
        var all = CFG.catalog || [];
        if (!term) return all;
        return all.filter(function (p) {
          return p.name.indexOf(term) > -1 || p.cat.indexOf(term) > -1;
        });
      },
      get noResults() { return (this.q || '').trim().length > 0 && this.results.length === 0; },
      priceLabel: function (p) { return NB.fmt(p.price); },
      add: function (p) { NB.cart.add(p.id, 1); }
    });

    w.Alpine.store('quick', {
      product: null,
      qty: 1,
      get name()  { return this.product ? this.product.name : ''; },
      get cat()   { return this.product ? this.product.cat : ''; },
      get desc()  { return this.product ? this.product.desc : ''; },
      get img()   { return this.product ? this.product.img : CFG.logo; },
      get url()   { return this.product ? this.product.url : '#'; },
      get priceLabel() { return this.product ? NB.fmt(this.product.price) : ''; },
      inc: function () { this.qty = Math.min(99, this.qty + 1); },
      dec: function () { this.qty = Math.max(1, this.qty - 1); },
      addToCart: function () {
        if (!this.product) return;
        NB.cart.add(this.product.id, this.qty);
        NB.closeQuick();
        setTimeout(NB.openCart, 380);
      }
    });
  });

  /* ---------- category shelf breakout ------------------------------------
     Copied verbatim from NABTA.dc.html. Each category behaves like a different
     physical object on the shelf.
     normal = how the closed pack settles back when the product leaves it.
     from -> peak -> settle = the opened product emerging, overshooting, then
     coming to rest. peak/settle are two keyframes of one tween, so the
     overshoot reads as weight rather than as a bounce easing. */
  var TILE_MOTION = {
    /* أعشاب — the container opens and the herbal mix lifts straight out of the shelf */
    masalek: {
      normal: {scale: .88, y: 14, rotation: -3.4, opacity: .06},
      from:   {scale: .70, y: 46, rotation: 6, x: 0},
      peak:   {scale: 1.22, y: -60, rotation: -3.8, x: 0, duration: .42},
      settle: {scale: 1.16, y: -50, rotation: -2.2, x: 0, duration: .32}
    },
    /* توابل — the sticks loosen and drift outward as they rise */
    cinnamon: {
      normal: {scale: .90, y: 10, rotation: 2.2, opacity: .06},
      from:   {scale: .76, y: 30, rotation: -6, x: 0},
      peak:   {scale: 1.20, y: -34, rotation: 5, x: 0, duration: .44},
      settle: {scale: 1.14, y: -27, rotation: 3.2, x: 0, duration: .34}
    },
    /* مكسرات — the pack bursts open and throws the nuts forward */
    macadamia: {
      normal: {scale: .86, y: 12, rotation: 2.6, opacity: .05},
      from:   {scale: .66, y: 36, rotation: -8, x: 0},
      peak:   {scale: 1.26, y: -46, rotation: 3, x: 0, duration: .36},
      settle: {scale: 1.17, y: -37, rotation: 1.6, x: 0, duration: .34}
    },
    /* بذور — the bowl tips and the seeds spill toward the viewer: settles lower, but larger */
    pumpkin: {
      normal: {scale: .90, y: 8, rotation: -3, opacity: .08},
      from:   {scale: .78, y: 24, rotation: 5, x: 0},
      peak:   {scale: 1.20, y: -34, rotation: -4, x: 0, duration: .40},
      settle: {scale: 1.26, y: -18, rotation: -2.2, x: 0, duration: .36}
    },
    /* عسل — the jar opens and pours diagonally out of the card */
    honey: {
      normal: {scale: .88, y: 8, rotation: 4, opacity: .07},
      from:   {scale: .74, y: 22, rotation: -6, x: 0},
      peak:   {scale: 1.19, y: -36, rotation: 4, x: 0, duration: .42},
      settle: {scale: 1.23, y: -22, rotation: 2.4, x: 0, duration: .38}
    }
  };

  /* One paused timeline per tile, built once: play() on enter, reverse() on leave. */
  function buildTileTimeline(gsap, tile, calm) {
    var normal = tile.querySelector('[data-tile-normal]');
    var opened = tile.querySelector('[data-tile-opened]');
    var bg = tile.querySelector('[data-tile-bg]');
    var arrow = tile.querySelector('[data-tile-arrow]');
    var float = tile.querySelector('[data-tile-float]');
    if (!normal) return null;
    var m = TILE_MOTION[tile.getAttribute('data-tile')];

    gsap.set(normal, {transformOrigin: '50% 94%'});
    if (float) gsap.set(float, {x: 0, y: 0, rotation: 0, transformOrigin: '50% 92%'});
    if (opened) {
      gsap.set(opened, {transformOrigin: '50% 88%'});
      gsap.set(opened, calm || !m ? {opacity: 0, scale: .94, y: 14, rotation: 0, x: 0} : Object.assign({opacity: 0}, m.from));
    }

    var tl = gsap.timeline({paused: true, defaults: {ease: 'power3.out'}});
    if (bg) tl.to(bg, {opacity: 1, duration: .5, ease: 'power2.out'}, 0);
    if (arrow) tl.to(arrow, {opacity: 1, x: -4, duration: .4, ease: 'power2.out'}, .06);

    /* no opened artwork, or motion dialled down: a plain, gentle lift */
    if (!opened || !m || calm) {
      if (opened) {
        tl.to(normal, {opacity: 0, duration: .3, ease: 'power2.inOut'}, .06)
          .to(opened, {opacity: 1, scale: 1.04, y: -12, duration: .45, ease: 'power2.out'}, 0);
      } else {
        tl.to(normal, {scale: 1.06, y: -16, duration: .5}, 0);
      }
      return tl;
    }

    /* the closed pack sinks back into the shelf and hands over ... */
    tl.to(normal, {scale: m.normal.scale, y: m.normal.y, rotation: m.normal.rotation, duration: .42}, 0)
      .to(normal, {opacity: m.normal.opacity, duration: .3, ease: 'power2.inOut'}, .12)
      /* ... to the opened product rising out from behind it */
      .to(opened, {opacity: 1, duration: .26, ease: 'power1.out'}, .05)
      .to(opened, {keyframes: [
        Object.assign({ease: 'power3.out'}, m.peak),
        Object.assign({ease: 'power2.inOut'}, m.settle)
      ]}, .05);
    return tl;
  }

  /* ---------- overlays ---------------------------------------------------
     Made visible with plain styles first, then animated — so they still open
     and close if the animation frame loop is throttled. */
  var lenis = null;
  var hideTimers = {};

  function q1(sel) { return d.querySelector(sel); }

  function show(sel, panelSel, v) {
    var g = w.gsap, root = q1(sel);
    if (!root) return;
    clearTimeout(hideTimers[sel]);
    root.style.pointerEvents = 'auto';
    root.setAttribute('aria-hidden', 'false');
    root.style.visibility = 'visible';
    root.style.opacity = '1';
    var scrim = root.querySelector('[data-drawer-scrim],[data-search-scrim],[data-quick-scrim]');
    if (scrim) scrim.style.opacity = '1';
    if (g && panelSel && v) {
      g.set(panelSel, v.to);
      g.fromTo(panelSel, v.from, Object.assign({immediateRender: false}, v.to, v.opts));
    }
    if (lenis) lenis.stop();
  }

  function hide(sel, panelSel, out) {
    var g = w.gsap, root = q1(sel);
    if (!root || root.getAttribute('aria-hidden') === 'true') return;
    root.style.pointerEvents = 'none';
    root.setAttribute('aria-hidden', 'true');
    if (g && panelSel && out) g.to(panelSel, out);
    if (g) g.to(root, {autoAlpha: 0, duration: .32, ease: 'power2.in', delay: .12});
    hideTimers[sel] = setTimeout(function () {
      root.style.visibility = 'hidden';
      root.style.opacity = '0';
    }, 540);
    if (lenis) lenis.start();
  }

  NB.openCart = function () {
    show('[data-drawer]', '[data-drawer-panel]', {from: {xPercent: -100}, to: {xPercent: 0}, opts: {duration: .65, ease: 'expo.out'}});
    setTimeout(function () { var b = q1('[data-drawer-panel] button'); if (b) b.focus(); }, 120);
  };
  NB.closeCart = function () { hide('[data-drawer]', '[data-drawer-panel]', {xPercent: -100, duration: .5, ease: 'power3.inOut'}); };
  NB.closeCartShop = function () { NB.closeCart(); setTimeout(function () { NB.jump('products'); }, 260); };
  NB.openCartFromMenu = function () { NB.closeMenu(); setTimeout(NB.openCart, 300); };

  NB.openSearch = function () {
    show('[data-search]', '[data-search-panel]', {from: {yPercent: -100}, to: {yPercent: 0}, opts: {duration: .6, ease: 'expo.out'}});
    setTimeout(function () { var i = q1('[data-search-input]'); if (i) i.focus(); }, 150);
  };
  NB.closeSearch = function () { hide('[data-search]', '[data-search-panel]', {yPercent: -100, duration: .5, ease: 'power3.inOut'}); };
  NB.openSearchFromMenu = function () { NB.closeMenu(); setTimeout(NB.openSearch, 300); };

  NB.openQuick = function (product) {
    if (w.Alpine) {
      var s = w.Alpine.store('quick');
      s.product = product;
      s.qty = 1;
    }
    show('[data-quick]', '[data-quick-panel]', {from: {scale: .94, opacity: 0, y: 20}, to: {scale: 1, opacity: 1, y: 0}, opts: {duration: .6, ease: 'expo.out'}});
  };
  NB.closeQuick = function () {
    hide('[data-quick]', '[data-quick-panel]', {scale: .95, opacity: 0, y: 16, duration: .35, ease: 'power2.in'});
    setTimeout(function () {
      if (w.Alpine) { w.Alpine.store('quick').product = null; w.Alpine.store('quick').qty = 1; }
    }, 460);
  };

  NB.openMenu = function () {
    show('[data-menu]', '[data-menu-item]', {from: {opacity: 0, y: 26}, to: {opacity: 1, y: 0}, opts: {duration: .7, stagger: .06, ease: 'power3.out'}});
  };
  NB.closeMenu = function () { hide('[data-menu]', '[data-menu-item]', {opacity: 0, y: 20, duration: .25}); };

  NB.jump = function (id) {
    var el = d.getElementById(id);
    if (!el) { w.location.href = '/#' + id; return; }
    var y = el.getBoundingClientRect().top + (w.pageYOffset || d.documentElement.scrollTop) - 70;
    if (lenis) lenis.scrollTo(y, {duration: 1.1}); else w.scrollTo({top: y, behavior: 'smooth'});
  };

  NB.navTo = function (e) {
    e.preventDefault();
    var id = e.currentTarget.dataset.nav;
    NB.closeMenu();
    setTimeout(function () { NB.jump(id); }, 260);
  };
  NB.goProducts = function () { NB.jump('products'); };
  NB.goCategories = function () { NB.jump('categories'); };

  d.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { NB.closeCart(); NB.closeSearch(); NB.closeQuick(); NB.closeMenu(); }
  });

  /* ---------- add to cart, with the fly-to-basket animation --------------- */
  function fly(srcImg) {
    var g = w.gsap, icon = q1('[data-cart-icon]');
    if (!g || !icon || !srcImg) return;
    var a = srcImg.getBoundingClientRect(), b = icon.getBoundingClientRect();
    if (!a.width) return;
    var c = srcImg.cloneNode(true);
    c.removeAttribute('data-cardimg');
    c.style.cssText = 'position:fixed;left:' + a.left + 'px;top:' + a.top + 'px;width:' + a.width + 'px;height:' + a.height + 'px;object-fit:contain;z-index:300;pointer-events:none;transform:none;margin:0;filter:drop-shadow(0 10px 20px rgba(0,0,0,.25))';
    d.body.appendChild(c);
    g.to(c, {
      x: (b.left + b.width / 2) - (a.left + a.width / 2),
      y: (b.top + b.height / 2) - (a.top + a.height / 2),
      scale: .1, opacity: 0, duration: .9, ease: 'power2.inOut', onComplete: function () { c.remove(); }
    });
    g.to(icon, {scale: 1.3, duration: .2, yoyo: true, repeat: 1, ease: 'power2.out', delay: .72});
  }

  var toastTimer;
  NB.toast = function (msg) {
    var el = q1('[data-toast]');
    if (!el) return;
    el.textContent = msg;
    el.setAttribute('data-show', 'true');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.setAttribute('data-show', 'false'); }, 2200);
  };

  function productById(id) {
    return (CFG.catalog || []).filter(function (x) { return String(x.id) === String(id); })[0] || null;
  }

  NB.onAdd = function (e) {
    var id = e.currentTarget.dataset.id;
    var card = e.currentTarget.closest('[data-card]') || e.currentTarget.closest('[data-bs]');
    var img = card && (card.querySelector('[data-cardimg]') || card.querySelector('img'));
    var qty = parseInt(e.currentTarget.dataset.qty || '1', 10) || 1;
    NB.cart.add(id, qty);
    /* The home page has the fly-to-basket animation; the inner pages have a
       toast. Whichever the page provides is the one that runs. */
    fly(img);
    var p = productById(id);
    NB.toast(p ? 'تمت الإضافة إلى السلة — ' + p.name : 'تمت الإضافة إلى السلة');
  };

  NB.onQuick = function (e) {
    var p = productById(e.currentTarget.dataset.id);
    if (p) NB.openQuick(p);
  };

  NB.onWish = function (e) {
    var b = e.currentTarget, path = b.querySelector('path');
    var on = b.dataset.on === '1';
    b.dataset.on = on ? '0' : '1';
    if (path) {
      path.setAttribute('fill', on ? 'none' : '#B08D57');
      path.setAttribute('stroke', on ? '#6B4630' : '#B08D57');
    }
    if (w.gsap) w.gsap.fromTo(b, {scale: .8}, {scale: 1, duration: .45, ease: 'back.out(3)'});
  };

  NB.onSubscribe = function (e) {
    e.preventDefault();
    if (e.currentTarget.reset) e.currentTarget.reset();
    var m = q1('[data-sub-msg]');
    if (m && w.gsap) w.gsap.fromTo(m, {opacity: 0, y: 6}, {opacity: 1, y: 0, duration: .5});
  };

  /* ---------- card + tile + magnet hover --------------------------------- */
  NB.cardEnter = function (e) {
    var g = w.gsap; if (!g) return;
    var c = e.currentTarget;
    g.to(c.querySelector('[data-cardimg]'), {y: -26, scale: 1.04, duration: .6, ease: 'power3.out'});
    g.to(c.querySelector('[data-cardbg]'), {opacity: 1, duration: .5});
    g.to(c.querySelector('[data-cardleaf]'), {opacity: .9, rotate: -8, duration: .6, ease: 'power2.out'});
    g.to(c.querySelector('[data-addbtn]'), {opacity: 1, y: 0, duration: .45, ease: 'power3.out'});
    g.to(c, {boxShadow: '0 30px 60px rgba(14,42,27,.16)', duration: .5});
  };
  NB.cardLeave = function (e) {
    var g = w.gsap; if (!g) return;
    var c = e.currentTarget;
    g.to(c.querySelector('[data-cardimg]'), {y: 0, scale: 1, duration: .6, ease: 'power3.out'});
    g.to(c.querySelector('[data-cardbg]'), {opacity: 0, duration: .5});
    g.to(c.querySelector('[data-cardleaf]'), {opacity: 0, rotate: 0, duration: .4});
    g.to(c.querySelector('[data-addbtn]'), {opacity: 0, y: 14, duration: .3});
    g.to(c, {boxShadow: '0 0 0 rgba(14,42,27,0)', duration: .5});
  };

  /* Reads the media queries live rather than a cached flag: initMotion runs
     before the first resize, and reading live also stays correct across one. */
  var mqDesktop = w.matchMedia('(min-width: 901px)');
  var mqHover = w.matchMedia('(hover: hover) and (pointer: fine)');
  function canTileHover() { return mqDesktop.matches && mqHover.matches; }

  NB.tileEnter = function (e) {
    var t = e.currentTarget;
    if (!canTileHover() || !t._catTimeline) return;
    t._isHovered = true;
    clearTimeout(t._zTimer);
    t.style.zIndex = '30';
    t._catTimeline.timeScale(1).play();
  };
  NB.tileLeave = function (e) {
    var t = e.currentTarget;
    if (!t._isHovered) return;
    t._isHovered = false;
    /* leaving is the same timeline run backwards, a touch quicker */
    if (t._catTimeline) t._catTimeline.timeScale(1.32).reverse();
    if (t._floatTo) { t._floatTo.x(0); t._floatTo.y(0); t._floatTo.r(0); }
    clearTimeout(t._zTimer);
    t._zTimer = setTimeout(function () { if (!t._isHovered) t.style.zIndex = ''; }, 700);
  };
  NB.tileMove = function (e) {
    var t = e.currentTarget;
    if (!t._isHovered || !t._floatTo) return;
    var r = t.getBoundingClientRect();
    var nx = (e.clientX - r.left - r.width / 2) / (r.width / 2);
    var ny = (e.clientY - r.top - r.height / 2) / (r.height / 2);
    /* depth, not cursor-following: keep this barely perceptible */
    t._floatTo.x(nx * 5);
    t._floatTo.y(ny * 3);
    t._floatTo.r(nx * 1.1);
  };

  NB.magMove = function (e) {
    var g = w.gsap; if (!g || !mqDesktop.matches) return;
    var b = e.currentTarget, r = b.getBoundingClientRect();
    g.to(b, {x: (e.clientX - r.left - r.width / 2) * .18, y: (e.clientY - r.top - r.height / 2) * .3, duration: .5, ease: 'power2.out'});
  };
  NB.magLeave = function (e) {
    var g = w.gsap; if (!g) return;
    g.to(e.currentTarget, {x: 0, y: 0, duration: .6, ease: 'elastic.out(1,.5)'});
  };

  /* ---------- home page motion ------------------------------------------- */
  var tiles = null, tileObs = null, tileRevealObs = null;

  function initTiles(gsap, calm) {
    tiles = gsap.utils.toArray('[data-tile]');
    tiles.forEach(function (t) {
      t._catTimeline = buildTileTimeline(gsap, t, calm);
      var float = t.querySelector('[data-tile-float]');
      /* the parallax lives on a wrapper so it can never fight the timeline's own
         transform on the image, which is what makes a reverse jump */
      t._floatTo = float ? {
        x: gsap.quickTo(float, 'x', {duration: .5, ease: 'power2.out'}),
        y: gsap.quickTo(float, 'y', {duration: .5, ease: 'power2.out'}),
        r: gsap.quickTo(float, 'rotation', {duration: .6, ease: 'power2.out'})
      } : null;
    });
    preloadTileArt();
    initTileScrollReveal();
  }

  /* Touch devices have no hover, so the very same breakout timeline is driven
     by scroll position instead: a tile opens when it settles into the middle
     band of the viewport and closes when it leaves. Tapping still navigates —
     the gesture is never hijacked. */
  function initTileScrollReveal() {
    if (!w.IntersectionObserver || !tiles || !tiles.length) return;
    tileRevealObs = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        var t = en.target;
        if (!t._catTimeline || canTileHover()) return; // hover drives it instead
        if (en.isIntersecting && !t._revealed) {
          t._revealed = true;
          clearTimeout(t._zTimer);
          t.style.zIndex = '30';
          t._catTimeline.timeScale(1).play();
        } else if (!en.isIntersecting && t._revealed) {
          t._revealed = false;
          t._catTimeline.timeScale(1.32).reverse();
          clearTimeout(t._zTimer);
          t._zTimer = setTimeout(function () { if (!t._revealed) t.style.zIndex = ''; }, 700);
        }
      });
    }, { rootMargin: '-32% 0px -32% 0px', threshold: 0 });
    tiles.forEach(function (t) { tileRevealObs.observe(t); });
  }

  /* the opened artwork is heavy, so warm it just before the shelf reaches the
     viewport: the first hover then paints with no fetch and no decode */
  function preloadTileArt() {
    var srcs = [].map.call(d.querySelectorAll('[data-tile-opened]'), function (i) {
      return i.getAttribute('src');
    }).filter(Boolean);
    if (!srcs.length) return;
    var done = false;
    function run() {
      if (done) return;
      done = true;
      srcs.forEach(function (src) {
        var img = new Image();
        img.decoding = 'async';
        img.src = src;
        if (img.decode) img.decode().catch(function () {});
      });
    }
    var sec = d.getElementById('categories');
    if (sec && w.IntersectionObserver) {
      tileObs = new IntersectionObserver(function (entries, obs) {
        if (entries.some(function (en) { return en.isIntersecting; })) { run(); obs.disconnect(); tileObs = null; }
      }, {rootMargin: '900px 0px'});
      tileObs.observe(sec);
    } else {
      setTimeout(run, 1800);
    }
  }

  function initMotion() {
    var gsap = w.gsap, ST = w.ScrollTrigger;
    gsap.registerPlugin(ST);
    var reduce = w.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var calm = reduce;

    if (!reduce && w.Lenis) {
      var L = w.Lenis.default || w.Lenis;
      try {
        lenis = new L({duration: 1.15, smoothWheel: true, wheelMultiplier: .9});
        lenis.on('scroll', ST.update);
        // Guarded: the resilience timer below can null lenis out, and this
        // callback keeps running on every frame after that.
        gsap.ticker.add(function (t) { if (lenis) lenis.raf(t * 1000); });
        gsap.ticker.lagSmoothing(0);
      } catch (e) {}
    }

    gsap.set(['[data-drawer-panel]'], {xPercent: -100});
    gsap.set(['[data-search-panel]'], {yPercent: -100});
    gsap.set(['[data-quick-panel]'], {scale: .94, opacity: 0, y: 20});
    gsap.set(['[data-menu-item]'], {opacity: 0, y: 26});

    var isHome = !!q1('[data-hero]');

    /* preloader + hero */
    var showPre = !reduce && !!q1('[data-pre]');
    var tl = gsap.timeline();
    if (showPre) {
      tl.to('[data-pre-logo]', {opacity: 1, scale: 1, duration: .7, ease: 'power3.out'})
        .to('[data-pre-line]', {scaleX: 1, duration: .55, ease: 'power2.inOut'}, '-=.28')
        .to('[data-pre]', {autoAlpha: 0, duration: .55, ease: 'power2.inOut'}, '+=.1')
        .set('[data-pre]', {display: 'none'});
    } else if (q1('[data-pre]')) {
      tl.set('[data-pre]', {display: 'none', autoAlpha: 0});
    }

    if (isHome) {
      var t0 = showPre ? 1.0 : 0;
      tl.fromTo('[data-hero-bg]', {scale: 1.06, opacity: 0.7}, {scale: 1, opacity: 1, duration: 2, ease: 'power2.out'}, t0)
        .fromTo('[data-hero-line-1]', {autoAlpha: 0, y: 32}, {autoAlpha: 1, y: 0, duration: 1, ease: 'power3.out'}, t0 + .2)
        .fromTo('[data-hero-line-2]', {autoAlpha: 0, y: 32}, {autoAlpha: 1, y: 0, duration: 1, ease: 'power3.out'}, t0 + .35)
        .fromTo('[data-hero-divider]', {autoAlpha: 0, scaleX: 0}, {autoAlpha: 1, scaleX: 1, duration: .85, ease: 'power2.out'}, t0 + .5)
        .fromTo('[data-hero-sub]', {autoAlpha: 0, y: 20}, {autoAlpha: 1, y: 0, duration: .85, ease: 'power3.out'}, t0 + .65)
        .fromTo('[data-hero-cta] > *', {autoAlpha: 0, y: 16}, {autoAlpha: 1, y: 0, duration: .7, stagger: .1, ease: 'power3.out'}, t0 + .8)
        .fromTo('[data-hero-badge]', {autoAlpha: 0, scale: .7}, {autoAlpha: 1, scale: 1, duration: 1.1, ease: 'back.out(1.8)'}, t0 + .7)
        .fromTo('[data-hero-dots]', {autoAlpha: 0, y: 12}, {autoAlpha: 1, y: 0, duration: .7, ease: 'power2.out'}, t0 + .95);
    }
    /* if rAF was throttled (hidden tab) the intro can stall — force it home */
    setTimeout(function () { if (tl.progress() < 1) tl.progress(1); }, 2500);

    /* header */
    if (q1('[data-headerbg]')) {
      ST.create({
        start: 'top -50', end: 99999,
        onToggle: function (self) {
          var on = self.isActive, bg = q1('[data-headerbg]');
          if (bg) bg.style.backdropFilter = on ? 'blur(16px)' : 'blur(0px)';
          gsap.to('[data-headerbg]', {backgroundColor: on ? 'rgba(252,250,245,.95)' : 'rgba(252,250,245,0)', borderBottomColor: on ? 'rgba(14,42,27,.10)' : 'rgba(14,42,27,0)', duration: .4, ease: 'power2.out'});
          gsap.to('[data-hbar]', {height: on ? 68 : 88, duration: .4, ease: 'power2.out'});
        }
      });
    }

    /* mouse parallax on hero */
    var hero = q1('[data-hero]');
    if (hero && !calm && mqDesktop.matches) {
      hero.addEventListener('mousemove', function (e) {
        var r = hero.getBoundingClientRect();
        var cx = (e.clientX - r.width / 2) / r.width, cy = (e.clientY - r.height / 2) / r.height;
        gsap.to('[data-hero-badge]', {x: cx * -20, y: cy * -15, duration: 1.4, ease: 'power2.out', overwrite: 'auto'});
      });
    }

    /* generic reveals */
    gsap.utils.toArray('[data-reveal]').forEach(function (el) {
      gsap.from(el, {autoAlpha: 0, y: 42, duration: 1.05, ease: 'power3.out', scrollTrigger: {trigger: el, start: 'top 86%'}});
    });
    gsap.utils.toArray('[data-word-line]').forEach(function (el, i) {
      gsap.from(el.firstElementChild, {yPercent: 112, duration: 1.2, delay: i * .1, ease: 'expo.out', scrollTrigger: {trigger: el, start: 'top 88%'}});
    });
    gsap.utils.toArray('[data-tile]').forEach(function (el, i) {
      gsap.from(el, {autoAlpha: 0, y: 54, duration: 1, delay: (i % 4) * .08, ease: 'power3.out', scrollTrigger: {trigger: el, start: 'top 92%'}});
    });
    initTiles(gsap, calm);
    if (q1('[data-pin]')) {
      gsap.utils.toArray('[data-bs]').forEach(function (el) {
        gsap.from(el, {autoAlpha: 0, y: 40, duration: 1, ease: 'power3.out', scrollTrigger: {trigger: '[data-pin]', start: 'top 70%'}});
      });
    }

    /* film section */
    var filmVideo = q1('[data-film-video]');
    if (filmVideo) {
      try { filmVideo.pause(); filmVideo.currentTime = 0; } catch (e) {}
      ST.create({
        trigger: '[data-film-sec]',
        start: 'top 75%',
        end: 'bottom 20%',
        onEnter: function () { filmVideo.play().catch(function () {}); },
        onEnterBack: function () { filmVideo.play().catch(function () {}); },
        onLeave: function () { filmVideo.pause(); },
        onLeaveBack: function () { filmVideo.pause(); }
      });

      if (!calm) {
        gsap.fromTo('[data-film]', {scale: .9}, {scale: 1, ease: 'none', scrollTrigger: {trigger: '[data-film-sec]', start: 'top bottom', end: 'top 22%', scrub: true}});
        gsap.fromTo('[data-film-video]', {yPercent: -6}, {yPercent: 4, ease: 'none', scrollTrigger: {trigger: '[data-film-sec]', start: 'top bottom', end: 'bottom top', scrub: true}});
        gsap.from('[data-film-text] > *', {autoAlpha: 0, y: 34, duration: 1, stagger: .12, ease: 'power3.out', scrollTrigger: {trigger: '[data-film]', start: 'top 55%'}});
      }
    }

    /* horizontal best sellers */
    var track = q1('[data-track]'), stage = q1('[data-pin]'), wrap = q1('[data-track-wrap]');
    if (track && stage && wrap) {
      if (!calm && mqDesktop.matches) {
        var amount = function () { return Math.max(0, track.scrollWidth - wrap.clientWidth + 24); };
        gsap.to(track, {
          x: amount, ease: 'none',
          scrollTrigger: {trigger: stage, start: 'top top', end: function () { return '+=' + (amount() + 200); }, pin: true, scrub: 1, invalidateOnRefresh: true, anticipatePin: 1}
        });
      } else {
        wrap.style.overflowX = 'auto';
        wrap.style.webkitOverflowScrolling = 'touch';
      }
    }

    /* swiper */
    if (w.Swiper && q1('[data-swiper]')) {
      try {
        new w.Swiper('[data-swiper]', {
          slidesPerView: 1.18, spaceBetween: 16, grabCursor: true, watchOverflow: true,
          breakpoints: {620: {slidesPerView: 2.15, spaceBetween: 20}, 900: {slidesPerView: 3, spaceBetween: 24}, 1280: {slidesPerView: 4, spaceBetween: 28}},
          navigation: {nextEl: '[data-sw-next]', prevEl: '[data-sw-prev]'}
        });
      } catch (e) {}
    }
    setTimeout(function () { ST.refresh(); }, 600);

    /* Resilience: if requestAnimationFrame never ticks (hidden tab, throttled
       embed), land every reveal on its final state and hand scrolling back. */
    setTimeout(function () {
      if (gsap.ticker.frame > 20) return;
      if (lenis) { try { lenis.destroy(); } catch (e) {} lenis = null; }
      if (tl.progress() < 1) tl.progress(1);
      ST.getAll().forEach(function (t) { if (t.animation) { try { t.animation.progress(1); } catch (e) {} } });
    }, 6000);
  }

  /* Motion is optional: the page stays fully usable if GSAP never arrives. */
  function waitLibs(n) {
    if (w.gsap && w.ScrollTrigger) { initMotion(); return; }
    if (n > 120) { var pre = q1('[data-pre]'); if (pre) pre.style.display = 'none'; return; }
    setTimeout(function () { waitLibs(n + 1); }, 70);
  }

  function boot() {
    /* never let the preloader trap the page (e.g. rAF throttled in a hidden tab) */
    setTimeout(function () {
      var p = q1('[data-pre]');
      if (p && p.style.display !== 'none') { p.style.display = 'none'; p.style.visibility = 'hidden'; }
    }, 3200);

    if (d.querySelector('[data-motion]')) waitLibs(0);

    /* The cart lives in the session, so another tab — or a back/forward-cache
       restore — can leave this page's copy stale. Re-read it when the tab shows. */
    d.addEventListener('visibilitychange', function () {
      if (!d.hidden) NB.cart.refresh().catch(function () {});
    });
  }

  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', boot);
  else boot();

})(window, document);
