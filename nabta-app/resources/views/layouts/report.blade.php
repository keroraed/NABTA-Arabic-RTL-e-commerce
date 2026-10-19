{{-- Printable report shell. Print stylesheet is the delivery mechanism: the
     shop prints these and files them, so screen chrome disappears on paper. --}}
<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>@yield('title', 'تقرير') — نبته</title>
  <link rel="icon" href="{{ asset('assets/logo.webp') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; background: #F5F3EE; color: #0E2A1B;
           font-family: 'IBM Plex Sans Arabic', system-ui, sans-serif; }
    .sheet { max-width: 1000px; margin: 0 auto; padding: 28px; background: #fff; min-height: 100vh; }
    .head { display: flex; align-items: center; justify-content: space-between;
            gap: 20px; padding-bottom: 18px; border-bottom: 2px solid #0E2A1B; margin-bottom: 22px; }
    .head img { height: 46px; width: auto; }
    h1 { margin: 0 0 4px; font-size: 24px; font-weight: 700; }
    .muted { color: #6B4630; font-size: 13.5px; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { padding: 10px 8px; border-bottom: 1px solid rgba(14,42,27,.12); text-align: start; }
    th { background: #F5EBD8; font-weight: 700; font-size: 13px; }
    td.num, th.num { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
    tr.total td { font-weight: 700; border-top: 2px solid #0E2A1B; border-bottom: 0; font-size: 15px; }
    .filters { display: flex; gap: 10px; align-items: end; margin-bottom: 20px; flex-wrap: wrap; }
    .filters label { display: block; font-size: 12.5px; color: #6B4630; margin-bottom: 4px; }
    .filters input { padding: 9px 10px; border: 1px solid rgba(14,42,27,.2); border-radius: 4px; font-family: inherit; }
    .filters button { padding: 10px 20px; border: 0; border-radius: 4px; background: #0E2A1B;
                      color: #F5EBD8; font-weight: 600; cursor: pointer; font-family: inherit; }
    .warn { background: #FFF2F2; border: 1px solid #E9C7C7; color: #B04A4A;
            padding: 12px 14px; border-radius: 4px; font-weight: 600; margin-bottom: 16px; }
    .ok { background: rgba(23,107,58,.08); border: 1px solid rgba(23,107,58,.3); color: #176B3A;
          padding: 12px 14px; border-radius: 4px; margin-bottom: 16px; font-size: 13.5px; }
    .neg { color: #B04A4A; }
    .pos { color: #176B3A; }
    @media print {
      body { background: #fff; }
      .no-print { display: none !important; }
      .sheet { max-width: none; padding: 0; }
    }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="head">
      <div>
        <h1>@yield('title', 'تقرير')</h1>
        <p class="muted" style="margin:0">@yield('subtitle')</p>
      </div>
      <img src="{{ asset('assets/logo.webp') }}" alt="نبته">
    </div>

    @yield('content')

    <p class="muted no-print" style="margin-top:28px">
      <button onclick="window.print()" style="padding:10px 22px;border:0;border-radius:4px;background:#176B3A;color:#fff;font-weight:600;cursor:pointer;font-family:inherit">طباعة</button>
    </p>
  </div>
</body>
</html>
