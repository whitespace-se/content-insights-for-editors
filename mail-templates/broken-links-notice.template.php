<?php ob_start(); ?>
<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
    '/partials/header.template.php'; ?>
</head>

<body bgcolor="#f4f4f4" style="background-color: rgb(244, 244, 244);">
    <div style="width: 100%; background-color:#f4f4f4;" align="center">
        <!-- <?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
            '/partials/header/top-section.template.php'; ?> -->


        <?php if ($_htmlvars['logo']) :
      include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
        '/partials/header/logo.template.php';
    endif; ?>

        <?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
      '/partials/header/preamble.template.php'; ?>

        <table width="640" cellspacing="0" cellpadding="0" class="bw-table_content" style="background-color:#ffffff;"
            bgcolor="#ffffff">
            <tbody>
                <tr>
                    <td style="background-color:#ffffff; padding-top:0px; padding-bottom:0px;" class="">

                        <?php foreach ($_htmlvars['sections'] as $_sectionVars) :
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
            ) :
              include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
                '/partials/cta-button.template.php';
            endif; ?>

                        <?php include CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH .
              '/partials/footer-section.template.php'; ?>


                    </td>
                </tr>
            </tbody>
        </table>


    </div>
</body>

</html>
<?php return ob_get_clean(); ?>