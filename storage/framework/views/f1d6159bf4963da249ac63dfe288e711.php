New order received

Customer: <?php echo e($order->customer_name); ?>

Email: <?php echo e($order->customer_email); ?>

Phone: <?php echo e($order->customer_phone ?: 'N/A'); ?>

<?php if($order->customer_address): ?>
Delivery address:
<?php echo e($order->customer_address); ?>


<?php endif; ?>
Total: <?php echo e(number_format($order->total_price, 2)); ?>

Payment: Arrange payment and delivery with the customer as you usually do.

Order items:
<?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
- <?php echo e($item->name); ?> x<?php echo e($item->quantity); ?> - <?php echo e(number_format($item->price * $item->quantity, 2)); ?>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

View orders: <?php echo e(rtrim(config('app.frontend_admin_url'), '/')); ?>/admin/orders

Contact the customer to arrange delivery and payment collection.
<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/order-placed-text.blade.php ENDPATH**/ ?>