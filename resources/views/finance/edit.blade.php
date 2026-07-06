@extends('layouts.app')

@section('title', 'Edit Transaction')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('finance.show', $transaction) }}" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to {{ $transaction->reference }}
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Edit Transaction — {{ $transaction->reference }}</h2>
</div>

<form method="POST" action="{{ route('finance.update', $transaction) }}">
@csrf
@method('PUT')

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-money-bill-wave" style="color:#16a34a; margin-right:8px;"></i>Transaction Details</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div>
    <label class="form-label">Transaction Type <span style="color:red;">*</span></label>
    <select name="type" class="form-control" onchange="setCategory(this.value); showSubcategory(this.value)">
        @foreach(['Tithe','Offering','First Fruit','Seed','Pledge','Donation','Expense','Other'] as $type)
            <option value="{{ $type }}" {{ old('type', $transaction->type) == $type ? 'selected' : '' }}>{{ $type }}</option>
        @endforeach
    </select>
</div>

<div id="subcategory-field" style="display:none;">
    <label class="form-label">Subcategory</label>
    <select name="subcategory" id="subcategory-select" class="form-control">
        <option value="">Select subcategory</option>
    </select>
</div>

            <div>
                <label class="form-label">Category <span style="color:red;">*</span></label>
                <select name="category" id="category" class="form-control">
                    <option value="Income" {{ old('category', $transaction->category) == 'Income' ? 'selected' : '' }}>Income</option>
                    <option value="Expense" {{ old('category', $transaction->category) == 'Expense' ? 'selected' : '' }}>Expense</option>
                </select>
            </div>

            <div>
                <label class="form-label">Amount (GHS) <span style="color:red;">*</span></label>
                <input type="number" name="amount" value="{{ old('amount', $transaction->amount) }}" step="0.01" min="0.01" class="form-control">
            </div>

            <div>
                <label class="form-label">Transaction Date <span style="color:red;">*</span></label>
                <input type="date" name="transaction_date" value="{{ old('transaction_date', $transaction->transaction_date->format('Y-m-d')) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Payment Method <span style="color:red;">*</span></label>
                <select name="payment_method" class="form-control" onchange="showPaymentFields(this.value)">
                    @foreach(['Cash','Mobile Money','Bank Transfer','Cheque','Other'] as $method)
                        <option value="{{ $method }}" {{ old('payment_method', $transaction->payment_method) == $method ? 'selected' : '' }}>{{ $method }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Status <span style="color:red;">*</span></label>
                <select name="status" class="form-control">
                    @foreach(['Confirmed','Pending','Cancelled'] as $status)
                        <option value="{{ $status }}" {{ old('status', $transaction->status) == $status ? 'selected' : '' }}>{{ $status }}</option>
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
                        <option value="{{ $member->id }}" {{ old('member_id', $transaction->member_id) == $member->id ? 'selected' : '' }}>
                            {{ $member->full_name }} ({{ $member->member_id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Payer Name (if not a member)</label>
                <input type="text" name="payer_name" value="{{ old('payer_name', $transaction->payer_name) }}" class="form-control">
            </div>

            <div>
                <label class="form-label">Related Service</label>
                <select name="church_service_id" class="form-control">
                    <option value="">Not linked to a service</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" {{ old('church_service_id', $transaction->church_service_id) == $service->id ? 'selected' : '' }}>
                            {{ $service->name }} — {{ $service->service_date->format('M d, Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

        </div>
    </div>
</div>

<!-- Payment Details -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-credit-card" style="color:#7c3aed; margin-right:8px;"></i>Payment Details</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

            <div id="momo-field" style="display:none;">
                <label class="form-label">Mobile Money Number</label>
                <input type="text" name="mobile_money_number" value="{{ old('mobile_money_number', $transaction->mobile_money_number) }}" class="form-control">
            </div>

            <div id="cheque-field" style="display:none;">
                <label class="form-label">Cheque Number</label>
                <input type="text" name="cheque_number" value="{{ old('cheque_number', $transaction->cheque_number) }}" class="form-control">
            </div>

            <div id="bank-field" style="display:none;">
                <label class="form-label">Bank Name</label>
                <input type="text" name="bank_name" value="{{ old('bank_name', $transaction->bank_name) }}" class="form-control">
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Description / Notes</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $transaction->description) }}</textarea>
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Update Transaction
    </button>
    <a href="{{ route('finance.show', $transaction) }}" class="btn-outline">
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

showPaymentFields('{{ old('payment_method', $transaction->payment_method) }}');

const subcategories = {
    'Expense': [
        'Funeral Dept',
        'Transport (Bus)',
        'Wednesday Prayer',
        'Welfare',
        'Women Ministry',
        'Scholarship & Needy',
        'General Expense',
        "Rt. Pastor's Pension Payments",
        'Salaries & Staff Allowance',
        'SSNIT/2nd Tier/PAYE',
        'Travel & Transport',
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
    const field    = document.getElementById('subcategory-field');
    const select   = document.getElementById('subcategory-select');
    const options  = subcategories[type] || [];
    const existing = '{{ old('subcategory', $transaction->subcategory) }}';

    if (options.length > 0) {
        field.style.display = 'block';
        select.innerHTML    = '<option value="">Select subcategory</option>';
        options.forEach(opt => {
            const selected = opt === existing ? 'selected' : '';
            select.innerHTML += `<option value="${opt}" ${selected}>${opt}</option>`;
        });
    } else {
        field.style.display = 'none';
        select.innerHTML    = '<option value="">Select subcategory</option>';
    }
}

// Run on load
showSubcategory('{{ old('type', $transaction->type) }}');
</script>

@endsection