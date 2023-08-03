var BrokenLinkDetector = {};

/*
	global jQuery, WS_ANALYSIS
*/
jQuery(document).ready(function($) {
  jQuery("#author_id").change(function(e) {
    window.location.href =
      window.location.href + "&author_id=" + jQuery(this).val();
  });

  jQuery("[name=show_only_broken_links]").change(function(e) {
    let showBrokenLinks =
      jQuery(this).attr("name") + "=" + jQuery(this).is(":checked");
    window.location.href = window.location.href + "&" + showBrokenLinks;
  });
});

jQuery(document).ready(function($) {
  jQuery("#trigger_update_analytics").click(function(e) {
    var $this = jQuery(this);
    $this.attr("disabled", true);
    var data = {
      action: "trigger_update_analytics",
    };

    jQuery.post(WS_ANALYSIS.ajaxurl, data, function(response) {
      $this.attr("disabled", false);
      if (!response.success) {
        let msg = WS_ANALYSIS.strings.something_went_wrong;
        if (response.data && response.data[0] && response.data[0].message) {
          msg = response.data[0].message;
        }
        alert(WS_ANALYSIS.strings.error + ": " + msg);
        return;
      }
      if (!alert(WS_ANALYSIS.strings.update_complete)) {
        window.location.reload();
      }
    });
  });
});
