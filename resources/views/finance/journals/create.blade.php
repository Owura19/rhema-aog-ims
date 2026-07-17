@extends('layouts.app')

@section('title', 'New Journal Entry')

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('finance.journals.index') }}" style="color:#64748b;text-decoration:none;font-size:13px;"><i class="fas fa-arrow-left"></i> Back to Journal Entries</a>
</div>

@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;"><i class="fas fa-triangle-exclamation"></i> {{ session('error') }}</div>
@endif

<div class="card" style="max-width:860px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-book" style="color:#7c3aed;margin-right:8px;"></i>New Journal Entry</div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('finance.journals.store') }}" id="journalForm">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;margin-bottom:20px;">
                <div>
                    <label class="form-label">Date <span style="color:red;">*</span></label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Description / Reason <span style="color:red;">*</span></label>
                    <input type="text" name="description" value="{{ old('description') }}" class="form-control" placeholder="e.g. Correction of misposted offering" required>
                </div>
            </div>

            <label class="form-label">Journal Lines <span style="color:red;">*</span></label>
            <div style="font-size:12px;color:#94a3b8;margin-bottom:10px;">Each line is a debit OR a credit. Total debits must equal total credits.</div>

            <table class="table" id="linesTable" style="margin-bottom:8px;">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th style="width:160px;text-align:right;">Debit (GHS)</th>
                        <th style="width:160px;text-align:right;">Credit (GHS)</th>
                        <th style="width:44px;"></th>
                    </tr>
                </thead>
                <tbody id="linesBody">
                    <!-- rows injected by JS; two to start -->
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid #e2e8f0;font-weight:800;">
                        <td style="text-align:right;">TOTALS</td>
                        <td style="text-align:right;"><span id="totalDebit">0.00</span></td>
                        <td style="text-align:right;"><span id="totalCredit">0.00</span></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align:center;padding-top:10px;">
                            <span id="balanceMsg" style="font-size:13px;font-weight:600;"></span>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <button type="button" class="btn-outline btn-sm" id="addLine" style="margin-bottom:20px;"><i class="fas fa-plus"></i> Add Line</button>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-primary" id="submitBtn"><i class="fas fa-check"></i> Post Journal Entry</button>
                <a href="{{ route('finance.journals.index') }}" class="btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    const accounts = @json($accounts->map(fn($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name.' ('.$a->type.')']));
    let rowIndex = 0;

    function accountOptions() {
        let opts = '<option value="">Select account</option>';
        accounts.forEach(a => { opts += `<option value="${a.id}">${a.label}</option>`; });
        return opts;
    }

    function addRow() {
        const i = rowIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="lines[${i}][account_id]" class="form-control">${accountOptions()}</select></td>
            <td><input type="number" step="0.01" min="0" name="lines[${i}][debit]" class="form-control debit" style="text-align:right;" placeholder="0.00"></td>
            <td><input type="number" step="0.01" min="0" name="lines[${i}][credit]" class="form-control credit" style="text-align:right;" placeholder="0.00"></td>
            <td style="text-align:center;"><button type="button" class="removeRow" style="background:none;border:none;color:#dc2626;cursor:pointer;"><i class="fas fa-times"></i></button></td>
        `;
        document.getElementById('linesBody').appendChild(tr);
        attachEvents(tr);
    }

    function attachEvents(tr) {
        tr.querySelectorAll('.debit, .credit').forEach(inp => inp.addEventListener('input', recalc));
        tr.querySelector('.removeRow').addEventListener('click', () => { tr.remove(); recalc(); });
    }

    function recalc() {
        let td = 0, tc = 0;
        document.querySelectorAll('.debit').forEach(i => td += parseFloat(i.value) || 0);
        document.querySelectorAll('.credit').forEach(i => tc += parseFloat(i.value) || 0);
        document.getElementById('totalDebit').textContent = td.toFixed(2);
        document.getElementById('totalCredit').textContent = tc.toFixed(2);

        const msg = document.getElementById('balanceMsg');
        const btn = document.getElementById('submitBtn');
        if (td === 0 && tc === 0) {
            msg.textContent = ''; btn.disabled = false;
        } else if (Math.abs(td - tc) < 0.005) {
            msg.textContent = '✓ Balanced'; msg.style.color = '#16a34a'; btn.disabled = false;
        } else {
            msg.textContent = `Out of balance by GHS ${Math.abs(td - tc).toFixed(2)}`; msg.style.color = '#dc2626'; btn.disabled = true;
        }
    }

    document.getElementById('addLine').addEventListener('click', addRow);
    // start with two rows
    addRow(); addRow();
</script>

@endsection