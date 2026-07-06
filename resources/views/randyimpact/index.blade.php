@extends('layouts.app')

@section('title', 'RandyImpact AI')

@section('content')

<div style="margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <h2 style="font-size:24px; font-weight:800; color:#1e293b;">⚡ RandyImpact <span style="color:#e8a020;">AI</span></h2>
        <div style="font-size:13px; color:#64748b;">Bible assistant & sermon tools for Rhema Assembly of God</div>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="{{ route('randyimpact.live-sermon') }}" class="btn-primary" style="background:#dc2626;">
            <i class="fas fa-microphone"></i> Live Sermon Mode
        </a>
        <a href="{{ route('randyimpact.projector') }}" class="btn-primary" style="background:#1e293b;" target="_blank">
            <i class="fas fa-desktop"></i> Projector View
        </a>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">

    <!-- Bible Q&A -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-bible" style="color:#7c3aed; margin-right:8px;"></i>Bible Q&A</div>
        </div>
        <div class="card-body">
            <div style="margin-bottom:12px;">
                <label class="form-label">Ask a Bible Question</label>
                <textarea id="bible-question" class="form-control" rows="3" placeholder="e.g. What does the Bible say about faith? What is the meaning of John 3:16?"></textarea>
            </div>
            <button onclick="askBible()" class="btn-primary" style="width:100%;">
                <i class="fas fa-paper-plane"></i> Ask RandyImpact AI
            </button>
            <div id="bible-answer" style="display:none; margin-top:16px; background:#f8fafc; border-radius:8px; padding:16px; font-size:14px; color:#374151; line-height:1.8; white-space:pre-wrap;"></div>
        </div>
    </div>

    <!-- Verse Lookup -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-search" style="color:#2563eb; margin-right:8px;"></i>Bible Verse Lookup</div>
        </div>
        <div class="card-body">
            <div style="margin-bottom:12px;">
                <label class="form-label">Enter Bible Reference</label>
                <input type="text" id="verse-ref" class="form-control" placeholder="e.g. John 3:16, Romans 8:28, Psalm 23:1">
            </div>
            <button onclick="lookupVerse()" class="btn-primary" style="width:100%;">
                <i class="fas fa-book-open"></i> Find Verse
            </button>
            <div id="verse-result" style="display:none; margin-top:16px;">
                <div id="verse-reference" style="font-size:13px; font-weight:700; color:#7c3aed; margin-bottom:8px;"></div>
                <div id="verse-text" style="font-size:16px; color:#1e293b; line-height:1.8; font-style:italic; background:#f8fafc; padding:16px; border-radius:8px; border-left:4px solid #7c3aed;"></div>
            </div>
        </div>
    </div>

</div>

