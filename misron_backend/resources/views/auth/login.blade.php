@if (!config('app.admin_ui_premium'))
    @include('auth.login_classic')
@else
@php
    $rtl = get_session_language()->rtl ?? 0;
    $shop = get_setting('website_name') ?: config('app.name');
    $icon = get_setting('site_icon') ? uploaded_asset(get_setting('site_icon')) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ translate('Admin Login') }} | {{ $shop }}</title>
    @if ($icon)<link rel="icon" href="{{ $icon }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>try{var t=localStorage.getItem('ui-theme');document.documentElement.setAttribute('data-ui-theme',t==='light'?'light':'dark')}catch(e){document.documentElement.setAttribute('data-ui-theme','dark')}</script>
    <style>
        :root{--ember:#fa7319;--ember-hi:#ff9447;--ember-lo:#e0600c;--ease:cubic-bezier(.2,.8,.2,1);
            --bg:#070708;--panel:rgba(22,22,25,.62);--line:rgba(255,255,255,.10);--text:#f5f1ec;--muted:#a59d94;--field:rgba(255,255,255,.05);--shadow:0 30px 80px rgba(0,0,0,.55)}
        html[data-ui-theme="light"]{--bg:#f6f3ef;--panel:rgba(255,255,255,.72);--line:rgba(20,16,12,.10);--text:#16120e;--muted:#6f675f;--field:rgba(20,16,12,.04);--shadow:0 30px 70px rgba(80,50,20,.18)}
        *{box-sizing:border-box;margin:0}
        html,body{height:100%}
        body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;-webkit-font-smoothing:antialiased}
        .stage{min-height:100vh;display:grid;grid-template-columns:1.05fr .95fr;position:relative}
        /* ---------- brand side ---------- */
        .brand{position:relative;overflow:hidden;padding:56px;display:flex;flex-direction:column;justify-content:space-between;background:#0a0a0b;color:#f5f1ec;isolation:isolate}
        .brand::before{content:"";position:absolute;inset:0;z-index:-2;background:radial-gradient(900px 500px at 15% 10%,rgba(250,115,25,.35),transparent 60%),linear-gradient(160deg,#0a0a0b,#17110c 70%,#0a0a0b)}
        .brand::after{content:"";position:absolute;inset:0;z-index:-1;opacity:.35;background-image:linear-gradient(rgba(255,255,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.06) 1px,transparent 1px);background-size:48px 48px;-webkit-mask-image:radial-gradient(70% 70% at 40% 40%,#000,transparent);mask-image:radial-gradient(70% 70% at 40% 40%,#000,transparent)}
        .orb{position:absolute;border-radius:50%;filter:blur(60px);z-index:-1;will-change:transform;opacity:.7}
        .o1{width:380px;height:380px;background:#fa7319;top:-90px;left:-70px;animation:drift1 16s ease-in-out infinite alternate}
        .o2{width:300px;height:300px;background:#ff9447;bottom:-80px;right:-40px;opacity:.45;animation:drift2 19s ease-in-out infinite alternate}
        .o3{width:220px;height:220px;background:#b34a08;top:45%;left:45%;opacity:.4;animation:drift3 22s ease-in-out infinite alternate}
        @keyframes drift1{to{transform:translate3d(120px,90px,0) scale(1.15)}}
        @keyframes drift2{to{transform:translate3d(-110px,-70px,0) scale(1.1)}}
        @keyframes drift3{to{transform:translate3d(-90px,60px,0) scale(.85)}}
        .logo{display:flex;align-items:center;gap:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;font-size:18px}
        .logo img{width:40px;height:40px;object-fit:contain;border-radius:11px;background:rgba(255,255,255,.08);padding:6px}
        .logo i{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(135deg,var(--ember-hi),var(--ember-lo));font-style:normal;box-shadow:0 8px 24px rgba(250,115,25,.45)}
        .hero h1{font-size:clamp(30px,3.4vw,50px);line-height:1.08;font-weight:800;letter-spacing:-.02em;max-width:520px}
        .hero h1 span{background:linear-gradient(90deg,#ff9447,#fa7319);-webkit-background-clip:text;background-clip:text;color:transparent}
        .hero p{margin-top:16px;color:#b9b0a6;max-width:440px;line-height:1.65;font-size:15px}
        .chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:28px}
        .chip{padding:9px 14px;border-radius:999px;font-size:12.5px;font-weight:600;color:#eee5dc;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);animation:float 6s ease-in-out infinite}
        .chip:nth-child(2){animation-delay:-1.5s}.chip:nth-child(3){animation-delay:-3s}.chip:nth-child(4){animation-delay:-4.5s}
        @keyframes float{50%{transform:translateY(-7px)}}
        .foot{font-size:12px;color:#8b8279}
        /* ---------- form side ---------- */
        .side{position:relative;display:grid;place-items:center;padding:32px}
        .side::before{content:"";position:absolute;inset:0;background:radial-gradient(520px 340px at 80% 10%,rgba(250,115,25,.14),transparent 70%);pointer-events:none}
        .card{position:relative;width:min(440px,100%);padding:40px 36px;border-radius:28px;background:var(--panel);border:1px solid var(--line);box-shadow:var(--shadow),inset 0 1px 0 rgba(255,255,255,.08);-webkit-backdrop-filter:blur(22px) saturate(1.3);backdrop-filter:blur(22px) saturate(1.3);animation:rise .8s var(--ease) both}
        .card::before{content:"";position:absolute;inset:-1px;border-radius:inherit;padding:1px;background:linear-gradient(140deg,rgba(250,115,25,.7),transparent 35%,transparent 65%,rgba(250,115,25,.35));-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none}
        @keyframes rise{from{opacity:0;transform:translateY(24px) scale(.98)}}
        .card.shake{animation:shake .5s}
        @keyframes shake{20%,60%{transform:translateX(-8px)}40%,80%{transform:translateX(8px)}}
        .mlogo{display:none;margin-bottom:22px}
        h2{font-size:26px;font-weight:800;letter-spacing:-.02em}
        .sub{color:var(--muted);font-size:14px;margin:6px 0 26px}
        .field{position:relative;margin-bottom:16px}
        .field input{width:100%;height:56px;padding:22px 48px 6px 16px;font:inherit;font-size:15px;color:var(--text);background:var(--field);border:1.5px solid var(--line);border-radius:14px;outline:none;transition:border-color .2s,box-shadow .2s,background .2s}
        .field label{position:absolute;left:16px;top:18px;font-size:14px;color:var(--muted);pointer-events:none;transition:transform .2s var(--ease),font-size .2s,color .2s;transform-origin:left top}
        .field input:focus,.field input:not(:placeholder-shown){padding-top:24px}
        .field input:focus~label,.field input:not(:placeholder-shown)~label{transform:translateY(-12px);font-size:11px;font-weight:600;letter-spacing:.04em}
        .field input:focus{border-color:var(--ember);box-shadow:0 0 0 4px rgba(250,115,25,.18);background:transparent}
        .field input:focus~label{color:var(--ember)}
        .field.bad input{border-color:#ef4444}
        .eye{position:absolute;right:8px;top:8px;width:40px;height:40px;border:0;border-radius:10px;background:transparent;color:var(--muted);cursor:pointer;display:grid;place-items:center;transition:color .2s,background .2s}
        .eye:hover{color:var(--ember);background:rgba(250,115,25,.12)}
        .msg{font-size:12.5px;color:#ef4444;margin:-8px 0 14px 4px}
        .caps{display:none;font-size:12px;color:var(--ember);margin:-8px 0 12px 4px}
        .row{display:flex;justify-content:space-between;align-items:center;margin:4px 0 22px;font-size:13px}
        .remember{display:flex;align-items:center;gap:9px;color:var(--muted);cursor:pointer;user-select:none}
        .remember input{appearance:none;-webkit-appearance:none;width:19px;height:19px;border-radius:6px;border:1.5px solid var(--line);background:var(--field);display:grid;place-items:center;cursor:pointer;transition:.2s}
        .remember input:checked{background:linear-gradient(135deg,var(--ember-hi),var(--ember-lo));border-color:transparent}
        .remember input:checked::after{content:"";width:5px;height:9px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg) translate(-1px,-1px)}
        .row a{color:var(--ember);text-decoration:none;font-weight:600}.row a:hover{text-decoration:underline}
        .go{position:relative;width:100%;height:54px;border:0;border-radius:14px;cursor:pointer;font:inherit;font-weight:700;font-size:15px;letter-spacing:.02em;color:#fff;overflow:hidden;background:linear-gradient(135deg,var(--ember-hi),var(--ember) 45%,var(--ember-lo));box-shadow:0 14px 34px rgba(250,115,25,.45),inset 0 1px 0 rgba(255,255,255,.35);transition:transform .2s var(--ease),box-shadow .2s}
        .go::after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.45),transparent);transform:skewX(-20deg);animation:sheen 4.5s ease-in-out infinite}
        @keyframes sheen{0%,60%{left:-60%}100%{left:130%}}
        .go:hover{transform:translateY(-2px);box-shadow:0 18px 40px rgba(250,115,25,.55),inset 0 1px 0 rgba(255,255,255,.4)}
        .go:active{transform:translateY(0) scale(.99)}
        .go.load{pointer-events:none;opacity:.9}
        .go.load span{opacity:0}
        .go.load::before{content:"";position:absolute;left:50%;top:50%;width:20px;height:20px;margin:-10px;border-radius:50%;border:2.5px solid rgba(255,255,255,.4);border-top-color:#fff;animation:spin .7s linear infinite;z-index:1}
        @keyframes spin{to{transform:rotate(360deg)}}
        .secure{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:20px;font-size:12px;color:var(--muted)}
        .theme{position:fixed;top:18px;right:18px;width:42px;height:42px;border-radius:50%;border:1px solid var(--line);background:var(--panel);color:var(--text);cursor:pointer;display:grid;place-items:center;-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);z-index:5;transition:transform .2s var(--ease),color .2s}
        .theme:hover{transform:rotate(18deg) scale(1.06);color:var(--ember)}
        .alert.err{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.4)}
        .alert{margin-bottom:18px;padding:11px 14px;border-radius:12px;font-size:13px;background:rgba(250,115,25,.12);border:1px solid rgba(250,115,25,.35)}
        @media(max-width:900px){.stage{grid-template-columns:1fr}.brand{display:none}.mlogo{display:flex;color:var(--text)}.side{min-height:100vh;padding:20px 16px}.card{padding:32px 24px}}
        @media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
    </style>
</head>
<body>
<button type="button" class="theme" id="theme" aria-label="Toggle theme"></button>
<main class="stage">
    <section class="brand" aria-hidden="true">
        <span class="orb o1"></span><span class="orb o2"></span><span class="orb o3"></span>
        <div class="logo">@if ($icon)<img src="{{ $icon }}" alt="" onerror="this.replaceWith(Object.assign(document.createElement('i'),{textContent:'{{ strtoupper(substr($shop,0,1)) }}'}))">@else<i>{{ strtoupper(substr($shop, 0, 1)) }}</i>@endif {{ $shop }}</div>
        <div class="hero">
            <h1>{{ translate('Run your whole shop from') }} <span>{{ translate('one command centre') }}</span></h1>
            <p>{{ translate('Orders, stock, purchasing, funnels and customers: everything you need to sell more, in one fast and secure place.') }}</p>
            <div class="chips"><span class="chip">{{ translate('Orders') }}</span><span class="chip">{{ translate('Inventory') }}</span><span class="chip">{{ translate('Sales Funnels') }}</span><span class="chip">{{ translate('Reports') }}</span></div>
        </div>
        <div class="foot">&copy; {{ date('Y') }} {{ $shop }}</div>
    </section>

    <section class="side">
        <div class="card" id="card">
            <div class="logo mlogo">@if ($icon)<img src="{{ $icon }}" alt="" onerror="this.style.display='none'">@endif {{ $shop }}</div>
            <h2>{{ translate('Welcome back') }}</h2>
            <p class="sub">{{ translate('Sign in to your admin account') }}</p>

            @php $flashes = session('flash_notification', collect())->toArray(); @endphp
            @if (session('status'))<div class="alert" role="status">{{ session('status') }}</div>@endif
            @foreach ($flashes as $f)<div class="alert {{ ($f['level'] ?? '') === 'danger' ? 'err' : '' }}" role="alert">{{ strip_tags($f['message'] ?? '') }}</div>@endforeach

            <form action="{{ route('login') }}" method="POST" id="form" novalidate>
                @csrf
                <div class="field {{ $errors->has('email') ? 'bad' : '' }}">
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder=" " autocomplete="username" required autofocus>
                    <label for="email">{{ translate('Email') }}</label>
                </div>
                @if ($errors->has('email'))<div class="msg" role="alert">{{ $errors->first('email') }}</div>@endif

                <div class="field {{ $errors->has('password') ? 'bad' : '' }}">
                    <input type="password" id="password" name="password" placeholder=" " autocomplete="current-password" required>
                    <label for="password">{{ translate('Password') }}</label>
                    <button type="button" class="eye" id="eye" aria-label="{{ translate('Show password') }}"></button>
                </div>
                @if ($errors->has('password'))<div class="msg" role="alert">{{ $errors->first('password') }}</div>@endif
                <div class="caps" id="caps">{{ translate('Caps Lock is on') }}</div>

                <div class="row">
                    <label class="remember"><input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}> {{ translate('Remember Me') }}</label>
                    <a href="{{ route('password.request') }}">{{ translate('Forgot password?') }}</a>
                </div>

                <button type="submit" class="go" id="go"><span>{{ translate('Login') }}</span></button>
            </form>

            @if (env('DEMO_MODE') == 'On')
                <p class="sub" style="margin:18px 0 0">admin@example.com / 123456
                    <a href="#" onclick="document.getElementById('email').value='admin@example.com';document.getElementById('password').value='123456';return false" style="color:var(--ember)">{{ translate('Copy') }}</a></p>
            @endif
            <div class="secure"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg> {{ translate('Secured connection') }}</div>
        </div>
    </section>
</main>
<script>
(function(){
  var root=document.documentElement;
  var MOON='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
  var SUN='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
  var EYE='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYEOFF='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.9 17.9A10.9 10.9 0 0 1 12 19C5 19 1 12 1 12a18 18 0 0 1 5.1-5.9M9.9 5.1A10.7 10.7 0 0 1 12 5c7 0 11 7 11 7a18 18 0 0 1-2.2 3.2M1 1l22 22"/></svg>';
  var tb=document.getElementById('theme');
  function paint(){tb.innerHTML=root.getAttribute('data-ui-theme')==='dark'?SUN:MOON}
  tb.onclick=function(){var m=root.getAttribute('data-ui-theme')==='dark'?'light':'dark';root.setAttribute('data-ui-theme',m);try{localStorage.setItem('ui-theme',m)}catch(e){}paint()};paint();
  var pw=document.getElementById('password'),eye=document.getElementById('eye');eye.innerHTML=EYE;
  eye.onclick=function(){var s=pw.type==='password';pw.type=s?'text':'password';eye.innerHTML=s?EYEOFF:EYE;pw.focus()};
  pw.addEventListener('keyup',function(e){document.getElementById('caps').style.display=(e.getModifierState&&e.getModifierState('CapsLock'))?'block':'none'});
  var card=document.getElementById('card');
  @if ($errors->any() || count($flashes)) card.classList.add('shake'); @endif
  document.getElementById('form').addEventListener('submit',function(e){
    var em=document.getElementById('email');
    if(!em.value.trim()||!pw.value){e.preventDefault();card.classList.remove('shake');void card.offsetWidth;card.classList.add('shake');(em.value.trim()?pw:em).focus();return}
    document.getElementById('go').classList.add('load');
  });
  // gentle parallax on the brand orbs (desktop only, skipped for reduced motion)
  if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches&&window.innerWidth>900){
    var b=document.querySelector('.brand'),o=b.querySelectorAll('.orb');
    document.addEventListener('mousemove',function(e){var x=(e.clientX/innerWidth-.5),y=(e.clientY/innerHeight-.5);o.forEach(function(el,i){el.style.translate=(x*(30+i*20))+'px '+(y*(30+i*20))+'px'})},{passive:true});
  }
})();
</script>
</body>
</html>
@endif
