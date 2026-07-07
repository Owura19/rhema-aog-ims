@extends('layouts.app')

@section('title', 'Live Sermon Mode')

@section('content')

<div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <a href="{{ route('randyimpact.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to RandyImpact AI
        </a>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">⚡ Live Sermon Mode</h2>
        <div style="font-size:13px; color:#64748b;">Real-time Bible verse detection, notes & summary</div>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="{{ route('randyimpact.projector') }}" target="_blank" class="btn-primary" style="background:#1e293b;">
            <i class="fas fa-desktop"></i> Open Projector Screen
        </a>
    </div>
</div>

<div class="grid-3">

    <!-- Column 1 — Microphone & Transcript -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Mic Control -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-microphone" style="color:#dc2626; margin-right:8px;"></i>Microphone</div>
                <span id="status-badge" class="badge badge-gray">Stopped</span>
            </div>
            <div class="card-body" style="text-align:center;">
                <div id="mic-btn" onclick="toggleListening()" style="width:90px; height:90px; border-radius:50%; background:#dc2626; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; cursor:pointer; transition:all 0.3s; box-shadow:0 4px 20px rgba(220,38,38,0.4);">
                    <i class="fas fa-microphone" style="font-size:32px; color:#fff;"></i>
                </div>
                <div id="mic-label" style="font-size:13px; font-weight:600; color:#64748b; margin-bottom:6px;">Click to Start Listening</div>
                <div id="interim-text" style="font-size:12px; color:#94a3b8; min-height:16px; font-style:italic;"></div>

                <!-- Auto Notes Timer -->
                <div id="notes-timer" style="display:none; margin-top:12px; padding:10px; background:#f0fdf4; border-radius:8px; border:1px solid #bbf7d0;">
                    <div style="font-size:12px; color:#15803d; font-weight:600;">
                        <i class="fas fa-sync fa-spin" style="margin-right:4px;"></i>
                        Auto-generating notes in <span id="countdown">120</span>s
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Transcript -->
        <div class="card" style="flex:1;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-file-alt" style="color:#2563eb; margin-right:8px;"></i>Live Transcript</div>
                <button onclick="clearAll()" class="btn-outline btn-sm"><i class="fas fa-trash"></i> Clear</button>
            </div>
            <div class="card-body" style="padding:12px;">
                <div id="transcript" style="min-height:150px; max-height:250px; overflow-y:auto; font-size:13px; color:#374151; line-height:1.8; white-space:pre-wrap;"></div>
            </div>
        </div>

        <!-- Manual Verse Lookup -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-search" style="color:#2563eb; margin-right:8px;"></i>Manual Lookup</div>
            </div>
            <div class="card-body" style="padding:12px;">
                <div style="display:flex; gap:8px;">
                    <input type="text" id="manual-ref" class="form-control" placeholder="e.g. John 3:16" style="font-size:13px;" onkeypress="if(event.key==='Enter') manualLookup()">
                    <button onclick="manualLookup()" class="btn-primary btn-sm" style="white-space:nowrap;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- Column 2 — Detected Verses -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <div class="card" style="flex:1;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-book-open" style="color:#7c3aed; margin-right:8px;"></i>Detected Verses</div>
                <span id="verse-count" class="badge badge-info">0 verses</span>
            </div>
            <div id="verses-container" style="padding:12px; min-height:200px; max-height:500px; overflow-y:auto;">
                <div id="empty-verse-state" style="text-align:center; color:#94a3b8; padding:30px 16px;">
                    <i class="fas fa-microphone" style="font-size:28px; display:block; margin-bottom:10px;"></i>
                    <div style="font-size:13px;">Verses will appear here as pastor speaks</div>
                </div>
            </div>
        </div>

    </div>

    <!-- Column 3 — Live Notes & Summary -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Live Sermon Notes -->
        <div class="card" style="flex:1;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-magic" style="color:#16a34a; margin-right:8px;"></i>Live Sermon Notes</div>
                <button onclick="generateNotesNow()" class="btn-primary btn-sm" style="background:#16a34a;">
                    <i class="fas fa-sync"></i> Now
                </button>
            </div>
            <div class="card-body" style="padding:12px;">
                <input type="text" id="sermon-topic" class="form-control" placeholder="Sermon topic (optional)" style="font-size:13px; margin-bottom:10px;">
                <div id="notes-display" style="min-height:200px; max-height:320px; overflow-y:auto; font-size:13px; color:#374151; line-height:1.8; white-space:pre-wrap; background:#f8fafc; border-radius:8px; padding:12px;">
                    <div style="text-align:center; color:#94a3b8; padding:30px 0;">
                        <i class="fas fa-magic" style="font-size:28px; display:block; margin-bottom:10px;"></i>
                        <div>Notes auto-generate every 2 minutes</div>
                        <div style="font-size:11px; margin-top:6px;">or click Generate Now</div>
                    </div>
                </div>
                <div style="display:flex; gap:8px; margin-top:10px;">
                    <button onclick="copyNotes()" class="btn-outline btn-sm" style="flex:1;"><i class="fas fa-copy"></i> Copy Notes</button>
                    <button onclick="generateSummaryNow()" class="btn-primary btn-sm" style="flex:1; background:#7c3aed;"><i class="fas fa-share"></i> Summary</button>
                </div>
            </div>
        </div>

        <!-- Live Summary -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-align-left" style="color:#e8a020; margin-right:8px;"></i>Live Summary</div>
                <span class="badge badge-warning" style="font-size:11px;">Projector Ready</span>
            </div>
            <div class="card-body" style="padding:12px;">
                <div id="summary-display" style="min-height:80px; font-size:13px; color:#374151; line-height:1.8;">
                    <div style="color:#94a3b8; font-size:12px;">Summary appears here after notes are generated...</div>
                </div>
                <button onclick="copySummary()" class="btn-outline btn-sm" style="margin-top:8px; width:100%;"><i class="fas fa-copy"></i> Copy Summary</button>
            </div>
        </div>

    </div>
