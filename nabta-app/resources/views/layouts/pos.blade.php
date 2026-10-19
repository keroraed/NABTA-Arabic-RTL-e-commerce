{{--
  POS shell. Deliberately spare: this runs full-screen on a shop PC, is read at
  arm's length across a counter, and is driven mostly by a barcode scanner.
  Large type, high contrast, no decoration.
--}}
<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex">
  <title>@yield('title', 'نقطة البيع') — نبته</title>

  <link rel="icon" href="{{ asset('assets/logo.webp') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/pos.css') }}?v={{ filemtime(public_path('css/pos.css')) }}">
  @stack('head')

  <script>
    window.POS = @json($posConfig ?? []);
  </script>
  @stack('scripts')
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
</head>
<body>
  @yield('body')
</body>
</html>
