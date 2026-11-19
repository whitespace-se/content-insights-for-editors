<?php ob_start(); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
<?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
  '/partials/header.template.php'; ?>
</head>
<body class="clean-body" style="margin: 0; padding: 0; -webkit-text-size-adjust: 100%; background-color: #f4f4f4;">
<?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
  '/partials/header/top-section.template.php'; ?>
<div style="background-color:transparent;">
<div class="block-grid" style="Margin: 0 auto; min-width: 320px; max-width: 600px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; background-color: #FFFFFF;">
<div style="border-collapse: collapse;display: table;width: 100%;background-color:#FFFFFF;">

<?php if ($_htmlvars['logo']):
  include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
    '/partials/header/logo.template.php';
endif; ?>
</div>
</div>
</div>
<div style="background-color:transparent;">
<div class="block-grid" style="Margin: 0 auto; min-width: 320px; max-width: 600px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; background-color: #FFFFFF;">
<div style="border-collapse: collapse;display: table;width: 100%;background-color:#FFFFFF;">
<?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
  '/partials/header/preamble.template.php'; ?>

<?php foreach ($_htmlvars['sections'] as $_sectionVars):
  $template =
    CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
    '/partials/section.template.php';
  if (
    $_sectionVars['id'] === 'most-viewed' &&
    class_exists('\CustomerFeedback\App')
  ) {
    $template =
      CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
      '/partials/section-3-cols-customer-feedback.template.php';
  }
  $template = apply_filters(
    'cife_notification_mail_render_section',
    $template,
    $_sectionVars
  );
  include $template;
endforeach; ?>

<?php if (
  isset($_htmlvars['button_cta_text']) &&
  isset($_htmlvars['button_cta_url'])
):
  include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
    '/partials/cta-button.template.php';
endif; ?>

<?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
  '/partials/footer-section.template.php'; ?>

</body>
</html>
<?php return ob_get_clean(); ?>
