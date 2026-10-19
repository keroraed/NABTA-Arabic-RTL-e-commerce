@extends('layouts.pos')
@section('title', 'اختيار الكاشير')

@section('body')
<div class="pos-centre">
  <form class="pos-card" method="POST" action="{{ route('pos.terminal.store') }}">
    @csrf
    <h1>اختر الكاشير</h1>
    <p class="sub">يُحفظ الاختيار على هذا الجهاز، فلن تحتاج لتكراره في كل مرة. رقم الكاشير يظهر في أرقام الفواتير.</p>

    @if ($terminals->isEmpty())
      <p class="pos-alert">لا توجد أجهزة كاشير مفعّلة. أضف واحداً من لوحة التحكم أولاً.</p>
    @else
      <div class="terminal-list">
        @foreach ($terminals as $terminal)
          <label class="terminal-option">
            <input type="radio" name="pos_terminal_id" value="{{ $terminal->id }}" @checked($loop->first) required>
            <span>
              <span class="code">{{ $terminal->code }} — {{ $terminal->name }}</span><br>
              <span class="where">المخزن: {{ $terminal->warehouse?->name }}</span>
            </span>
          </label>
        @endforeach
      </div>

      <div class="modal-actions">
        <button type="submit" class="btn-confirm">متابعة</button>
      </div>
    @endif
  </form>
</div>
@endsection
