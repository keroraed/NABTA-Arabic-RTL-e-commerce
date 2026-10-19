@extends('layouts.report')

@section('title', 'حركة الخزينة')
@section('subtitle')
  @if ($from || $to)
    من {{ $from?->format('Y/m/d') ?? '—' }} إلى {{ $to?->format('Y/m/d') ?? '—' }}
  @else
    كل الفترات
  @endif
@endsection

@section('content')
  <form class="filters no-print" method="GET">
    <div>
      <label for="from">من تاريخ</label>
      <input id="from" type="date" name="from" value="{{ $from?->format('Y-m-d') }}">
    </div>
    <div>
      <label for="to">إلى تاريخ</label>
      <input id="to" type="date" name="to" value="{{ $to?->format('Y-m-d') }}">
    </div>
    <button type="submit">عرض</button>
  </form>

  @foreach ($accounts as $entry)
    @php($report = $entry['report'])
    <h2 style="font-size:19px;margin:26px 0 12px">{{ $entry['account']->name }}</h2>

    {{-- The reconciliation is the point of this report: if opening + in - out
         does not equal closing, something wrote to the table outside Treasury
         and the number cannot be trusted. Say so, loudly. --}}
    @if (! $report['reconciles'])
      <p class="warn">⚠ الخزينة لا تتوازن: الرصيد الافتتاحي + الوارد − المنصرف لا يساوي الرصيد الختامي. راجع الحركات.</p>
    @else
      <p class="ok">✔ الخزينة متوازنة: {{ number_format($report['opening'], 2) }} + {{ number_format($report['in'], 2) }} − {{ number_format($report['out'], 2) }} = {{ number_format($report['closing'], 2) }}</p>
    @endif

    <table>
      <thead>
        <tr>
          <th style="width:140px">التاريخ</th>
          <th>البيان</th>
          <th class="num" style="width:120px">وارد</th>
          <th class="num" style="width:120px">منصرف</th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="3"><strong>رصيد افتتاحي</strong></td><td class="num"><strong>{{ number_format($report['opening'], 2) }}</strong></td></tr>

        @forelse ($entry['movements'] as $movement)
          <tr>
            <td>{{ $movement->occurred_at->format('Y/m/d H:i') }}</td>
            <td>{{ $movement->description }}</td>
            <td class="num pos">{{ $movement->direction === 'in' ? number_format($movement->amount, 2) : '—' }}</td>
            <td class="num neg">{{ $movement->direction === 'out' ? number_format($movement->amount, 2) : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="4" style="text-align:center;padding:24px">لا توجد حركات في هذه الفترة.</td></tr>
        @endforelse

        <tr class="total">
          <td colspan="2">الرصيد الختامي</td>
          <td class="num">{{ number_format($report['in'], 2) }}</td>
          <td class="num">{{ number_format($report['out'], 2) }}</td>
        </tr>
        <tr class="total">
          <td colspan="3">الرصيد النهائي</td>
          <td class="num">{{ number_format($report['closing'], 2) }} ج.م</td>
        </tr>
      </tbody>
    </table>
  @endforeach
@endsection
