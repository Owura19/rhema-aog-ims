<?php $__env->startSection('title', 'Finance'); ?>

<?php $__env->startSection('content'); ?>

<!-- Stats Row -->
<div style="display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-arrow-up" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS <?php echo e(number_format($stats['total_income'], 2)); ?></div>
            <div class="stat-label">Income This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;">
            <i class="fas fa-arrow-down" style="color:#dc2626;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS <?php echo e(number_format($stats['total_expense'], 2)); ?></div>
            <div class="stat-label">Expenses This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-hand-holding-heart" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS <?php echo e(number_format($stats['total_tithes'], 2)); ?></div>
            <div class="stat-label">Tithes This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-church" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS <?php echo e(number_format($stats['total_offerings'], 2)); ?></div>
            <div class="stat-label">Offerings This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:<?php echo e($stats['net_balance'] >= 0 ? '#dcfce7' : '#fee2e2'); ?>;">
            <i class="fas fa-balance-scale" style="color:<?php echo e($stats['net_balance'] >= 0 ? '#16a34a' : '#dc2626'); ?>;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px; color:<?php echo e($stats['net_balance'] >= 0 ? '#16a34a' : '#dc2626'); ?>;">GHS <?php echo e(number_format($stats['net_balance'], 2)); ?></div>
            <div class="stat-label">Net Balance</div>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
   <div class="card-header">
    <div class="card-title"><i class="fas fa-money-bill-wave" style="color:#16a34a; margin-right:8px;"></i>Transactions</div>
    <div style="display:flex; gap:10px;">
        <a href="<?php echo e(route('finance.report')); ?>" class="btn-outline btn-sm"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="<?php echo e(route('finance.export', ['year' => now()->year])); ?>" class="btn-primary btn-sm" style="background:#16a34a;">
            <i class="fas fa-file-excel"></i> Export Excel
        </a>
        <a href="<?php echo e(route('finance.create')); ?>" class="btn-primary"><i class="fas fa-plus"></i> Record Transaction</a>
    </div>
</div>

    <!-- Filters -->
    <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <form method="GET" action="<?php echo e(route('finance.index')); ?>" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Search reference, name..." class="form-control" style="width:220px;">
            <select name="type" class="form-control" style="width:150px;">
                <option value="">All Types</option>
                <?php $__currentLoopData = ['Tithe','Offering','First Fruit','Seed','Pledge','Donation','Expense','Other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($type); ?>" <?php echo e(request('type') == $type ? 'selected' : ''); ?>><?php echo e($type); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <select name="category" class="form-control" style="width:130px;">
                <option value="">All Categories</option>
                <option value="Income" <?php echo e(request('category') == 'Income' ? 'selected' : ''); ?>>Income</option>
                <option value="Expense" <?php echo e(request('category') == 'Expense' ? 'selected' : ''); ?>>Expense</option>
            </select>
            <select name="payment_method" class="form-control" style="width:160px;">
                <option value="">All Methods</option>
                <?php $__currentLoopData = ['Cash','Mobile Money','Bank Transfer','Cheque','Other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($method); ?>" <?php echo e(request('payment_method') == $method ? 'selected' : ''); ?>><?php echo e($method); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <input type="date" name="date_from" value="<?php echo e(request('date_from')); ?>" class="form-control" style="width:150px;">
            <input type="date" name="date_to" value="<?php echo e(request('date_to')); ?>" class="form-control" style="width:150px;">
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filter</button>
            <?php if(request()->hasAny(['search','type','category','payment_method','date_from','date_to'])): ?>
                <a href="<?php echo e(route('finance.index')); ?>" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Payer</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td>
                        <span style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:3px 8px; border-radius:4px;"><?php echo e($transaction->reference); ?></span>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:13px;"><?php echo e($transaction->payer_label); ?></div>
                        <?php if($transaction->churchService): ?>
                            <div style="font-size:11px; color:#94a3b8;"><?php echo e($transaction->churchService->name); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo e($transaction->category === 'Income' ? 'badge-success' : 'badge-danger'); ?>">
                            <?php echo e($transaction->type); ?>

                        </span>
                    </td>
                    <td>
                        <span style="font-weight:700; font-size:15px; color:<?php echo e($transaction->category === 'Income' ? '#16a34a' : '#dc2626'); ?>;">
                            <?php echo e($transaction->category === 'Expense' ? '-' : '+'); ?> GHS <?php echo e(number_format($transaction->amount, 2)); ?>

                        </span>
                    </td>
                    <td style="font-size:13px; color:#64748b;"><?php echo e($transaction->payment_method); ?></td>
                    <td style="font-size:13px; color:#64748b;"><?php echo e($transaction->transaction_date->format('M d, Y')); ?></td>
                    <td>
                        <?php if($transaction->status === 'Confirmed'): ?>
                            <span class="badge badge-success">Confirmed</span>
                        <?php elseif($transaction->status === 'Pending'): ?>
                            <span class="badge badge-warning">Pending</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Cancelled</span>
                        <?php endif; ?>
                    </td>
                   <td>
    <div style="display:flex; gap:6px;">
        <a href="<?php echo e(route('finance.show', $transaction)); ?>" class="btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
        <a href="<?php echo e(route('finance.receipt', ['transaction' => $transaction->id])); ?>" class="btn-primary btn-sm" style="background:#16a34a;" title="Receipt" target="_blank"><i class="fas fa-file-pdf"></i></a>
        <a href="<?php echo e(route('finance.edit', $transaction)); ?>" class="btn-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
        <form method="POST" action="<?php echo e(route('finance.destroy', $transaction)); ?>" onsubmit="return confirm('Delete this transaction?')">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="submit" class="btn-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
        </form>
    </div>
</td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fas fa-money-bill-wave" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No transactions yet</div>
                        <a href="<?php echo e(route('finance.create')); ?>" class="btn-primary"><i class="fas fa-plus"></i> Record First Transaction</a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($transactions->hasPages()): ?>
    <div style="padding:16px 24px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; color:#64748b;">
            Showing <?php echo e($transactions->firstItem()); ?> to <?php echo e($transactions->lastItem()); ?> of <?php echo e($transactions->total()); ?> transactions
        </div>
        <?php echo e($transactions->links()); ?>

    </div>
    <?php endif; ?>
</div>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\user\Herd\rhema-aog-ims\resources\views/finance/index.blade.php ENDPATH**/ ?>