Thank you for your order

<?php echo e($storeLabel); ?> has received your order. They will contact you soon about delivery and payment.

Order reference: <?php echo e(substr($order->id, 0, 8)); ?>…
Name: <?php echo e($order->customer_name); ?>

<?php if($order->customer_address): ?>
Delivery address:
<?php echo e($order->customer_address); ?>


<?php endif; ?>
Total: <?php echo e(number_format($order->total_price, 2)); ?>


Your items:
<?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
- <?php echo e($item->name); ?> ×<?php echo e($item->quantity); ?> — <?php echo e(number_format($item->price * $item->quantity, 2)); ?>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

If you did not place this order, you can ignore this email or contact <?php echo e($storeLabel); ?>.
<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/order-placed-customer-text.blade.php ENDPATH**/ ?>