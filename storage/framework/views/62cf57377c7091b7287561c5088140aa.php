<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Planner inquiry</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111;">
    <p><strong>Store:</strong> <?php echo e($admin->company_name); ?></p>
    <p><strong>Planner:</strong> <?php echo e($plannerLabel); ?> <span style="color:#555;">(<?php echo e($plannerType); ?>)</span></p>
    <p><strong>From:</strong> <?php echo e($customerName); ?> &lt;<?php echo e($customerEmail); ?>&gt;</p>
    <?php if(!empty($notes)): ?>
        <p><strong>Customer notes:</strong></p>
        <p style="white-space: pre-wrap;"><?php echo e($notes); ?></p>
    <?php endif; ?>

    <h2 style="font-size:18px;margin:24px 0 8px;">Design summary</h2>
    <table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;width:100%;max-width:680px;">
        <?php $__currentLoopData = ($designSummary['overview'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td style="padding:6px 10px;border:1px solid #e2e4e8;background:#f8fafc;width:170px;"><strong><?php echo e($row['label']); ?></strong></td>
                <td style="padding:6px 10px;border:1px solid #e2e4e8;"><?php echo e($row['value']); ?></td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </table>

    <h3 style="font-size:16px;margin:18px 0 8px;">Products requested</h3>
    <?php $__empty_1 = true; $__currentLoopData = ($designSummary['products'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div style="border:1px solid #e2e4e8;border-radius:8px;padding:12px;margin:0 0 12px;">
            <p style="margin:0 0 8px;"><strong><?php echo e($product['title']); ?></strong></p>
            <?php if(!empty($product['details'])): ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php $__currentLoopData = $product['details']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><strong><?php echo e($detail['label']); ?>:</strong> <?php echo e($detail['value']); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p>No products were placed in the planner.</p>
    <?php endif; ?>

    <?php if(!empty($designSummary['materials'])): ?>
        <h3 style="font-size:16px;margin:18px 0 8px;">Materials used</h3>
        <ul style="margin-top:0;padding-left:20px;">
            <?php $__currentLoopData = $designSummary['materials']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($material['name']); ?> <span style="color:#555;">(<?php echo e($material['id']); ?>)</span> - used <?php echo e($material['count']); ?> time(s)</li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    <?php endif; ?>

    <p><strong>Technical design data (JSON backup):</strong></p>
    <pre style="font-size:11px;line-height:1.35;overflow:auto;max-height:320px;background:#f6f7f9;padding:12px;border-radius:8px;border:1px solid #e2e4e8;"><?php echo e(e($designJsonPretty)); ?></pre>
</body>
</html>
<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/planner-inquiry.blade.php ENDPATH**/ ?>