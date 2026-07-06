<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhema — Projector View</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #0a0f1e;
            color: #fff;
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .bg-pattern {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(124,58,237,0.15) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(37,99,235,0.15) 0%, transparent 60%),
                radial-gradient(ellipse at 60% 80%, rgba(232,160,32,0.08) 0%, transparent 50%);
            z-index: 0;
        }

        /* ── HEADER ── */
        .header {
            position: fixed;
            top: 0; left: 0; right: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 40px;
            z-index: 10;
            background: linear-gradient(to bottom, rgba(10,15,30,0.95), transparent);
        }

        .church-name {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 700;
            color: #e8a020;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .live-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: rgba(255,255,255,0.6);
        }

        .live-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #64748b;
            animation: pulse 1.5s infinite;
        }

        .mode-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .mode-verse   { background: rgba(124,58,237,0.3); color: #c4b5fd; border: 1px solid rgba(124,58,237,0.4); }
        .mode-notes   { background: rgba(22,163,74,0.3);  color: #86efac; border: 1px solid rgba(22,163,74,0.4); }
        .mode-waiting { background: rgba(100,116,139,0.3); color: #94a3b8; border: 1px solid rgba(100,116,139,0.4); }

        /* ── VERSE COUNTDOWN BAR ── */
        .verse-countdown-bar {
            position: fixed;
            top: 0; left: 0;
            height: 4px;
            background: #7c3aed;
            z-index: 200;
            transition: width 1s linear;
        }

        /* ── MAIN CONTENT ── */
        .main-content {
            position: relative;
            z-index: 10;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 60px 100px;
        }

        /* ── WAITING STATE ── */
        .waiting-state { text-align: center; }

        .waiting-logo {
            font-family: 'Playfair Display', serif;
            font-size: 56px;
            font-weight: 900;
            color: #fff;
            margin-bottom: 12px;
            line-height: 1;
        }

        .waiting-logo span { color: #e8a020; }

        .waiting-subtitle {
            font-size: 16px;
            color: rgba(255,255,255,0.4);
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 40px;
        }

        .waiting-pulse {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .waiting-pulse span {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: rgba(232,160,32,0.5);
            animation: dotPulse 1.4s infinite;
        }

        .waiting-pulse span:nth-child(2) { animation-delay: 0.2s; }
        .waiting-pulse span:nth-child(3) { animation-delay: 0.4s; }

        /* ── VERSE DISPLAY ── */
        .verse-display {
            display: none;
            text-align: center;
            width: 100%;
            max-width: 900px;
            animation: fadeIn 0.5s ease;
        }

        .verse-reference {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: #e8a020;
            margin-bottom: 24px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .verse-divider {
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, transparent, #e8a020, transparent);
            margin: 0 auto 24px;
        }

        .verse-text {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 400;
            color: #fff;
            line-height: 1.6;
            font-style: italic;
            text-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }

        .verse-back-label {
            margin-top: 24px;
            font-size: 12px;
            color: rgba(255,255,255,0.3);
            letter-spacing: 2px;
        }

        /* ── NOTES DISPLAY ── */
        .notes-display {
            display: none;
            width: 100%;
            max-width: 1100px;
            animation: fadeIn 0.5s ease;
        }

        .notes-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .notes-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 700;
            color: #e8a020;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .notes-subtitle {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            letter-spacing: 2px;
        }

        .notes-full {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 24px 28px;
        }

        .notes-full-content {
            font-size: 16px;
            color: rgba(255,255,255,0.85);
            line-height: 2;
            white-space: pre-wrap;
            max-height: 58vh;
            overflow-y: auto;
        }

        /* ── FOOTER ── */
        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            padding: 14px 40px;
            z-index: 10;
            background: linear-gradient(to top, rgba(10,15,30,0.95), transparent);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .recent-verses { display: flex; gap: 8px; flex-wrap: wrap; }

        .recent-badge {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 16px;
            padding: 4px 12px;
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            cursor: pointer;
            transition: all 0.2s;
        }

        .recent-badge:hover {
            background: rgba(232,160,32,0.2);
            border-color: #e8a020;
            color: #e8a020;
        }

        .footer-right { display: flex; align-items: center; gap: 12px; }

        .manual-input { display: flex; gap: 8px; }

        .manual-input input {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 8px 14px;
            color: #fff;
            font-size: 13px;
            width: 180px;
            outline: none;
        }

        .manual-input input::placeholder { color: rgba(255,255,255,0.4); }

        .manual-input button {
            background: #e8a020;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        /* ── SWITCH BUTTONS ── */
        .view-switcher { display: flex; gap: 8px; }

        .switch-btn {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .switch-btn.active   { background: #e8a020; color: #fff; }
        .switch-btn.inactive { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); }

        /* ── UPDATE FLASH ── */
        .update-flash {
            position: fixed;
            top: 60px;
            right: 20px;
            background: rgba(22,163,74,0.9);
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* ── VERSE INCOMING FLASH ── */
        .verse-flash {
            position: fixed;
            top: 60px;
            left: 20px;
            background: rgba(124,58,237,0.9);
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* ── ANIMATIONS ── */
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        @keyframes dotPulse {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="bg-pattern"></div>
<div class="verse-countdown-bar" id="verse-countdown-bar" style="width:0%;"></div>
<div class="update-flash" id="update-flash">✓ Notes Updated</div>
<div class="verse-flash" id="verse-flash">📖 New Verse Detected</div>

<!-- Header -->
<div class="header">
    <div class="church-name">Rhema Assembly of God</div>
    <div class="header-right">
        <div class="view-switcher">
            <button class="switch-btn active" id="btn-waiting" onclick="showWaiting()">Waiting</button>
            <button class="switch-btn inactive" id="btn-verse" onclick="showLastVerse()">Verse</button>
            <button class="switch-btn inactive" id="btn-notes" onclick="showNotes()">Notes</button>
        </div>
        <span class="mode-badge mode-waiting" id="mode-badge">Waiting</span>
        <div class="live-indicator">
            <div class="live-dot" id="live-dot"></div>
            <span id="live-label">Waiting for sermon...</span>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">

    <!-- Waiting State -->
    <div id="waiting-state" class="waiting-state">
        <div class="waiting-logo">Grace<span>World</span></div>
        <div class="waiting-subtitle">International Ministries</div>
        <div class="waiting-pulse">
            <span></span><span></span><span></span>
        </div>
    </div>

    <!-- Verse Display -->
    <div id="verse-display" class="verse-display">
        <div class="verse-reference" id="verse-ref"></div>
        <div class="verse-divider"></div>
        <div class="verse-text" id="verse-text"></div>
        <div class="verse-back-label" id="verse-back-label"></div>
    </div>

    <!-- Notes Display -->
    <div id="notes-display" class="notes-display">
        <div class="notes-header">
            <div class="notes-title">📖 Live Sermon Notes</div>
            <div class="notes-subtitle">Updated every 2 minutes · Verses appear automatically</div>
        </div>
        <div class="notes-full">
            <div class="notes-full-content" id="notes-content">Waiting for sermon notes...</div>
        </div>
    </div>

</div>

<!-- Footer -->
<div class="footer">
    <div class="recent-verses" id="recent-verses"></div>
    <div class="footer-right">
        <div class="manual-input">
            <input type="text" id="manual-ref" placeholder="Type verse e.g. John 3:16" onkeypress="if(event.key==='Enter') manualLookup()">
            <button onclick="manualLookup()">Show</button>
        </div>
    </div>
</div>

<script>
const csrfToken    = '{{ csrf_token() }}';
const recentVerses = [];
let currentMode    = 'waiting';
let lastVerse      = null;
let verseTimeout   = null;
let countdownBar   = null;
let currentNotes   = '';
const VERSE_DISPLAY_TIME = 15000; // 15 seconds

// ── BROADCAST CHANNEL ──
try {
    const channel = new BroadcastChannel('rhema-projector');
    channel.onmessage = (event) => {
        if (event.data.type === 'verse') {
            // Always interrupt — show verse immediately regardless of current mode
            clearTimeout(verseTimeout);
            clearInterval(countdownBar);
            showVerseFlash();
            displayVerse(event.data.data);
            setLive();
        }
        if (event.data.type === 'notes') {
            updateNotes(event.data.data);
            setLive();
            // Only switch to notes if NOT currently showing a verse
            if (currentMode !== 'verse') {
                showNotes();
            }
        }
    };
} catch(e) {}

function setLive() {
    document.getElementById('live-dot').style.background = '#dc2626';
    document.getElementById('live-label').textContent    = 'Live — Receiving sermon';
}

function showVerseFlash() {
    const flash = document.getElementById('verse-flash');
    flash.style.opacity = '1';
    setTimeout(() => flash.style.opacity = '0', 2000);
}

// ── DISPLAY VERSE — always interrupts current mode ──
function displayVerse(verse) {
    lastVerse   = verse;
    currentMode = 'verse';

    // Hide all other states
    document.getElementById('waiting-state').style.display = 'none';
    document.getElementById('notes-display').style.display  = 'none';
    document.getElementById('verse-display').style.display  = 'block';

    // Set verse content
    document.getElementById('verse-ref').textContent  = verse.reference;
    document.getElementById('verse-text').textContent = verse.text;

    // Update badges
    document.getElementById('mode-badge').textContent = '📖 Verse';
    document.getElementById('mode-badge').className   = 'mode-badge mode-verse';
    updateSwitcher('verse');

    // Add to recent
    addToRecent(verse);

    // Start countdown bar
    startCountdownBar();

    // Show back label
    const backLabel = document.getElementById('verse-back-label');
    if (currentNotes) {
        backLabel.textContent = 'Returning to notes in 15 seconds...';
    } else {
        backLabel.textContent = '';
    }

    // After 15 seconds — go back to notes if notes exist, otherwise waiting
    verseTimeout = setTimeout(() => {
        if (currentNotes) {
            showNotes();
        } else {
            showWaiting();
        }
    }, VERSE_DISPLAY_TIME);
}

// ── COUNTDOWN BAR ──
function startCountdownBar() {
    const bar = document.getElementById('verse-countdown-bar');
    bar.style.transition = 'none';
    bar.style.width = '100%';

    setTimeout(() => {
        bar.style.transition = `width ${VERSE_DISPLAY_TIME}ms linear`;
        bar.style.width = '0%';
    }, 50);
}

// ── UPDATE NOTES ──
function updateNotes(notes) {
    currentNotes = notes;
    document.getElementById('notes-content').textContent = notes;

    // Flash update indicator
    const flash = document.getElementById('update-flash');
    flash.style.opacity = '1';
    setTimeout(() => flash.style.opacity = '0', 2000);
}

// ── SHOW MODES ──
function showWaiting() {
    currentMode = 'waiting';
    clearTimeout(verseTimeout);

    document.getElementById('waiting-state').style.display = 'block';
    document.getElementById('verse-display').style.display  = 'none';
    document.getElementById('notes-display').style.display  = 'none';

    document.getElementById('mode-badge').textContent = 'Waiting';
    document.getElementById('mode-badge').className   = 'mode-badge mode-waiting';
    updateSwitcher('waiting');

    // Reset countdown bar
    const bar = document.getElementById('verse-countdown-bar');
    bar.style.transition = 'none';
    bar.style.width = '0%';
}

function showLastVerse() {
    if (lastVerse) {
        clearTimeout(verseTimeout);
        displayVerse(lastVerse);
    }
}

function showNotes() {
    if (!currentNotes) return;
    currentMode = 'notes';
    clearTimeout(verseTimeout);

    document.getElementById('waiting-state').style.display = 'none';
    document.getElementById('verse-display').style.display  = 'none';
    document.getElementById('notes-display').style.display  = 'block';

    document.getElementById('mode-badge').textContent = '📝 Notes';
    document.getElementById('mode-badge').className   = 'mode-badge mode-notes';
    updateSwitcher('notes');

    // Reset countdown bar
    const bar = document.getElementById('verse-countdown-bar');
    bar.style.transition = 'none';
    bar.style.width = '0%';
}

function updateSwitcher(mode) {
    document.getElementById('btn-waiting').className = 'switch-btn ' + (mode === 'waiting' ? 'active' : 'inactive');
    document.getElementById('btn-verse').className   = 'switch-btn ' + (mode === 'verse'   ? 'active' : 'inactive');
    document.getElementById('btn-notes').className   = 'switch-btn ' + (mode === 'notes'   ? 'active' : 'inactive');
}

// ── RECENT VERSES ──
function addToRecent(verse) {
    if (!recentVerses.find(v => v.reference === verse.reference)) {
        recentVerses.unshift(verse);
        if (recentVerses.length > 6) recentVerses.pop();
        updateRecentUI();
    }
}

function updateRecentUI() {
    const container = document.getElementById('recent-verses');
    container.innerHTML = recentVerses.map(v => `
        <div class="recent-badge" onclick='clearTimeout(verseTimeout); displayVerse(${JSON.stringify(v)})'>${v.reference}</div>
    `).join('');
}

// ── MANUAL VERSE LOOKUP ──
async function manualLookup() {
    const ref = document.getElementById('manual-ref').value.trim();
    if (!ref) return;
    try {
        const res  = await fetch('/randyimpact/get-verse', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ reference: ref }),
        });
        const data = await res.json();
        if (data.success) {
            clearTimeout(verseTimeout);
            displayVerse({ reference: data.reference, text: data.text });
            document.getElementById('manual-ref').value = '';
        } else {
            alert('Verse not found.');
        }
    } catch(e) {}
}
</script>

</body>
</html>