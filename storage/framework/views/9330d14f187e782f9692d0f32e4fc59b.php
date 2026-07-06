<?php $__env->startSection('title', 'Visitors'); ?>

<?php $__env->startSection('content'); ?>

<div style="display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-user-friends" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['total']); ?></div>
            <div class="stat-label">Total Visitors</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-calendar-check" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['this_month']); ?></div>
            <div class="stat-label">This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-clock" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['pending']); ?></div>
            <div class="stat-label">Pending Follow-up</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-user-plus" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['first_time']); ?></div>
            <div class="stat-label">First Time (Month)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-church" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['joined']); ?></div>
            <div class="stat-label">Joined as Members</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user-friends" style="color:#2563eb; margin-right:8px;"></i>Visitors</div>
        <a href="<?php echo e(route('visitors.create')); ?>" class="btn-primary">
            <i class="fas fa-plus"></i> Record Visitor
        </a>
    </div>

    <!-- Filters -->
    <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <form method="GET" action="<?php echo e(route('visitors.index')); ?>" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Search name, phone..." class="form-control" style="width:220px;">
            <select name="follow_up_status" class="form-control" style="width:170px;">
                <option value="">All Statuses</option>
                <?php $__currentLoopData = ['Pending','Called','Visited','Attended Again','Joined','No Response','Not Interested']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($status); ?>" <?php echo e(request('follow_up_status') == $status ? 'selected' : ''); ?>><?php echo e($status); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <select name="visit_type" class="form-control" style="width:150px;">
                <option value="">All Types</option>
                <?php $__currentLoopData = ['First Time','Second Time','Third Time','Regular']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($type); ?>" <?php echo e(request('visit_type') == $type ? 'selected' : ''); ?>><?php echo e($type); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <input type="date" name="date_from" value="<?php echo e(request('date_from')); ?>" class="form-control" style="width:150px;">
            <input type="date" name="date_to" value="<?php echo e(request('date_to')); ?>" class="form-control" style="width:150px;">
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filter</button>
            <?php if(request()->hasAny(['search','follow_up_status','visit_type','date_from','date_to'])): ?>
                <a href="<?php echo e(route('visitors.index')); ?>" class="btn-outline"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Visitor</th>
                    <th>Phone</th>
                    <th>Visit Date</th>
                    <th>Type</th>
                    <th>How Heard</th>
                    <th>Follow-up</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $visitors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $visitor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td>
                        <div style="font-weight:600; color:#1e293b;"><?php echo e($visitor->full_name); ?></div>
                        <?php if($visitor->email): ?>
                            <div style="font-size:12px; color:#94a3b8;"><?php echo e($visitor->email); ?></div>
                        <?php endif; ?>
                        <?php if($visitor->converted_to_member): ?>
                            <span class="badge badge-success" style="font-size:10px;">Member</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px; color:#64748b;"><?php echo e($visitor->phone ?? '—'); ?></td>
                    <td style="font-size:13px; color:#64748b;"><?php echo e($visitor->visit_date->format('M d, Y')); ?></td>
                    <td>
                        <?php if($visitor->visit_type === 'First Time'): ?>
                            <span class="badge badge-info">First Time</span>
                        <?php elseif($visitor->visit_type === 'Second Time'): ?>
                            <span class="badge badge-warning">Second Time</span>
                        <?php elseif($visitor->visit_type === 'Third Time'): ?>
                            <span class="badge" style="background:#f3e8ff; color:#7c3aed;">Third Time</span>
                        <?php else: ?>
                            <span class="badge badge-success">Regular</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px; color:#64748b;"><?php echo e($visitor->how_heard ?? '—'); ?></td>
                    <td>
                        <?php $color = $visitor->follow_up_status_color; ?>
                        <span class="badge badge-<?php echo e($color); ?>"><?php echo e($visitor->follow_up_status); ?></span>
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="<?php echo e(route('visitors.show', $visitor)); ?>" class="btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?php echo e(route('visitors.edit', $visitor)); ?>" class="btn-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if(!$visitor->converted_to_member): ?>
                            <form method="POST" action="<?php echo e(route('visitors.convert', $visitor)); ?>" onsubmit="return confirm('Convert <?php echo e($visitor->full_name); ?> to a full member?')">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn-primary btn-sm" style="background:#16a34a;" title="Convert to Member"><i class="fas fa-user-plus"></i></button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="<?php echo e(route('visitors.destroy', $visitor)); ?>" onsubmit="return confirm('Delete this visitor?')">
                                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                <button type="submit" class="btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fas fa-user-friends" style="font-size:48px; display:block; margin-bottom:16px;"></i>
                        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No visitors recorded yet</div>
                        <a href="<?php echo e(route('visitors.create')); ?>" class="btn-primary"><i class="fas fa-plus"></i> Record First Visitor</a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($visitors->hasPages()): ?>
    <div style="padding:16px 24px; border-top:1px solid #f1f5f9;">
        <?php echo e($visitors->links()); ?>

    </div>
    <?php endif; ?>
</div>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\user\Herd\rhema-aog-ims\resources\views/visitors/index.blade.php ENDPATH**/ ?>