</div>

<script>
const csrfToken      = document.querySelector('meta[name="csrf-token"]').content;
let recognition      = null;
let isListening      = false;
let fullTranscript   = '';
let detectedRefs     = new Set();
let projectorChannel = null;
let countdownTimer   = null;
let countdownValue   = 120; // 2 minutes
let currentNotes     = '';

try { projectorChannel = new BroadcastChannel('rhema-projector'); } catch(e) {}

function sendToProjector(data) {
    if (projectorChannel) projectorChannel.postMessage(data);
}

function toggleListening() {
    isListening ? stopListening() : startListening();
}

function startListening() {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        alert('Please use Google Chrome for speech recognition.');
        return;
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();
    recognition.continuous     = true;
    recognition.interimResults = true;
    recognition.lang           = 'en-US';

    recognition.onstart = () => {
        isListening = true;
        document.getElementById('mic-btn').style.background  = '#16a34a';
        document.getElementById('mic-btn').style.boxShadow   = '0 4px 20px rgba(22,163,74,0.4)';
        document.getElementById('mic-label').textContent     = 'Listening... Speak now';
        document.getElementById('status-badge').textContent  = 'Live';
        document.getElementById('status-badge').className    = 'badge badge-danger';
        document.getElementById('notes-timer').style.display = 'block';
        startCountdown();
    };

    recognition.onresult = (event) => {
        let interim = '';
        let final   = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
            if (event.results[i].isFinal) {
                final += event.results[i][0].transcript + ' ';
            } else {
                interim += event.results[i][0].transcript;
            }
        }
        if (final) {
            fullTranscript += final;
            document.getElementById('transcript').textContent = fullTranscript;
            detectVerses(final);
        }
        document.getElementById('interim-text').textContent = interim;
    };

    recognition.onerror = (event) => {
        if (event.error !== 'no-speech') stopListening();
    };

    recognition.onend = () => {
        if (isListening) recognition.start();
    };

    recognition.start();
}

function stopListening() {
    isListening = false;
    if (recognition) recognition.stop();
    clearInterval(countdownTimer);
    document.getElementById('mic-btn').style.background   = '#dc2626';
    document.getElementById('mic-btn').style.boxShadow    = '0 4px 20px rgba(220,38,38,0.4)';
    document.getElementById('mic-label').textContent      = 'Click to Start Listening';
    document.getElementById('status-badge').textContent   = 'Stopped';
    document.getElementById('status-badge').className     = 'badge badge-gray';
    document.getElementById('notes-timer').style.display  = 'none';
    document.getElementById('interim-text').textContent   = '';
}

function startCountdown() {
    countdownValue = 120;
    document.getElementById('countdown').textContent = countdownValue;

    countdownTimer = setInterval(() => {
        countdownValue--;
        document.getElementById('countdown').textContent = countdownValue;
        if (countdownValue <= 0) {
            countdownValue = 120;
            document.getElementById('countdown').textContent = countdownValue;
            if (fullTranscript.trim().length > 50) {
                generateNotesNow();
            }
        }
    }, 1000);
}

async function generateNotesNow() {
    if (!fullTranscript.trim() || fullTranscript.trim().length < 20) return;

    const topic   = document.getElementById('sermon-topic').value.trim();
    const display = document.getElementById('notes-display');
    display.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;"><i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:8px; display:block;"></i>Generating notes...</div>';

    try {
        const res  = await fetch('{{ route("randyimpact.generate-notes") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ transcript: fullTranscript, topic }),
        });
        const data = await res.json();

        if (data.success) {
            currentNotes = data.notes;
            display.textContent = data.notes;

            // Send notes to projector
            sendToProjector({ type: 'notes', data: data.notes });

            // Auto generate summary
            generateSummaryNow();
        } else {
            display.textContent = '❌ ' + data.message;
        }
    } catch(e) {
        display.textContent = '❌ Error: ' + e.message;
    }
}

