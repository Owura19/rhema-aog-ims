<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>

<!-- Stats Row 1 -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-users" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['total_members']); ?></div>
            <div class="stat-label">Total Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-user-check" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['active_members']); ?></div>
            <div class="stat-label">Active Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-church" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['services_month']); ?></div>
            <div class="stat-label">Services This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3;">
            <i class="fas fa-fingerprint" style="color:#ca8a04;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['active_devices']); ?></div>
            <div class="stat-label">Active Devices</div>
        </div>
    </div>
</div>

<!-- Stats Row 2 -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">
            <i class="fas fa-arrow-up" style="color:#16a34a;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS <?php echo e(number_format($stats['income_month'], 2)); ?></div>
            <div class="stat-label">Income This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;">
            <i class="fas fa-arrow-down" style="color:#dc2626;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:18px;">GHS <?php echo e(number_format($stats['expense_month'], 2)); ?></div>
            <div class="stat-label">Expenses This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">
            <i class="fas fa-home" style="color:#2563eb;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['total_groups']); ?></div>
            <div class="stat-label">Active Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff;">
            <i class="fas fa-calendar-alt" style="color:#7c3aed;"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo e($stats['upcoming_events']); ?></div>
            <div class="stat-label">Upcoming Events</div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">

    <!-- Recent Services -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-church" style="color:#7c3aed; margin-right:8px;"></i>Recent Services</div>
            <a href="<?php echo e(route('services.create')); ?>" class="btn-primary btn-sm"><i class="fas fa-plus"></i> New Service</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Attendance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $recentServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td>
                            <a href="<?php echo e(route('services.show', $service)); ?>" style="font-weight:600; color:#2563eb; text-decoration:none;"><?php echo e($service->name); ?></a>
                            <div style="font-size:12px; color:#94a3b8;"><?php echo e($service->service_type); ?></div>
                        </td>
                        <td style="font-size:13px; color:#64748b;"><?php echo e($service->service_date->format('M d, Y')); ?></td>
                        <td><span style="font-weight:700;"><?php echo e($service->attendance_logs_count); ?></span> <span style="font-size:12px; color:#94a3b8;">present</span></td>
                        <td>
                            <?php if($service->status === 'Completed'): ?>
                                <span class="badge badge-success">Completed</span>
                            <?php elseif($service->status === 'Ongoing'): ?>
                                <span class="badge badge-info">Ongoing</span>
                            <?php elseif($service->status === 'Scheduled'): ?>
                                <span class="badge badge-warning">Scheduled</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:#94a3b8; padding:30px;">
                            No services yet. <a href="<?php echo e(route('services.create')); ?>" style="color:#2563eb;">Create first service</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-bolt" style="color:#e8a020; margin-right:8px;"></i>Quick Actions</div>
            </div>
            <div class="card-body" style="display:flex; flex-direction:column; gap:10px;">
                <a href="<?php echo e(route('members.create')); ?>" class="btn-primary"><i class="fas fa-user-plus"></i> Add Member</a>
                <a href="<?php echo e(route('services.create')); ?>" class="btn-primary" style="background:#7c3aed;"><i class="fas fa-church"></i> Create Service</a>
                <a href="<?php echo e(route('finance.create')); ?>" class="btn-primary" style="background:#16a34a;"><i class="fas fa-money-bill-wave"></i> Record Transaction</a>
                <a href="<?php echo e(route('events.create')); ?>" class="btn-primary" style="background:#2563eb;"><i class="fas fa-calendar-alt"></i> Create Event</a>
            </div>
        </div>

        <!-- Upcoming Events -->
        <?php if($upcomingEvents->isNotEmpty()): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-calendar-alt" style="color:#2563eb; margin-right:8px;"></i>Upcoming Events</div>
            </div>
            <div style="padding:0;">
                <?php $__currentLoopData = $upcomingEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9;">
                    <div style="font-size:13px; font-weight:600; color:#1e293b;"><?php echo e($event->title); ?></div>
                    <div style="font-size:12px; color:#64748b;"><?php echo e($event->start_date->format('M d, Y')); ?> · <?php echo e($event->venue ?? 'Venue TBD'); ?></div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Members -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-users" style="color:#2563eb; margin-right:8px;"></i>Recent Members</div>
            </div>
            <div style="padding:0;">
                <?php $__empty_1 = true; $__currentLoopData = $recentMembers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div style="display:flex; align-items:center; gap:10px; padding:10px 20px; border-bottom:1px solid #f1f5f9;">
                    <div class="member-avatar-placeholder" style="width:32px; height:32px; font-size:12px;"><?php echo e(strtoupper(substr($member->first_name,0,1))); ?></div>
                    <div style="flex:1;">
                        <div style="font-size:13px; font-weight:600; color:#1e293b;"><?php echo e($member->full_name); ?></div>
                        <div style="font-size:11px; color:#94a3b8;"><?php echo e($member->member_id); ?></div>
                    </div>
                    <span class="badge <?php echo e($member->membership_status === 'Active' ? 'badge-success' : 'badge-gray'); ?>" style="font-size:11px;"><?php echo e($member->membership_status); ?></span>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">No members yet</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\user\Herd\rhema-aog-ims\resources\views/dashboard/super-admin.blade.php ENDPATH**/ ?>