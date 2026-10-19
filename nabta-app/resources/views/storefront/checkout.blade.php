{{-- Ported from checkout.html.

     The prototype computed subtotal, shipping and total in the browser and posted
     them (HANDOFF.md 2.3). Here the numbers below are a live preview only: the
     server recomputes every one of them from its own catalogue when the order is
     placed, and the form posts nothing but ids, quantities and the address. --}}
@extends('layouts.storefront')

@section('title', 'إتمام الطلب | نبته NABTA')
@section('description', 'أكمل طلبك من نبته — الدفع عند الاستلام أو إلكترونياً في جميع المحافظات، بدون إنشاء حساب.')

@push('head')
  <meta name="robots" content="noindex">
@endpush

@section('body')

  @include('partials.chrome-header', ['active' => 'shop'])

  {{-- pageshow: coming back from Paymob with the back button restores this
       page from the bfcache with `submitting` still true. --}}
  <main class="co-page" style="padding-top:76px"
        @pageshow.window="if ($event.persisted) submitting = false"
        x-data="{
          gov: '{{ old('governorate_id') }}',
          payment: @js(old('payment_method', 'cod')),
          submitting: false,
          fees: {{ Js::from($governorates->pluck('shipping_fee', 'id')->map(fn ($f) => (float) $f)) }},
          threshold: {{ $freeShippingThreshold }},
          get subtotal() { return $store.cart.subtotal; },
          get qualifies() { return this.subtotal >= this.threshold; },
          get shipping() { return this.qualifies ? 0 : (this.fees[this.gov] ?? 0); },
          get total() { return this.subtotal + (this.gov ? this.shipping : 0); },
          fmt(n) { return NB.fmt(n); },
          get shippingLabel() {
            if (!this.gov) return 'يُحسب بعد اختيار المحافظة';
            return this.shipping === 0 ? 'مجاني' : this.fmt(this.shipping);
          },
          get freeHint() {
            const remain = this.threshold - this.subtotal;
            return remain > 0 ? 'أضف بقيمة ' + this.fmt(remain) + ' للحصول على شحن مجاني.' : 'مبروك! حصلت على شحن مجاني.';
          }
        }">
    <section style="max-width:1280px;margin:0 auto;padding:clamp(30px,5vw,56px) clamp(16px,4vw,44px) clamp(60px,8vw,100px)">

      <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#6B4630;margin-bottom:16px">
        <a href="{{ route('shop') }}" style="opacity:.8;color:inherit">المنتجات</a><span>/</span><span style="color:#176B3A;font-weight:600">إتمام الطلب</span>
      </div>
      <h1 style="margin:0 0 8px;font-size:clamp(1.7rem,3.6vw,2.6rem);font-weight:700;letter-spacing:-.02em">إتمام الطلب</h1>
      <p style="margin:0 0 clamp(28px,4vw,44px);font-size:15px;line-height:1.85;color:#3A483D;font-weight:300">
        لا تحتاج إلى إنشاء حساب — املأ بياناتك وسنتواصل معك لتأكيد الطلب.
        {{ $onlinePaymentAvailable ? 'ادفع عند الاستلام أو إلكترونياً بالبطاقة أو المحفظة.' : 'الدفع عند الاستلام.' }}
      </p>

      @if (session('cart_error'))
        <p style="margin:0 0 24px;padding:14px 16px;background:#FFF2F2;border:1px solid #E9C7C7;border-radius:3px;font-size:14px;color:#B04A4A;line-height:1.7">{{ session('cart_error') }}</p>
      @endif

      <template x-if="$store.cart.isEmpty">
        <div style="text-align:center;padding:clamp(50px,9vw,110px) 20px;background:#F5EBD8;border-radius:4px">
          <p style="margin:0 0 10px;font-size:19px;font-weight:600">سلتك فارغة</p>
          <p style="margin:0 0 28px;font-size:15px;color:#3A483D;font-weight:300">أضف بعض المنتجات قبل إتمام الطلب.</p>
          <a href="{{ route('shop') }}" style="display:inline-block;background:#0E2A1B;color:#F5EBD8;font-size:15px;font-weight:600;padding:15px 38px;border-radius:2px">تصفّح المنتجات</a>
        </div>
      </template>

      <form method="POST" action="{{ route('checkout.store') }}" x-show="!$store.cart.isEmpty" x-cloak class="co-grid" style="grid-template-columns:1.35fr .85fr;gap:clamp(22px,3vw,44px);align-items:start"
            @submit="if (submitting) { $event.preventDefault(); } submitting = true">
        @csrf

        <div style="display:flex;flex-direction:column;gap:22px">

          <div class="card" style="padding:clamp(20px,3vw,30px)">
            <h2 style="margin:0 0 20px;font-size:17px;font-weight:700;display:flex;align-items:center;gap:10px">
              <span style="width:26px;height:26px;border-radius:50%;background:#0E2A1B;color:#F5EBD8;font-size:13px;display:grid;place-items:center;font-weight:700">١</span>
              بيانات التواصل
            </h2>
            <div style="display:flex;flex-direction:column;gap:16px">
              <div class="fld" data-invalid="{{ $errors->has('customer_name') ? 'true' : 'false' }}">
                <label for="customer_name">الاسم بالكامل <span class="req">*</span></label>
                <input id="customer_name" name="customer_name" type="text" autocomplete="name" placeholder="مثال: أحمد محمد علي" value="{{ old('customer_name') }}">
                <span class="err">{{ $errors->first('customer_name') }}</span>
              </div>
              <div class="row2">
                <div class="fld" data-invalid="{{ $errors->has('customer_phone') ? 'true' : 'false' }}">
                  <label for="customer_phone">رقم الموبايل <span class="req">*</span></label>
                  <input id="customer_phone" name="customer_phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="01012345678" maxlength="11" value="{{ old('customer_phone') }}">
                  <span class="err">{{ $errors->first('customer_phone') }}</span>
                </div>
                <div class="fld" data-invalid="{{ $errors->has('customer_alt_phone') ? 'true' : 'false' }}">
                  <label for="customer_alt_phone">رقم بديل (اختياري)</label>
                  <input id="customer_alt_phone" name="customer_alt_phone" type="tel" inputmode="numeric" placeholder="01112345678" maxlength="11" value="{{ old('customer_alt_phone') }}">
                  <span class="err">{{ $errors->first('customer_alt_phone') }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="card" style="padding:clamp(20px,3vw,30px)">
            <h2 style="margin:0 0 20px;font-size:17px;font-weight:700;display:flex;align-items:center;gap:10px">
              <span style="width:26px;height:26px;border-radius:50%;background:#0E2A1B;color:#F5EBD8;font-size:13px;display:grid;place-items:center;font-weight:700">٢</span>
              عنوان التوصيل
            </h2>
            <div style="display:flex;flex-direction:column;gap:16px">
              <div class="row2">
                <div class="fld" data-invalid="{{ $errors->has('governorate_id') ? 'true' : 'false' }}">
                  <label for="governorate_id">المحافظة <span class="req">*</span></label>
                  <select id="governorate_id" name="governorate_id" x-model="gov">
                    <option value="">اختر المحافظة</option>
                    @foreach ($governorates as $g)
                      <option value="{{ $g->id }}">{{ $g->name_ar }}</option>
                    @endforeach
                  </select>
                  <span class="err">{{ $errors->first('governorate_id') }}</span>
                </div>
                <div class="fld" data-invalid="{{ $errors->has('city') ? 'true' : 'false' }}">
                  <label for="city">المدينة / المنطقة <span class="req">*</span></label>
                  <input id="city" name="city" type="text" placeholder="مثال: مدينة نصر" value="{{ old('city') }}">
                  <span class="err">{{ $errors->first('city') }}</span>
                </div>
              </div>
              <div class="fld" data-invalid="{{ $errors->has('address_line') ? 'true' : 'false' }}">
                <label for="address_line">العنوان بالتفصيل <span class="req">*</span></label>
                <textarea id="address_line" name="address_line" placeholder="اسم الشارع، رقم العقار، رقم الشقة، الدور">{{ old('address_line') }}</textarea>
                <span class="err">{{ $errors->first('address_line') }}</span>
              </div>
              <div class="fld" data-invalid="{{ $errors->has('landmark') ? 'true' : 'false' }}">
                <label for="landmark">علامة مميزة (اختياري)</label>
                <input id="landmark" name="landmark" type="text" placeholder="مثال: بجوار صيدلية النور" value="{{ old('landmark') }}">
                <span class="err">{{ $errors->first('landmark') }}</span>
              </div>
              <div class="fld">
                <label for="notes">ملاحظات على الطلب (اختياري)</label>
                <textarea id="notes" name="notes" placeholder="أي تعليمات خاصة بالتوصيل أو الطلب" style="min-height:76px">{{ old('notes') }}</textarea>
              </div>
            </div>
          </div>

          <div class="card" style="padding:clamp(20px,3vw,30px)">
            <h2 style="margin:0 0 18px;font-size:17px;font-weight:700;display:flex;align-items:center;gap:10px">
              <span style="width:26px;height:26px;border-radius:50%;background:#0E2A1B;color:#F5EBD8;font-size:13px;display:grid;place-items:center;font-weight:700">٣</span>
              طريقة الدفع
            </h2>
            {{-- The server re-validates the choice: Paymob is accepted only while
                 it is fully configured (StoreOrderRequest). --}}
            <div class="pay-options" role="radiogroup" aria-label="طريقة الدفع">
              <label class="pay-opt" :class="payment === 'cod' && 'is-on'">
                <input type="radio" name="payment_method" value="cod" x-model="payment">
                <span>
                  <span class="pay-opt__title">الدفع عند الاستلام</span>
                  <span class="pay-opt__desc">تدفع نقداً للمندوب وقت تسليم الطلب. لا حاجة لأي بيانات بنكية.</span>
                </span>
              </label>

              @if ($onlinePaymentAvailable)
                <label class="pay-opt" :class="payment === 'paymob' && 'is-on'">
                  <input type="radio" name="payment_method" value="paymob" x-model="payment">
                  <span>
                    <span class="pay-opt__title">الدفع الإلكتروني عبر Paymob (بطاقات بنكية ومحافظ إلكترونية)</span>
                    <span class="pay-opt__desc">فيزا، ماستركارد، ميزة، أو محفظة فودافون كاش / أورانج كاش / اتصالات كاش. ستنتقل إلى صفحة الدفع الآمنة من Paymob ثم تعود إلى المتجر.</span>
                  </span>
                </label>
              @endif
            </div>
            @error('payment_method')
              <p style="margin:12px 0 0;font-size:12.5px;color:#B04A4A;font-weight:500">{{ $message }}</p>
            @enderror
            @unless ($onlinePaymentAvailable)
              <p style="margin:14px 0 0;font-size:12.5px;color:#6B4630;line-height:1.7">الدفع الإلكتروني غير متاح حالياً.</p>
            @endunless
          </div>
        </div>

        <aside class="co-summary card" style="position:sticky;top:96px;padding:clamp(20px,3vw,28px)">
          <h2 style="margin:0 0 18px;font-size:17px;font-weight:700">ملخّص الطلب</h2>

          <div style="display:flex;flex-direction:column;gap:16px;max-height:340px;overflow-y:auto;padding-inline-end:4px">
            <template x-for="l in $store.cart.lines" :key="l.id">
              {{-- The line total sits in the controls row and wraps under them
                   on a phone, instead of squeezing the name into a sliver. --}}
              <div style="display:flex;gap:12px;align-items:flex-start">
                <div style="position:relative;flex:0 0 62px;width:62px;height:62px;background:#F5EBD8;border-radius:3px">
                  <img :src="l.image" :alt="l.name" style="position:absolute;inset:0;margin:auto;width:78%;height:78%;object-fit:contain">
                </div>
                <div style="flex:1;min-width:0">
                  <p style="margin:0 0 3px;font-size:14px;font-weight:600;line-height:1.5" x-text="l.name"></p>
                  <p style="margin:0 0 8px;font-size:12.5px;color:#6B4630" x-text="NB.fmt(l.price) + ' × ' + l.qty"></p>
                  <div class="co-line-ctl">
                    <button type="button" class="qbtn" @click="$store.cart.dec(l)" :disabled="l.qty <= 1" aria-label="إنقاص">−</button>
                    <span style="min-width:24px;text-align:center;font-size:14px;font-weight:600" x-text="l.qty"></span>
                    <button type="button" class="qbtn" @click="$store.cart.inc(l)" :disabled="l.qty >= l.stock" aria-label="زيادة">+</button>
                    <button type="button" class="co-remove" @click="$store.cart.remove(l)">حذف</button>
                    <p class="co-line-total" x-text="NB.fmt(l.line_total)"></p>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <div style="border-top:1px solid rgba(14,42,27,.1);margin-top:20px;padding-top:16px;display:flex;flex-direction:column;gap:11px;font-size:14.5px">
            <div style="display:flex;justify-content:space-between;gap:12px">
              <span style="color:#3A483D">المجموع الفرعي</span><span style="font-weight:600" x-text="fmt(subtotal)"></span>
            </div>
            <div style="display:flex;justify-content:space-between;gap:12px">
              <span style="color:#3A483D">الشحن</span><span style="font-weight:600" x-text="shippingLabel"></span>
            </div>
            <p style="margin:0;font-size:12.5px;color:#176B3A;line-height:1.6" x-text="freeHint"></p>
          </div>

          <div style="border-top:2px solid rgba(14,42,27,.12);margin-top:16px;padding-top:16px;display:flex;justify-content:space-between;align-items:baseline;gap:12px">
            <span style="font-size:16px;font-weight:700">الإجمالي</span>
            <span style="font-size:22px;font-weight:700;color:#176B3A" x-text="fmt(total)"></span>
          </div>

          @if ($errors->any())
            <p style="margin:16px 0 0;padding:12px 14px;background:#FFF2F2;border:1px solid #E9C7C7;border-radius:3px;font-size:13px;color:#B04A4A;line-height:1.7">من فضلك راجع الحقول المميزة بالأحمر أعلاه.</p>
          @endif

          <button type="submit" class="btn-place" style="margin-top:18px" :disabled="submitting"
                  x-text="payment === 'paymob' ? 'المتابعة للدفع الآمن عبر Paymob' : 'تأكيد الطلب — الدفع عند الاستلام'">تأكيد الطلب — الدفع عند الاستلام</button>

          <p style="margin:14px 0 0;font-size:12.5px;color:#6B4630;line-height:1.75;text-align:center">
            بتأكيد الطلب أنت توافق على سياسة الشحن والاسترجاع.
          </p>
        </aside>

        {{-- Phones only: the summary sits below a long form there, so the total
             and the submit button stay within thumb reach. Same form, so it
             submits exactly what the main button does. --}}
        <div class="co-mobilebar" role="region" aria-label="إجمالي الطلب">
          <div style="min-width:0">
            <span style="display:block;font-size:12px;color:#6B4630">الإجمالي</span>
            <span style="display:block;font-size:18px;font-weight:700;color:#176B3A;white-space:nowrap" x-text="fmt(total)"></span>
          </div>
          <button type="submit" class="btn-place" :disabled="submitting"
                  x-text="payment === 'paymob' ? 'ادفع الآن' : 'تأكيد الطلب'">تأكيد الطلب</button>
        </div>
      </form>
    </section>
  </main>

  @include('partials.overlays')
  @include('partials.mobile-menu')

  @include('partials.chrome-footer')

@endsection
