<?php ($logoUrl = config('mail.brand_logo_url')); ?>
<?php ($brandName = config('mail.from.name')); ?>
<?php if($logoUrl): ?>
<div style="margin-bottom:20px;">
<img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($brandName); ?>" width="140" style="max-width:140px;height:auto;display:block;border:0;" />
</div>
<?php else: ?>
<p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#111827;"><?php echo e($brandName); ?></p>
<?php endif; ?>
<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/partials/branded-header.blade.php ENDPATH**/ ?>