<!-- Sermon Notes Generator -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-file-alt" style="color:#16a34a; margin-right:8px;"></i>Sermon Notes Generator</div>
        <span class="badge badge-success">AI Powered</span>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:16px;">
            <div>
                <label class="form-label">Sermon Topic (optional)</label>
                <input type="text" id="sermon-topic" class="form-control" placeholder="e.g. The Power of Faith">
            </div>
            <div style="display:flex; align-items:flex-end;">
                <button onclick="generateNotes()" class="btn-primary" style="width:100%; background:#16a34a;">
                    <i class="fas fa-magic"></i> Generate Sermon Notes
                </button>
            </div>
        </div>
        <div>
            <label class="form-label">Sermon Transcript / Key Points</label>
            <textarea id="sermon-transcript" class="form-control" rows="6" placeholder="Paste your sermon transcript, key points, or rough notes here. The AI will generate structured sermon notes from this..."></textarea>
        </div>

        <!-- Notes Result -->
        <div id="notes-result" style="display:none; margin-top:20px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <div style="font-size:15px; font-weight:700; color:#1e293b;"><i class="fas fa-file-alt" style="color:#16a34a; margin-right:8px;"></i>Generated Sermon Notes</div>
                <div style="display:flex; gap:8px;">
                    <button onclick="generateSummary()" class="btn-outline btn-sm"><i class="fas fa-share"></i> Generate Summary</button>
                    <button onclick="copyNotes()" class="btn-primary btn-sm"><i class="fas fa-copy"></i> Copy Notes</button>
                </div>
            </div>
            <div id="notes-content" style="background:#f8fafc; border-radius:8px; padding:20px; font-size:14px; color:#374151; line-height:1.8; white-space:pre-wrap; border:1px solid #e2e8f0;"></div>

            <!-- Summary Result -->
            <div id="summary-result" style="display:none; margin-top:16px; background:#dcfce7; border-radius:8px; padding:16px; border:1px solid #bbf7d0;">
                <div style="font-size:13px; font-weight:700; color:#15803d; margin-bottom:8px;"><i class="fas fa-share-alt"></i> Community Share Summary</div>
                <div id="summary-content" style="font-size:14px; color:#166534; line-height:1.8;"></div>
                <button onclick="copySummary()" class="btn-primary btn-sm" style="margin-top:10px; background:#16a34a;"><i class="fas fa-copy"></i> Copy Summary</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; flex-direction:column; gap:16px;">
    <div style="background:#fff; border-radius:16px; padding:32px 48px; text-align:center;">
        <div style="font-size:32px; margin-bottom:12px;">⚡</div>
        <div style="font-size:16px; font-weight:700; color:#1e293b; margin-bottom:4px;">RandyImpact AI is thinking...</div>
        <div id="loading-text" style="font-size:13px; color:#64748b;">Processing your request</div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function showLoading(text) {
    const overlay = document.getElementById('loading-overlay');
    document.getElementById('loading-text').textContent = text || 'Processing your request...';
    overlay.style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loading-overlay').style.display = 'none';
}

async function askBible() {
    const question = document.getElementById('bible-question').value.trim();
    if (!question) { alert('Please enter a question.'); return; }

    showLoading('Searching the scriptures...');

    try {
        const res = await fetch('{{ route("randyimpact.ask-bible") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ question }),
        });
        const data = await res.json();
        const box  = document.getElementById('bible-answer');
        box.style.display = 'block';
        box.textContent = data.success ? data.answer : '❌ ' + data.message;
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        hideLoading();
    }
}

async function lookupVerse() {
    const ref = document.getElementById('verse-ref').value.trim();
    if (!ref) { alert('Please enter a Bible reference.'); return; }

    showLoading('Looking up the verse...');

    try {
        const res = await fetch('{{ route("randyimpact.get-verse") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ reference: ref }),
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('verse-result').style.display = 'block';
            document.getElementById('verse-reference').textContent = data.reference;
            document.getElementById('verse-text').textContent = data.text;
        } else {
            alert('Verse not found: ' + data.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        hideLoading();
    }
}

async function generateNotes() {
    const transcript = document.getElementById('sermon-transcript').value.trim();
    const topic      = document.getElementById('sermon-topic').value.trim();
    if (!transcript) { alert('Please enter sermon transcript or key points.'); return; }

    showLoading('Generating sermon notes...');

    try {
        const res = await fetch('{{ route("randyimpact.generate-notes") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ transcript, topic }),
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('notes-result').style.display = 'block';
            document.getElementById('notes-content').textContent = data.notes;
            document.getElementById('summary-result').style.display = 'none';
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        hideLoading();
    }
}

async function generateSummary() {
    const notes = document.getElementById('notes-content').textContent.trim();
    if (!notes) { alert('Please generate sermon notes first.'); return; }

    showLoading('Creating community summary...');

    try {
        const res = await fetch('{{ route("randyimpact.generate-summary") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ notes }),
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('summary-result').style.display = 'block';
            document.getElementById('summary-content').textContent = data.summary;
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        hideLoading();
    }
}

function copyNotes() {
    const notes = document.getElementById('notes-content').textContent;
    navigator.clipboard.writeText(notes).then(() => alert('Notes copied to clipboard!'));
}

function copySummary() {
    const summary = document.getElementById('summary-content').textContent;
    navigator.clipboard.writeText(summary).then(() => alert('Summary copied to clipboard!'));
}
</script>

@endsection