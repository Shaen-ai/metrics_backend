<?php $__env->startSection('title', 'Reset your password'); ?>

<?php $__env->startSection('content'); ?>
<p style="margin:0 0 16px;font-size:16px;">Hi <?php echo e(\App\Support\MailBranding::greetingName($user->name)); ?>,</p>
<p style="margin:0 0 24px;font-size:16px;">We received a request to reset your password for your <?php echo e(config('mail.from.name')); ?> account. Choose a new password using the link below:</p>
<p style="margin:0 0 24px;">
<a href="<?php echo e($resetUrl); ?>" style="display:inline-block;padding:12px 20px;background:#111827;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">Reset password</a>
</p>
<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">If the button does not work, copy this link into your browser:</p>
<p style="margin:0 0 24px;font-size:13px;color:#374151;word-break:break-all;"><?php echo e($resetUrl); ?></p>
<p style="margin:0;font-size:13px;color:#6b7280;">If you did not request a password reset, you can ignore this email. Your password will stay the same.</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.branded', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/reset-password.blade.php ENDPATH**/ ?>