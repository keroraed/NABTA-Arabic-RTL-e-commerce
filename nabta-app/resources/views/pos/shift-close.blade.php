@extends('layouts.pos')
@section('title', 'إغلاق وردية')

@section('body')
<div class="pos-centre">
  <form class="pos-card" method="POST" action="{{ route('pos.shift.close.store') }}"
        x-data="{ counted: '', expected: {{ $expected }} }">
    @csrf
    <h1>إغلاق الوردية</h1>
    <p class="sub">{{ $terminal->code }} — بدأت {{ $shift->opened_at->format('Y/m/d H:i') }}</p>

    <div class="summary-row"><span>عدد الفواتير</span><span>{{ $salesCount }}</span></div>
    <div class="summary-row"><span>النقدية الافتتاحية</span><span>{{ number_format($shift->opening_float, 2) }} ج.م</span></div>
    <div class="summary-row total"><span>المتوقع في الدرج</span><span>{{ number_format($expected, 2) }} ج.م</span></div>

    <div class="field" style="margin-top:22px">
      <label for="counted_cash">النقدية المعدودة فعلياً</label>
      <input id="counted_cash" name="counted_cash" type="number" step="0.01" min="0"
             x-model="counted" required autofocus>
    </div>

    {{-- The variance is shown before submitting so a miscount is caught at the
         drawer rather than argued about the next morning. --}}
    <template x-if="counted !== ''">
      <p class="summary-row">
        <span>الفرق</span>
        <span :class="(counted - expected) < 0 ? 'variance-short' : 'variance-over'"
              x-text="(counted - expected).toFixed(2) + ' ج.م'"></span>
      </p>
    </template>

    <div class="field">
      <label for="notes">ملاحظات (اختياري)</label>
      <textarea id="notes" name="notes" rows="2"></textarea>
    </div>

    <div class="modal-actions">
      <button type="submit" class="btn-confirm">إغلاق الوردية</button>
      <a class="btn-cancel" href="{{ route('pos.index') }}" style="text-align:center;line-height:26px;text-decoration:none">رجوع</a>
    </div>
  </form>
</div>
@endsection
