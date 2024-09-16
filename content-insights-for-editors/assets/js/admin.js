jQuery(document).ready(function($) {
    function handleAjaxRequest(buttonId, actionName, startMessage) {
        $(buttonId).on('click', function(e) {
            e.preventDefault();
            console.log('Button clicked:', buttonId);
            var $button = $(this);
            var $result = $(buttonId + '-result');

            $button.prop('disabled', true);
            $result.html('<p style="color: blue;">' + startMessage + '</p>');

            console.log('Sending AJAX request:', actionName);
            $.ajax({
                url: cifeAjax.ajax_url,
                type: 'POST',
                data: {
                    action: actionName,
                    nonce: cifeAjax.nonce
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        $result.html('<p style="color: green;">' + response.data.message + '</p>');
                    } else {
                        $result.html('<p style="color: red;">' + response.data.message + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    $result.html('<p style="color: red;">Error: ' + error + '</p>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    handleAjaxRequest('#fetch_matomo_data', 'cife_fetch_matomo_data', 'Fetching Matomo data...');
    handleAjaxRequest('#empty_matomo_data', 'cife_empty_matomo_data', 'Emptying Matomo data...');
});