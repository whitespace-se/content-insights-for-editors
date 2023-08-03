<div class="wrap">
    <h2><?php _e('Broken links', 'broken-link-detector'); ?></h2>
    <p>Next broken links detection will run: <?php echo $nextRun; ?></p>

    <div id="poststuff">
        <?php
            $listTable->prepare_items();
            $listTable->display();
        ?>
    </div>
</div>
