<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Contact form</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111;">
    <p><strong>From:</strong> <?php echo e($senderName); ?> &lt;<?php echo e($senderEmail); ?>&gt;</p>
    <p><strong>Message:</strong></p>
    <p style="white-space: pre-wrap;"><?php echo e($bodyText); ?></p>
</body>
</html>
<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/contact-form.blade.php ENDPATH**/ ?>