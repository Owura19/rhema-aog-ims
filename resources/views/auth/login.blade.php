<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sign In — {{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
    :root{--accent:#e05c3a;--accent-hover:#c94e2f;--ink:#141821;--ink-2:#6b7280;--line:#e5e7eb;}
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#eceef1;padding:24px;}
    .shell{width:100%;max-width:1080px;min-height:640px;background:#fff;border-radius:28px;overflow:hidden;display:flex;box-shadow:0 30px 80px rgba(16,24,40,.18);}
    .left{flex:0 0 46%;position:relative;background:linear-gradient(150deg,#2b1a17 0%,#1a1f2e 45%,#0d1526 100%);color:#fff;padding:34px;display:flex;flex-direction:column;overflow:hidden;}
    .left::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 25% 15%,rgba(224,92,58,.45),transparent 45%),radial-gradient(circle at 80% 85%,rgba(41,72,120,.5),transparent 50%),radial-gradient(circle at 60% 40%,rgba(255,150,90,.12),transparent 40%);}
    .left::after{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.06) 1px,transparent 1px);background-size:22px 22px;opacity:.5;}
    .left-top{position:relative;display:flex;align-items:center;justify-content:space-between;z-index:2;}
    .left-top .works{font-weight:700;font-size:14px;}
    .left-top .nav{display:flex;align-items:center;gap:14px;font-size:13px;}
    .left-top .nav a{color:rgba(255,255,255,.8);text-decoration:none;}
    .left-top .join{border:1.5px solid rgba(255,255,255,.5);border-radius:20px;padding:7px 16px;color:#fff;}
    .left-center{position:relative;z-index:2;flex:1;display:flex;flex-direction:column;justify-content:center;}
    .left-logo{width:70px;height:70px;border-radius:18px;background:linear-gradient(135deg,var(--accent),#f0a03a);display:flex;align-items:center;justify-content:center;font-size:30px;box-shadow:0 12px 34px rgba(224,92,58,.4);margin-bottom:22px;}
    .left-center h2{font-family:'Plus Jakarta Sans',sans-serif;font-size:30px;font-weight:800;line-height:1.15;letter-spacing:-.5px;}
    .left-center p{color:rgba(255,255,255,.7);font-size:14px;margin-top:12px;line-height:1.7;max-width:320px;}
    .left-bottom{position:relative;z-index:2;display:flex;align-items:center;gap:12px;}
    .left-ava{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#e05c3a,#f0a03a);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;}
    .left-bottom .who{font-weight:700;font-size:14px;}
    .left-bottom .role{color:rgba(255,255,255,.6);font-size:12px;}
    .right{flex:1;padding:44px 56px;display:flex;flex-direction:column;}
    .right-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:auto;}
    .brand{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:20px;color:var(--ink);letter-spacing:-.3px;}
    .brand span{color:var(--accent);}
    .lang{border:1px solid var(--line);border-radius:20px;padding:6px 12px;font-size:12.5px;color:var(--ink-2);display:flex;align-items:center;gap:6px;}
    .form-wrap{flex:1;display:flex;flex-direction:column;justify-content:center;max-width:380px;width:100%;margin:0 auto;}
    .form-wrap h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:38px;font-weight:800;letter-spacing:-1px;color:var(--ink);text-align:center;}
    .form-wrap .sub{text-align:center;color:var(--ink-2);font-size:14px;margin-top:6px;margin-bottom:28px;}
    .field{margin-bottom:14px;}
    .input{width:100%;padding:14px 16px;border:1.5px solid var(--line);border-radius:12px;font-size:14px;color:var(--ink);outline:none;transition:all .16s;}
    .input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(224,92,58,.1);}
    .err{color:#dc2626;font-size:12.5px;margin-top:5px;}
    .forgot{text-align:right;margin-bottom:16px;}
    .forgot a{color:var(--accent);font-size:12.5px;text-decoration:none;font-weight:600;}
    .divider{display:flex;align-items:center;gap:12px;color:#9ca3af;font-size:12px;margin:14px 0;}
    .divider::before,.divider::after{content:"";flex:1;height:1px;background:var(--line);}
    .google{width:100%;padding:12px;border:1.5px solid var(--line);border-radius:12px;background:#fff;display:flex;align-items:center;justify-content:center;gap:10px;font-size:13.5px;font-weight:600;color:var(--ink-2);cursor:not-allowed;opacity:.85;}
    .btn{width:100%;padding:15px;background:var(--accent);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;margin-top:14px;transition:all .16s;box-shadow:0 6px 18px rgba(224,92,58,.32);}
    .btn:hover{background:var(--accent-hover);transform:translateY(-1px);}
    .signup{text-align:center;font-size:13px;color:var(--ink-2);margin-top:18px;}
    .signup span{color:var(--accent);font-weight:600;}
    .socials{display:flex;align-items:center;justify-content:center;gap:20px;margin-top:22px;color:#9ca3af;font-size:16px;}
    .status{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;}
    @media(max-width:880px){.left{display:none;}.right{padding:36px 28px;}.shell{min-height:auto;max-width:440px;}}
</style>
</head>
<body>
<div class="shell">
    <div class="left">
        <div class="left-top">
            <div class="works">Rhema Assembly of God</div>
            <div class="nav"><a>Home</a><span class="join">Members</span></div>
        </div>
        <div class="left-center">
            <div class="left-logo"><i class="fas fa-church"></i></div>
            <h2>Information Management System</h2>
            <p>Managing members, giving, attendance, and church operations — all in one place.</p>
        </div>
        <div class="left-bottom">
            <div class="left-ava">R</div>
            <div><div class="who">Rhema AOG</div><div class="role">Church Management</div></div>
        </div>
    </div>
    <div class="right">
        <div class="right-top">
            <div class="brand">Rhema <span>IMS</span></div>
            <div class="lang"><i class="fas fa-globe"></i> EN</div>
        </div>
        <div class="form-wrap">
            <h1>Welcome Back</h1>
            <div class="sub">Sign in to Rhema IMS</div>
            @if (session('status'))<div class="status"><i class="fas fa-circle-check"></i> {{ session('status') }}</div>@endif
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="field">
                    <input class="input" type="email" name="email" value="{{ old('email') }}" placeholder="Email" required autofocus autocomplete="username">
                    @error('email')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <input class="input" type="password" name="password" placeholder="Password" required autocomplete="current-password">
                    @error('password')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="forgot">@if (Route::has('password.request'))<a href="{{ route('password.request') }}">Forgot password ?</a>@endif</div>
                <div class="divider">or</div>
                <div class="google" title="Google sign-in is not enabled"><i class="fab fa-google" style="color:#ea4335;"></i> Login with Google</div>
                <button type="submit" class="btn">Login</button>
            </form>
            <div class="signup">Members are added by church admin · <span>Contact your admin</span></div>
            <div class="socials"><i class="fab fa-facebook"></i><i class="fab fa-x-twitter"></i><i class="fab fa-linkedin"></i><i class="fab fa-instagram"></i></div>
        </div>
    </div>
</div>
</body>
</html>
