Store: <?php echo e($admin->company_name); ?>

Planner: <?php echo e($plannerLabel); ?> (<?php echo e($plannerType); ?>)
From: <?php echo e($customerName); ?> <<?php echo e($customerEmail); ?>>

<?php if(!empty($notes)): ?>
Customer notes:
<?php echo e($notes); ?>


<?php endif; ?>
Design summary:
<?php $__currentLoopData = ($designSummary['overview'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php echo e($row['label']); ?>: <?php echo e($row['value']); ?>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

Products requested:
<?php $__empty_1 = true; $__currentLoopData = ($designSummary['products'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<?php echo e($product['title']); ?>

<?php $__currentLoopData = ($product['details'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
- <?php echo e($detail['label']); ?>: <?php echo e($detail['value']); ?>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
No products were placed in the planner.
<?php endif; ?>
<?php if(!empty($designSummary['materials'])): ?>
Materials used:
<?php $__currentLoopData = $designSummary['materials']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
- <?php echo e($material['name']); ?> (<?php echo e($material['id']); ?>) - used <?php echo e($material['count']); ?> time(s)
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php endif; ?>
Technical design data (JSON backup):
<?php echo e($designJsonPretty); ?>

<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/planner-inquiry-text.blade.php ENDPATH**/ ?>