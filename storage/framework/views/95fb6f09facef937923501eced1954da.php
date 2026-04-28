<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $__env->yieldContent('title', config('mail.from.name')); ?></title>
</head>
<body style="margin:0;padding:24px;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;line-height:1.5;color:#111827;background:#f9fafb;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;padding:32px;border:1px solid #e5e7eb;">
<tr>
<td>
<?php echo $__env->make('emails.partials.branded-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->yieldContent('content'); ?>
<?php echo $__env->make('emails.partials.branded-footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</td>
</tr>
</table>
</body>
</html>
<?php /**PATH /Users/shahen1/apps/mebel/backend/resources/views/emails/layouts/branded.blade.php ENDPATH**/ ?>