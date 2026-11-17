{{--
    Harvest money: which harvest campaign, and which of the payer's pledges it pays into.
    Shown only when the type is "Harvest". Expects $harvests, and optionally $selectedHarvest,
    $selectedPledge and $initialType.
--}}
@php
    $selectedHarvest = old('harvest_id', $selectedHarvest ?? null);
    $selectedPledge  = old('pledge_id', $selectedPledge ?? null);
    $isHarvest       = old('type', $initialType ?? '') === 'Harvest';
@endphp
<div class="card" id="harvest-card" style="margin-bottom:20px; {{ $isHarvest ? '' : 'display:none;' }}">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-wheat-awn" style="color:#ca8a04; margin-right:8px;"></i>Harvest</div>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <label class="form-label">Harvest campaign <span style="color:red;">*</span></label>
                <select name="harvest_id" id="harvest-select" class="form-control {{ $errors->has('harvest_id') ? 'is-invalid' : '' }}">
                    <option value="">Select harvest</option>
                    @foreach($harvests as $h)
                        <option value="{{ $h->id }}" {{ (string) $selectedHarvest === (string) $h->id ? 'selected' : '' }}>{{ $h->name }} ({{ $h->year }})</option>
                    @endforeach
                </select>
                @error('harvest_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small style="color:#94a3b8; font-size:12px;">The money is added to the payer's pledge on this harvest, so it shows on the Harvest page.</small>
            </div>
            <div>
                <label class="form-label">Pledge</label>
                <div id="harvest-pledges" data-selected="{{ $selectedPledge }}" style="font-size:13px; color:#64748b;">
                    Choose the harvest and the member (or payer name) to see their pledge.
                </div>
                @error('pledge_id')<div class="invalid-feedback" style="display:block;">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const card    = document.getElementById('harvest-card');
    const box     = document.getElementById('harvest-pledges');
    const harvest = document.getElementById('harvest-select');
    const type    = document.querySelector('select[name="type"]');
    const member  = document.querySelector('select[name="member_id"]');
    const payer   = document.querySelector('input[name="payer_name"]');
    const url     = @json(route('finance.harvest-pledges'));
    let selected  = box.dataset.selected || '';
    let timer;

    const money = n => 'GH₵ ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const text  = (tag, value, style) => { const el = document.createElement(tag); el.textContent = value; if (style) el.style.cssText = style; return el; };

    function render(data) {
        box.innerHTML = '';
        const real  = data.pledges.filter(p => !p.giver);
        const giver = data.pledges.find(p => p.giver);

        if (data.rule === 'giver') {
            box.appendChild(text('div', giver
                ? 'No pledge on this harvest. The money is added to their harvest giver record ' + giver.reference + '.'
                : 'No pledge on this harvest. It will be recorded as a harvest giver (a pledge equal to what was given).'));
            return;
        }
        if (data.rule === 'choose') {
            box.appendChild(text('div', 'More than one pledge: choose the one this money is for.', 'color:#b45309; margin-bottom:6px;'));
        }
        const table = document.createElement('table');
        table.className = 'table';
        table.style.cssText = 'font-size:13px; margin:0;';
        const head = table.createTHead().insertRow();
        ['', 'Pledge', 'Pledged', 'Paid', 'Balance'].forEach(h => head.appendChild(text('th', h)));
        const body = table.createTBody();
        real.forEach(p => {
            const row   = body.insertRow();
            const radio = document.createElement('input');
            radio.type = 'radio'; radio.name = 'pledge_id'; radio.value = p.id;
            radio.checked = data.rule === 'single' || String(p.id) === String(selected);
            radio.setAttribute('aria-label', 'Pledge ' + p.reference);
            radio.addEventListener('change', () => { selected = String(p.id); });
            row.insertCell().appendChild(radio);
            row.appendChild(text('td', p.reference, 'font-family:monospace;'));
            row.appendChild(text('td', money(p.pledged), 'white-space:nowrap;'));
            row.appendChild(text('td', money(p.paid), 'white-space:nowrap;'));
            row.appendChild(text('td', money(p.balance), 'white-space:nowrap;'));
        });
        const wrap = document.createElement('div');
        wrap.className = 'table-wrap';
        wrap.appendChild(table);
        box.appendChild(wrap);
    }

    function load() {
        const isHarvest = type && type.value === 'Harvest';
        card.style.display = isHarvest ? '' : 'none';
        if (!isHarvest || !harvest.value || (!member.value && !payer.value.trim())) {
            box.textContent = 'Choose the harvest and the member (or payer name) to see their pledge.';
            return;
        }
        const q = new URLSearchParams({ harvest_id: harvest.value, member_id: member.value, payer_name: payer.value.trim() });
        box.textContent = 'Loading…';
        fetch(url + '?' + q, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(render)
            .catch(() => { box.textContent = 'Could not load the pledges. You can still save: a single pledge is picked automatically.'; });
    }

    [type, harvest, member].forEach(el => el && el.addEventListener('change', () => { if (el !== harvest) selected = ''; load(); }));
    payer && payer.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 400); });
    load();
})();
</script>
