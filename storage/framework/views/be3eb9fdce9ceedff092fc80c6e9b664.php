<?php $__env->startSection('title', 'New order'); ?>

<?php $__env->startSection('content'); ?>
<p style="margin:0 0 16px;font-size:16px;font-weight:600;">New order received</p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Customer:</strong> <?php echo e($order->customer_name); ?></p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Email:</strong> <?php echo e($order->customer_email); ?></p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Phone:</strong> <?php echo e($order->customer_phone ?: 'N/A'); ?></p>
<?php if($order->customer_address): ?>
<p style="margin:0 0 8px;font-size:15px;"><strong>Delivery address:</strong><br><?php echo e(nl2br(e($order->customer_address))); ?></p>
<?php endif; ?>
<p style="margin:0 0 8px;font-size:15px;"><strong>Total:</strong> <?php echo e(number_format($order->total_price, 2)); ?></p>
<p style="margin:0 0 24px;font-size:15px;"><strong>Payment:</strong> Arrange payment and delivery with the customer as you usually do.</p>
<p style="margin:0 0 12px;font-size:15px;font-weight:600;">Order items</p>
<ul style="margin:0 0 24px;padding-left:20px;font-size:14px;">
<?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<li style="margin-bottom:6px;"><?php echo e($item->name); ?> x<?php echo e($item->quantity); ?> - <?php echo e(number_format($item->price * $item->quantity, 2)); ?></li>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</ul>
<p style="margin:0 0 16px;">
<a href="<?php echo e(rtrim(config('app.frontend_admin_url'), '/')); ?>/admin/orders" style="display:inline-block;padding:12px 20px;background:#111827;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">View orders in dashboard</a>
</p>
<p style="margin:0;font-size:13px;color:#6b7280;">Contact the customer to arrange delivery and payment collection.</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.branded', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/order-placed.blade.php ENDPATH**/ ?>