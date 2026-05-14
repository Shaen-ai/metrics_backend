ERROR REPORT — <?php echo e(config('mail.from.name')); ?>

==============================================

User ID:    <?php echo e($userId); ?>

User Email: <?php echo e($userEmail); ?>


Error:
<?php echo e($errorMessage); ?>


<?php if($url): ?>
Page: <?php echo e($url); ?>

<?php endif; ?>
<?php if($userAgent): ?>
Browser: <?php echo e($userAgent); ?>

<?php endif; ?>
<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/error-report-text.blade.php ENDPATH**/ ?>