Hi <?php echo e(\App\Support\MailBranding::greetingName($user->name)); ?>,

Thanks for signing up with <?php echo e(config('mail.from.name')); ?>. Open this link to verify your email, then sign in:

<?php echo e($verificationUrl); ?>


If you did not create an account, you can ignore this message.

—
<?php echo e(config('mail.from.name')); ?>

<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/verify-email-text.blade.php ENDPATH**/ ?>