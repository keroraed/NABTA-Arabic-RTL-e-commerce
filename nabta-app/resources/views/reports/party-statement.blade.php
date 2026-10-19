@extends('layouts.report')

@section('title', 'كشف حساب — ' . $party->name)
@section('subtitle')
  {{ \App\Models\Party::TYPE_LABELS[$party->type] ?? $party->type }}
  @if ($party->phone) · {{ $party->phone }} @endif
  @if ($from || $to)
    · من {{ $from?->format('Y/m/d') ?? '—' }} إلى {{ $to?->format('Y/m/d') ?? '—' }}
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

  <table>
    <thead>
      <tr>
        <th style="width:110px">التاريخ</th>
        <th>البيان</th>
        <th class="num" style="width:120px">مدين</th>
        <th class="num" style="width:120px">دائن</th>
        <th class="num" style="width:140px">الرصيد</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td colspan="4"><strong>رصيد افتتاحي</strong></td>
        <td class="num"><strong>{{ number_format($statement['opening'], 2) }}</strong></td>
      </tr>

      @forelse ($statement['rows'] as $row)
        <tr>
          <td>{{ $row['date']->format('Y/m/d') }}</td>
          <td>{{ $row['description'] }}</td>
          <td class="num">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '—' }}</td>
          <td class="num">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '—' }}</td>
          <td class="num">{{ number_format($row['balance'], 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="5" style="text-align:center;padding:30px">لا توجد حركات في هذه الفترة.</td></tr>
      @endforelse

      <tr class="total">
        <td colspan="2">الرصيد الختامي</td>
        <td class="num">{{ number_format(collect($statement['rows'])->sum('debit'), 2) }}</td>
        <td class="num">{{ number_format(collect($statement['rows'])->sum('credit'), 2) }}</td>
        <td class="num {{ $statement['closing'] > 0 ? 'neg' : 'pos' }}">
          {{ number_format($statement['closing'], 2) }} ج.م
        </td>
      </tr>
    </tbody>
  </table>

  {{-- The convention is stated on the page itself, because a statement whose
       sign convention is ambiguous is worse than no statement. --}}
  <p class="muted" style="margin-top:18px">
    @if ($party->isSupplier())
      رصيد موجب يعني مبلغ مستحق <strong>للمورد علينا</strong> (دائن − مدين).
    @else
      رصيد موجب يعني مبلغ مستحق <strong>على العميل لنا</strong> (مدين − دائن).
    @endif
  </p>
@endsection
