<?php $__env->startSection('title', 'Record Transaction'); ?>

<?php $__env->startSection('content'); ?>

<div style="margin-bottom:20px;">
    <a href="<?php echo e(route('finance.index')); ?>" style="color:#64748b; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Finance
    </a>
    <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-top:4px;">Record New Transaction</h2>
</div>

<form method="POST" action="<?php echo e(route('finance.store')); ?>">
<?php echo csrf_field(); ?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-money-bill-wave" style="color:#16a34a; margin-right:8px;"></i>Transaction Details</div>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">

           <div>
    <label class="form-label">Transaction Type <span style="color:red;">*</span></label>
    <select name="type" class="form-control <?php echo e($errors->has('type') ? 'is-invalid' : ''); ?>" onchange="setCategory(this.value); showSubcategory(this.value)">
        <option value="">Select type</option>
        <?php $__currentLoopData = ['Tithe','Offering','First Fruit','Seed','Pledge','Donation','Expense','Other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($type); ?>" <?php echo e(old('type') == $type ? 'selected' : ''); ?>><?php echo e($type); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <?php $__errorArgs = ['type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
</div>

<div id="subcategory-field" style="display:none;">
    <label class="form-label">Subcategory <span style="color:red;">*</span></label>
    <select name="subcategory" id="subcategory-select" class="form-control">
        <option value="">Select subcategory</option>
    </select>
</div>

            <div>
                <label class="form-label">Category <span style="color:red;">*</span></label>
                <select name="category" id="category" class="form-control <?php echo e($errors->has('category') ? 'is-invalid' : ''); ?>">
                    <option value="Income" <?php echo e(old('category', 'Income') == 'Income' ? 'selected' : ''); ?>>Income</option>
                    <option value="Expense" <?php echo e(old('category') == 'Expense' ? 'selected' : ''); ?>>Expense</option>
                </select>
                <?php $__errorArgs = ['category'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div>
                <label class="form-label">Amount (GHS) <span style="color:red;">*</span></label>
                <input type="number" name="amount" value="<?php echo e(old('amount')); ?>" step="0.01" min="0.01" class="form-control <?php echo e($errors->has('amount') ? 'is-invalid' : ''); ?>" placeholder="0.00">
                <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div>
                <label class="form-label">Transaction Date <span style="color:red;">*</span></label>
                <input type="date" name="transaction_date" value="<?php echo e(old('transaction_date', now()->format('Y-m-d'))); ?>" class="form-control <?php echo e($errors->has('transaction_date') ? 'is-invalid' : ''); ?>">
                <?php $__errorArgs = ['transaction_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div>
                <label class="form-label">Payment Method <span style="color:red;">*</span></label>
                <select name="payment_method" class="form-control <?php echo e($errors->has('payment_method') ? 'is-invalid' : ''); ?>" onchange="showPaymentFields(this.value)">
                    <?php $__currentLoopData = ['Cash','Mobile Money','Bank Transfer','Cheque','Other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($method); ?>" <?php echo e(old('payment_method', 'Cash') == $method ? 'selected' : ''); ?>><?php echo e($method); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php $__errorArgs = ['payment_method'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div>
                <label class="form-label">Status <span style="color:red;">*</span></label>
                <select name="status" class="form-control">
                    <?php $__currentLoopData = ['Confirmed','Pending','Cancelled']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($status); ?>" <?php echo e(old('status', 'Confirmed') == $status ? 'selected' : ''); ?>><?php echo e($status); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                    <?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($member->id); ?>" <?php echo e(old('member_id') == $member->id ? 'selected' : ''); ?>>
                            <?php echo e($member->full_name); ?> (<?php echo e($member->member_id); ?>)
                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            <div>
                <label class="form-label">Payer Name (if not a member)</label>
                <input type="text" name="payer_name" value="<?php echo e(old('payer_name')); ?>" class="form-control" placeholder="Full name of payer">
            </div>

            <div>
                <label class="form-label">Related Service</label>
                <select name="church_service_id" class="form-control">
                    <option value="">Not linked to a service</option>
                    <?php $__currentLoopData = $services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($service->id); ?>" <?php echo e(old('church_service_id') == $service->id ? 'selected' : ''); ?>>
                            <?php echo e($service->name); ?> — <?php echo e($service->service_date->format('M d, Y')); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                <input type="text" name="mobile_money_number" value="<?php echo e(old('mobile_money_number')); ?>" class="form-control" placeholder="e.g. 0244000000">
            </div>

            <div id="cheque-field" style="display:none;">
                <label class="form-label">Cheque Number</label>
                <input type="text" name="cheque_number" value="<?php echo e(old('cheque_number')); ?>" class="form-control" placeholder="Cheque number">
            </div>

            <div id="bank-field" style="display:none;">
                <label class="form-label">Bank Name</label>
                <input type="text" name="bank_name" value="<?php echo e(old('bank_name')); ?>" class="form-control" placeholder="e.g. GCB Bank">
            </div>

            <div style="grid-column:span 3;">
                <label class="form-label">Description / Notes</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Any additional notes about this transaction..."><?php echo e(old('description')); ?></textarea>
            </div>

        </div>
    </div>
</div>

<div style="display:flex; gap:12px;">
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Record Transaction
    </button>
    <a href="<?php echo e(route('finance.index')); ?>" class="btn-outline">
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

// Run on load
showPaymentFields('<?php echo e(old('payment_method', 'Cash')); ?>');

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
showSubcategory('<?php echo e(old('type', '')); ?>');
</script>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\user\Herd\rhema-aog-ims\resources\views/finance/create.blade.php ENDPATH**/ ?>