async function generateSummaryNow() {
    const notes = currentNotes || document.getElementById('notes-display').textContent;
    if (!notes || notes.length < 20) return;

    try {
        const res  = await fetch('{{ route("randyimpact.generate-summary") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ notes }),
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('summary-display').textContent = data.summary;
        }
    } catch(e) {}
}

async function detectVerses(text) {
    try {
        const res  = await fetch('{{ route("randyimpact.detect-verses") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ transcript: text }),
        });
        const data = await res.json();

        if (data.success && data.verses.length > 0) {
            data.verses.forEach(verse => {
                if (!detectedRefs.has(verse.reference)) {
                    detectedRefs.add(verse.reference);
                    addVerseCard(verse);
                    sendToProjector({ type: 'verse', data: verse });
                    updateVerseCount();
                }
            });
        }
    } catch(e) {}
}

function addVerseCard(verse) {
    const container = document.getElementById('verses-container');
    document.getElementById('empty-verse-state')?.remove();

    const card = document.createElement('div');
    card.style.cssText = 'background:#f3e8ff; border-radius:10px; padding:12px 14px; margin-bottom:10px; border-left:4px solid #7c3aed;';
    card.innerHTML = `
        <div style="font-size:11px; font-weight:700; color:#7c3aed; margin-bottom:4px; display:flex; align-items:center; justify-content:space-between;">
            <span><i class="fas fa-book-open" style="margin-right:4px;"></i>${verse.reference}</span>
            <button onclick='sendToProjector({type:"verse",data:${JSON.stringify(verse).replace(/"/g, "&quot;")}})' style="background:#7c3aed; color:#fff; border:none; border-radius:4px; padding:2px 8px; font-size:10px; cursor:pointer;">
                <i class="fas fa-desktop"></i> Project
            </button>
        </div>
        <div style="font-size:12px; color:#1e293b; line-height:1.6; font-style:italic;">${verse.text}</div>
    `;
    container.prepend(card);
}

function updateVerseCount() {
    const count = detectedRefs.size;
    document.getElementById('verse-count').textContent = count + (count === 1 ? ' verse' : ' verses');
}

async function manualLookup() {
    const ref = document.getElementById('manual-ref').value.trim();
    if (!ref) return;
    try {
        const res  = await fetch('{{ route("randyimpact.get-verse") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ reference: ref }),
        });
        const data = await res.json();
        if (data.success) {
            const verse = { reference: data.reference, text: data.text };
            if (!detectedRefs.has(verse.reference)) {
                detectedRefs.add(verse.reference);
                addVerseCard(verse);
                updateVerseCount();
            }
            sendToProjector({ type: 'verse', data: verse });
            document.getElementById('manual-ref').value = '';
        } else {
            alert('Verse not found.');
        }
    } catch(e) {
        alert('Error: ' + e.message);
    }
}

function clearAll() {
    fullTranscript = '';
    detectedRefs.clear();
    currentNotes   = '';
    document.getElementById('transcript').textContent = '';
    document.getElementById('verses-container').innerHTML = `
        <div id="empty-verse-state" style="text-align:center; color:#94a3b8; padding:30px 16px;">
            <i class="fas fa-microphone" style="font-size:28px; display:block; margin-bottom:10px;"></i>
            <div style="font-size:13px;">Verses will appear here as pastor speaks</div>
        </div>`;
    document.getElementById('notes-display').innerHTML = `
        <div style="text-align:center; color:#94a3b8; padding:30px 0;">
            <i class="fas fa-magic" style="font-size:28px; display:block; margin-bottom:10px;"></i>
            <div>Notes auto-generate every 2 minutes</div>
            <div style="font-size:11px; margin-top:6px;">or click Generate Now</div>
        </div>`;
    document.getElementById('summary-display').innerHTML = '<div style="color:#94a3b8; font-size:12px;">Summary appears here after notes are generated...</div>';
    updateVerseCount();
}

function copyNotes() {
    const notes = document.getElementById('notes-display').textContent;
    navigator.clipboard.writeText(notes).then(() => alert('Notes copied!'));
}

function copySummary() {
    const summary = document.getElementById('summary-display').textContent;
    navigator.clipboard.writeText(summary).then(() => alert('Summary copied!'));
}

// CSS animation
const style = document.createElement('style');
style.textContent = '@keyframes slideIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }';
document.head.appendChild(style);
</script>

@endsection