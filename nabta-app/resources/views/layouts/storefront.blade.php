{{--
  Storefront shell.

  Fonts and motion libraries are loaded from the same CDNs the static prototype
  used, and the ported design lives in public/css/nabta.css rather than going
  through Vite. That keeps the storefront a pure Blade deploy with no build step
  — which is what the Hostinger shared-hosting target wants.
--}}
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', 'نبته NABTA')</title>
  <meta name="description" content="@yield('description', 'منتجات طبيعية مختارة بعناية، من العطارة الأصيلة بروح عصرية. الدفع عند الاستلام في جميع المحافظات.')">
  <link rel="canonical" href="{{ url()->current() }}">

  <meta property="og:type" content="@yield('og_type', 'website')">
  <meta property="og:site_name" content="نبته NABTA">
  <meta property="og:locale" content="ar_EG">
  <meta property="og:title" content="@yield('title', 'نبته NABTA')">
  <meta property="og:description" content="@yield('description', 'منتجات طبيعية مختارة بعناية، من العطارة الأصيلة بروح عصرية.')">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:image" content="@yield('og_image', asset('assets/logo.webp'))">

  <link rel="icon" href="{{ asset('assets/logo.webp') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/nabta.css') }}?v={{ filemtime(public_path('css/nabta.css')) }}">
  @stack('head')

  <script>
    window.NABTA = {
      routes: {
        cart:     @json(route('cart.state')),
        cartAdd:  @json(route('cart.add', ['product' => '__ID__'])),
        cartItem: @json(route('cart.update', ['product' => '__ID__'])),
        checkout: @json(route('checkout')),
        shop:     @json(route('shop')),
        product:  @json(route('products.show', ['product' => '__SLUG__'])),
      },
      csrf: @json(csrf_token()),
      cart:    @json($__cartState ?? ['count' => 0, 'subtotal' => 0, 'lines' => []]),
      catalog: @json($__catalog ?? []),
      logo:    @json(asset('assets/logo.webp')),
    };
  </script>
  {{-- nabta.js must load BEFORE Alpine: it registers its stores on the
       alpine:init event, which the CDN build fires as soon as it evaluates. --}}
  <script defer src="{{ asset('js/nabta.js') }}?v={{ filemtime(public_path('js/nabta.js')) }}"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
</head>
{{-- x-data on the body is load-bearing, not decoration: Alpine.start() only
     walks trees rooted at an x-data, so without it the cart badge, the cart
     drawer, search and quick-view render as empty elements. --}}
<body x-data>
  @yield('body')
  @stack('scripts')
</body>
</html>
