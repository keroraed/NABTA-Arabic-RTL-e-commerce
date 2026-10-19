@extends('layouts.pos')
@section('title', 'فتح وردية')

@section('body')
<div class="pos-centre">
  <form class="pos-card" method="POST" action="{{ route('pos.shift.open.store') }}">
    @csrf
    <h1>فتح وردية</h1>
    <p class="sub">{{ $terminal->code }} — {{ $terminal->name }}. لا يمكن تسجيل أي فاتورة قبل فتح وردية، حتى تظل كل مبيعة قابلة للمراجعة في نهاية اليوم.</p>

    @if (session('status'))
      <p class="flash">{{ session('status') }}</p>
    @endif

    <div class="field">
      <label for="opening_float">النقدية الافتتاحية في الدرج</label>
      <input id="opening_float" name="opening_float" type="number" step="0.01" min="0" value="{{ old('opening_float', 0) }}" required autofocus>
      @error('opening_float')<span style="color:#B04A4A;font-size:13px">{{ $message }}</span>@enderror
    </div>

    <div class="modal-actions">
      <button type="submit" class="btn-confirm">بدء الوردية</button>
      <a class="btn-cancel" href="{{ route('pos.terminal') }}" style="text-align:center;line-height:26px;text-decoration:none">تغيير الكاشير</a>
    </div>
  </form>
</div>
@endsection
