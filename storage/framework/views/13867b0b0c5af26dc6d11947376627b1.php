<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Contact form</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111;">
    <p><strong>From:</strong> <?php echo e($senderName); ?> &lt;<?php echo e($senderEmail); ?>&gt;</p>
    <?php if(!empty($phone)): ?>
    <p><strong>Phone:</strong> <?php echo e($phone); ?></p>
    <?php endif; ?>
    <?php if(!empty($source)): ?>
    <p><strong>Source:</strong> <?php echo e($source); ?></p>
    <?php endif; ?>
    <?php if(!empty($shareUrl)): ?>
    <p><strong>Share URL:</strong> <a href="<?php echo e($shareUrl); ?>"><?php echo e($shareUrl); ?></a></p>
    <?php endif; ?>
    <p><strong>Message:</strong></p>
    <p style="white-space: pre-wrap;"><?php echo e($bodyText); ?></p>
</body>
</html>
<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/contact-form.blade.php ENDPATH**/ ?>