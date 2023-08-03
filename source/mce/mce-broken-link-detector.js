(function() {
    tinymce.PluginManager.add('brokenlinksdetector', function(editor, url) {
        var selectors = 'a[href="#"]';
        var selectors_before = 'a[href="#"]::before';
        var selectors_after = 'a[href="#"]::after';

        // Ajax to get broken links for this post
        jQuery.each(broken_links, function (index, item) {
            selectors = selectors + ', a[data-mce-href="' + item + '"]';
            selectors_before = selectors_before + ', a[data-mce-href="' + item + '"]::before';
            selectors_after = selectors_after + ', a[data-mce-href="' + item + '"]::after';
        });

        editor.contentStyles.push(selectors + " { color: #ff0000; text-decoration-style: wavy; }");
        editor.contentStyles.push(selectors_before + ' { display: inline-block; margin-right: 3px; content: ""; font-family: dashicons; position: relative; top: 4px; text-dociration: none; }');
        editor.contentStyles.push(selectors_after + ' { display: inline-block; margin-left: 3px; content: "Broken link"; font-family: arial; font-size: 12px; background-color: #ff0000; color: #fff; border-radius: 3px; padding: 0 4px; position: relative; top: -1px; }');
    });
})();

