From: <?php echo e($senderName); ?> <<?php echo e($senderEmail); ?>>
<?php if(!empty($phone)): ?>
Phone: <?php echo e($phone); ?>

<?php endif; ?>
<?php if(!empty($source)): ?>
Source: <?php echo e($source); ?>

<?php endif; ?>
<?php if(!empty($shareUrl)): ?>
Share URL: <?php echo e($shareUrl); ?>

<?php endif; ?>

Message:
<?php echo e($bodyText); ?>

<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/contact-form-text.blade.php ENDPATH**/ ?>