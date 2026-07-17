@extends('layouts.app')

@section('title', 'Record Transaction')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('finance.index') }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Finance
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Record New Transaction</h2>
</div>

<form method="POST" action="{{ route('finance.store') }}">
@csrf

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-money-bill-wave" style="color:#16a34a; margin-right:8px;"></i>Transaction Details</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

           <div>
    <label class="form-label">Transaction Type <span style="color:red;">*</span></label>
    <select name="type" class="form-control {{ $errors->has('type') ? 'is-invalid' : '' }}" onchange="setCategory(this.value); showSubcategory(this.value)">
        <option value="">Select type</option>
        @foreach(['Tithe','Offering','First Fruit','Seed','Pledge','Donation','Expense','Other'] as $type)
            <option value="{{ $type }}" {{ old('type') == $type ? 'selected' : '' }}>{{ $type }}</option>
        @endforeach
    </select>
    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div id="subcategory-field" style="display:none;">
    <label class="form-label">Subcategory <span style="color:red;">*</span></label>
    <select name="subcategory" id="subcategory-select" class="form-control">
        <option value="">Select subcategory</option>
    </select>
</div>

<div>
    <label class="form-label">Post to Account <span style="color:red;">*</span></label>
    <select name="account_id" class="form-control {{ $errors->has('account_id') ? 'is-invalid' : '' }}">
        <option value="">Select account</option>
        @php
            $grouped = $accounts->groupBy(fn($a) => optional($a->parent)->name ?? 'Other');
        @endphp
        @foreach($accounts->whereNotNull('parent_id')->groupBy('parent_id') as $parentId => $group)
            <optgroup label="{{ optional($group->first()->parent)->name }}">
                @foreach($group as $acct)
                    <option value="{{ $acct->id }}" {{ old('account_id') == $acct->id ? 'selected' : '' }}>
                        {{ $acct->code }} — {{ $acct->name }}
                    </option>
                @endforeach
            </optgroup>
        @endforeach
        @foreach($accounts->where('parent_id', null) as $acct)
            <option value="{{ $acct->id }}" {{ old('account_id') == $acct->id ? 'selected' : '' }}>
                {{ $acct->code }} — {{ $acct->name }}
            </option>
        @endforeach
    </select>
    @error('account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div>
    <label class="form-label">Paid To / From (Cash or Bank) <span style="color:red;">*</span></label>
    <select name="cash_account_id" class="form-control {{ $errors->has('cash_account_id') ? 'is-invalid' : '' }}">
        <option value="">Select cash / bank account</option>
        @foreach($cashAccounts as $ca)
            <option value="{{ $ca->id }}" {{ old('cash_account_id') == $ca->id ? 'selected' : '' }}>
                {{ $ca->code }} — {{ $ca->name }}
            </option>
        @endforeach
    </select>
    <small style="color:#94a3b8; font-size:12px;">Where the money was received into, or paid out from.</small>
    @error('cash_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

            <div>
                <label class="form-label">Category <span style="color:red;">*</span></label>
                <select name="category" id="category" class="form-control {{ $errors->has('category') ? 'is-invalid' : '' }}" onchange="onCategoryChange(this.value)">
    <option value="Income" {{ old('category', 'Income') == 'Income' ? 'selected' : '' }}>Income</option>
    <option value="Expense" {{ old('category') == 'Expense' ? 'selected' : '' }}>Expense</option>
    <option value="Asset" {{ old('category') == 'Asset' ? 'selected' : '' }}>Asset (e.g. buy/sell equipment)</option>
    <option value="Liability" {{ old('category') == 'Liability' ? 'selected' : '' }}>Liability (e.g. loan)</option>
</select>
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div id="direction-field" style="display:none;">
    <label class="form-label">Direction <span style="color:red;">*</span></label>
    <select name="direction" id="direction" class="form-control">
        <option value="in">Money coming IN (received)</option>
        <option value="out">Money going OUT (paid)</option>
    </select>
    <small style="color:#94a3b8; font-size:12px;">e.g. buying equipment = OUT; taking a loan = IN.</small>
</div>

            <div>
                <label class="form-label">Amount (GHS) <span style="color:red;">*</span></label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" class="form-control {{ $errors->has('amount') ? 'is-invalid' : '' }}" placeholder="0.00">
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Transaction Date <span style="color:red;">*</span></label>
                <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->format('Y-m-d')) }}" class="form-control {{ $errors->has('transaction_date') ? 'is-invalid' : '' }}">
                @error('transaction_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Payment Method <span style="color:red;">*</span></label>
                <select name="payment_method" class="form-control {{ $errors->has('payment_method') ? 'is-invalid' : '' }}" onchange="showPaymentFields(this.value)">
                    @foreach(['Cash','Mobile Money','Bank Transfer','Cheque','Other'] as $method)
                        <option value="{{ $method }}" {{ old('payment_method', 'Cash') == $method ? 'selected' : '' }}>{{ $method }}</option>
                    @endforeach
                </select>
                @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Status <span style="color:red;">*</span></label>
                <select name="status" class="form-control">
                    @foreach(['Confirmed','Pending','Cancelled'] as $status)
                        <option value="{{ $status }}" {{ old('status', 'Confirmed') == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>

        </div>
    </div>
</div>

<!-- Payer Information -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user" style="color:#2563eb; margin-right:8px;"></i>Payer Information</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px;">

            <div>
                <label class="form-label">Member (if registered)</label>
                <select name="member_id" class="form-control">
                    <option value="">Anonymous / Walk-in</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}" {{ old('member_id') == $member->id ? 'selected' : '' }}>
                            {{ $member->full_name }} ({{ $member->member_id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Payer Name (if not a member)</label>
                <input type="text" name="payer_name" value="{{ old('payer_name') }}" class="form-control" placeholder="Full name of payer">
            </div>

            <div>
                <label class="form-label">Related Service</label>
                <select name="church_service_id" class="form-control">
                    <option value="">Not linked to a service</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" {{ old('church_service_id') == $service->id ? 'selected' : '' }}>
                            {{ $service->name }} — {{ $service->service_date->format('M d, Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

        </div>
    </div>
</div>

<!-- Payment Details -->
<div class="card" style="margin-bottom:20px;" id="payment-details">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-credit-card" style="color:#7c3aed; margin-right:8px;"></i>Payment Details</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div id="momo-field" style="display:none;">
                <label class="form-label">Mobile Money Number</label>
                <input type="text" name="mobile_money_number" value="{{ old('mobile_money_number') }}" class="form-control" placeholder="e.g. 0244000000">
            </div>

            <div id="cheque-field" style="display:none;">
                <label class="form-label">Cheque Number</label>
                <input type="text" name="cheque_number" value="{{ old('cheque_number') }}" class="form-control" placeholder="Cheque number">
            </div>

            <div id="bank-field" style="display:none;">
                <label class="form-label">Bank Name</label>
                <input type="text" name="bank_name" value="{{ old('bank_name') }}" class="form-control" placeholder="e.g. GCB Bank">
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Description / Notes</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Any additional notes about this transaction...">{{ old('description') }}</textarea>
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Record Transaction
    </button>
    <a href="{{ route('finance.index') }}" class="btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

</form>

<script>
function setCategory(type) {
    const cat = document.getElementById('category');
    if (type === 'Expense') {
        cat.value = 'Expense';
    } else {
        cat.value = 'Income';
    }
}

function showPaymentFields(method) {
    document.getElementById('momo-field').style.display   = method === 'Mobile Money' ? 'block' : 'none';
    document.getElementById('cheque-field').style.display = method === 'Cheque' ? 'block' : 'none';
    document.getElementById('bank-field').style.display   = (method === 'Bank Transfer' || method === 'Cheque') ? 'block' : 'none';
}

// Balance-sheet accounts for Asset/Liability transactions
const balanceSheetAccounts = @json($balanceSheetAccounts->map(fn($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name.' ('.$a->type.')']));
const incomeExpenseAccounts = @json($accounts->map(fn($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name]));

function onCategoryChange(cat) {
    const dirField = document.getElementById('direction-field');
    const acctSelect = document.querySelector('select[name="account_id"]');
    const isBalanceSheet = (cat === 'Asset' || cat === 'Liability');

    // Show/hide the Direction field
    dirField.style.display = isBalanceSheet ? 'block' : 'none';

    // Swap the "Post to Account" options
    const list = isBalanceSheet ? balanceSheetAccounts.filter(a => a.label.includes('('+cat+')')) : incomeExpenseAccounts;
    acctSelect.innerHTML = '<option value="">Select account</option>';
    list.forEach(a => { acctSelect.innerHTML += `<option value="${a.id}">${a.label}</option>`; });
}

// Run on load
showPaymentFields('{{ old('payment_method', 'Cash') }}');

const subcategories = {
    'Expense': [
        // B1 — Department Expenses
        'Funeral Dept',
        'Transport (Bus)',
        'Wednesday Prayer',
        'Welfare',
        'Women Ministry',
        'Scholarship & Needy',
        // B2 — Administration
        'General Expense',
        "Rt. Pastor's Pension Payments",
        'Salaries & Staff Allowance',
        'SSNIT/2nd Tier/PAYE',
        'Travel & Transport',
        // B3 — Other Expenses
        'Cleaning & Sanitation',
        'Gen. Coun/Tithe on Tithe',
        'Donation (Expense)',
        'Internet & Comm Cost',
        'Medicals',
        'Refreshments',
        'Repairs & Maintenance',
        'Retreat/Revival/Seminar',
        'Printing & Stationery',
        'Utility Bills',
        'School Fees',
        'Security & Police on Duty',
        'Satellite Church',
    ],
    'Offering': [
        'Executive (English) Service',
        'Divine (Twi) Service',
        'Joint Service',
        'Bible Studies - Tuesday',
        'Miracle Service - Friday',
        'Fundraisings',
    ],
    'Donation': [
        'Men Ministry',
        'Women Ministry',
        'Children Ministry',
        'Sunday School',
        'Funeral Dept.',
        'Christ Ambassador (CA)',
        'Welfare Dept.',
        'Prayer Mtg (Wednesday)',
    ],
    'Other': [
        'Dist/Reg/Gen. Council',
        'Fund Raising',
        'Child Dedication',
        'All Night',
        'Satellite Churches',
        'Revival/Retreat/Seminars',
        'Scholarship Fund',
        'Book Sales (Sunday School)',
        'Missions',
        'Joy Fellowship',
        'Interest Received',
    ],
};

function showSubcategory(type) {
    const field  = document.getElementById('subcategory-field');
    const select = document.getElementById('subcategory-select');
    const options = subcategories[type] || [];

    if (options.length > 0) {
        field.style.display = 'block';
        select.innerHTML = '<option value="">Select subcategory</option>';
        options.forEach(opt => {
            select.innerHTML += `<option value="${opt}">${opt}</option>`;
        });
    } else {
        field.style.display = 'none';
        select.innerHTML = '<option value="">Select subcategory</option>';
    }
}

// Run on load for old values
showSubcategory('{{ old('type', '') }}');
</script>

@endsection