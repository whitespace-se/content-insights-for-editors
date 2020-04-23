<?php $_customer = [
  'percentage' => $_feedback['yes'],
]; ?>
<?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
  '/partials/customer-feedback/positive.template.php'; ?>
<?php $_customer = ['percentage' => $_feedback['no']]; ?>
<?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
  '/partials/customer-feedback/negative.template.php'; ?>
