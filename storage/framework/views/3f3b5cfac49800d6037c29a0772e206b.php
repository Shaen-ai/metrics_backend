<?php $__env->startSection('title', 'Order received'); ?>

<?php $__env->startSection('content'); ?>
<p style="margin:0 0 16px;font-size:16px;font-weight:600;">Thank you for your order</p>
<p style="margin:0 0 16px;font-size:15px;"><?php echo e($storeLabel); ?> has received your order. They will contact you soon about delivery and payment.</p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Order reference:</strong> <?php echo e(substr($order->id, 0, 8)); ?>…</p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Name:</strong> <?php echo e($order->customer_name); ?></p>
<?php if($order->customer_address): ?>
<p style="margin:0 0 8px;font-size:15px;"><strong>Delivery address:</strong><br><?php echo e(nl2br(e($order->customer_address))); ?></p>
<?php endif; ?>
<p style="margin:0 0 8px;font-size:15px;"><strong>Total:</strong> <?php echo e(number_format($order->total_price, 2)); ?></p>
<p style="margin:0 0 12px;font-size:15px;font-weight:600;">Your items</p>
<ul style="margin:0 0 24px;padding-left:20px;font-size:14px;">
<?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<li style="margin-bottom:6px;"><?php echo e($item->name); ?> ×<?php echo e($item->quantity); ?> — <?php echo e(number_format($item->price * $item->quantity, 2)); ?></li>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</ul>
<p style="margin:0;font-size:13px;color:#6b7280;">If you did not place this order, you can ignore this email or contact <?php echo e($storeLabel); ?>.</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.branded', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/order-placed-customer.blade.php ENDPATH**/ ?>