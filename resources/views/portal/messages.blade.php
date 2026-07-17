<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Messages — {{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
    :root{--primary:#1a3c5e;--accent:#e8a020;--ink:#0f172a;--ink-2:#64748b;--line:#e2e8f0;}
    body{background:#f6f7f9;color:var(--ink);}
    .topbar{background:linear-gradient(135deg,#1a3c5e,#0f2540);color:#fff;padding:0 28px;height:66px;display:flex;align-items:center;justify-content:space-between;}
    .topbar .brand{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;}
    .topbar .brand span{color:var(--accent);}
    .topbar .right{display:flex;align-items:center;gap:16px;}
    .topbar a,.topbar button{color:rgba(255,255,255,.8);text-decoration:none;font-size:13px;font-weight:600;background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:6px;}
    .topbar a:hover,.topbar button:hover{color:#fff;}
    .avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;}

    .wrap{max-width:760px;margin:0 auto;padding:24px 20px;}
    .back{color:var(--ink-2);text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;margin-bottom:16px;}
    .card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 1px 3px rgba(16,24,40,.05);overflow:hidden;}
    .card-head{padding:18px 22px;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:16px;display:flex;align-items:center;gap:8px;}
    .card-head small{font-weight:400;color:var(--ink-2);font-size:12.5px;}

    .thread{padding:22px;display:flex;flex-direction:column;gap:14px;min-height:240px;max-height:460px;overflow-y:auto;}
    .msg{max-width:75%;padding:11px 15px;border-radius:14px;font-size:13.5px;line-height:1.5;}
    .msg .meta{font-size:11px;margin-top:5px;opacity:.7;}
    .from-member{align-self:flex-end;background:var(--primary);color:#fff;border-bottom-right-radius:4px;}
    .from-leader{align-self:flex-start;background:#f1f5f9;color:var(--ink);border-bottom-left-radius:4px;}
    .empty{text-align:center;color:#94a3b8;font-size:13px;padding:40px 0;}

    .composer{border-top:1px solid #f1f5f9;padding:16px 22px;display:flex;gap:10px;align-items:flex-end;}
    .composer textarea{flex:1;border:1.5px solid var(--line);border-radius:10px;padding:11px 13px;font-size:13.5px;resize:vertical;min-height:46px;outline:none;font-family:inherit;}
    .composer textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,60,94,.08);}
    .send-btn{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:12px 18px;font-weight:700;font-size:13.5px;cursor:pointer;display:flex;align-items:center;gap:7px;white-space:nowrap;}
    .send-btn:hover{background:#234d78;}

    .flash{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px;}
    .err{color:#dc2626;font-size:12.5px;margin-top:6px;}
</style>
</head>
<body>

<div class="topbar">
    <div class="brand">{{ config('app.name') }} <span>Member Portal</span></div>
    <div class="right">
        <div class="avatar">{{ strtoupper(substr($member->first_name,0,1)) }}</div>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button type="submit"><i class="fas fa-right-from-bracket"></i> Sign out</button>
        </form>
    </div>
</div>

<div class="wrap">
    <a href="{{ route('portal.dashboard') }}" class="back"><i class="fas fa-arrow-left"></i> Back to dashboard</a>

    @if(session('success'))
        <div class="flash"><i class="fas fa-circle-check"></i> {{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-head">
            <i class="fas fa-comments" style="color:var(--primary);"></i>
            Messages to Leadership
            <small>&nbsp;— private & confidential</small>
        </div>

        <div class="thread" id="thread">
            @forelse($messages as $msg)
                <div class="msg {{ $msg->is_from_member ? 'from-member' : 'from-leader' }}">
                    {{ $msg->body }}
                    <div class="meta">
                        {{ $msg->is_from_member ? 'You' : (optional($msg->senderUser)->name ?? 'Leadership') }}
                        · {{ $msg->created_at->format('M d, g:i A') }}
                    </div>
                </div>
            @empty
                <div class="empty">
                    <i class="fas fa-comment-dots" style="font-size:28px;color:#cbd5e1;"></i>
                    <div style="margin-top:10px;">No messages yet. Send a private message to church leadership below.</div>
                </div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('portal.messages.send') }}" class="composer">
            @csrf
            <textarea name="body" placeholder="Write a private message to leadership..." required></textarea>
            <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i> Send</button>
        </form>
        @error('body')<div class="err" style="padding:0 22px 16px;">{{ $message }}</div>@enderror
    </div>
</div>

<script>
    // Scroll thread to the latest message
    var t = document.getElementById('thread');
    if (t) t.scrollTop = t.scrollHeight;
</script>
</body>
</html>