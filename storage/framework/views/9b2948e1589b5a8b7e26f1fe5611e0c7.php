Hi <?php echo e(\App\Support\MailBranding::greetingName($user->name)); ?>,

We received a request to reset your password for your <?php echo e(config('mail.from.name')); ?> account.

Open this link to choose a new password:

<?php echo e($resetUrl); ?>


If you did not request a password reset, you can ignore this email.

—
<?php echo e(config('mail.from.name')); ?>

<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/reset-password-text.blade.php ENDPATH**/ ?>