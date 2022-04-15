<?php if (isset($_sectionVars['list']) && count($_sectionVars['list']) > 0) : ?>
<table cellspacing="0" cellpadding="0" align="center" width="100%">
    <tbody>
        <tr>
            <td style="padding: 15px 30px;" class="bw-td_paddingmobile10">
                <table cellspacing="0" cellpadding="0" align="left" border="0" width="100%"
                    style="background-color:#f4f4f4;" bgcolor="#f4f4f4">
                    <tbody>
                        <tr>
                            <td class="bodytext"
                                style="padding-top: 20px; padding-right: 10px; padding-bottom: 15px; padding-left: 10px;">
                                <span
                                    style="font-size: 22px; color: rgb(13, 13, 13);"><?php echo $_sectionVars['section_header']; ?></span><br><br>
                                <table style="width: 100%;">
                                    <tbody>
                                        <?php if ($_sectionVars['list_header']) : ?>
                                        <tr>
                                            <td style="padding: 5px 0px;"><span style="color: rgb(85, 85, 85);"><span
                                                        style="font-size: 18px;"><?php echo $_sectionVars['list_header']['title'] ?:
                                                                                                                                ''; ?></span></span>
                                            </td>
                                            <td style="padding: 5px 0px; text-align: right;">
                                                <span style="color: rgb(85, 85, 85);"><span
                                                        style="font-size: 18px;"><?php echo $_sectionVars['list_header']['value'] ?:
                                                                                                    ''; ?></span><br></span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php foreach ($_sectionVars['list'] as $item) : ?>
                                        <tr>
                                            <td style="padding: 5px 0px;"><span style="color: rgb(85, 85, 85);"><a
                                                        href="<?php echo $item['url']; ?>" rel="noopener"
                                                        style="text-decoration: underline; color: #5b2c82;"
                                                        target="_blank"><?php echo $item['title']; ?></a><br></span>
                                            </td>
                                            <?php if (!empty($item['value'])) : ?>
                                            <td style="padding: 5px 0px; text-align: right;">
                                                <span style="color: rgb(85, 85, 85);">
                                                    <?php echo $item['value']; ?></span>
                                            </td>
                                            <?php endif; ?>
                                        </tr>

                                        <?php endforeach; ?>

                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
<?php else : ?>
<table cellspacing="0" cellpadding="0" align="center" width="100%">
    <tbody>
        <tr>
            <td style="padding: 15px 30px;" class="bw-td_paddingmobile10">
                <table cellspacing="0" cellpadding="0" align="left" border="0" width="100%"
                    style="background-color:#f4f4f4;" bgcolor="#f4f4f4">
                    <tbody>
                        <tr>
                            <td class="bodytext"
                                style="padding-top: 20px; padding-right: 10px; padding-bottom: 15px; padding-left: 10px; font-size:14px;">
                                <span style="font-size: 22px;"><?php echo $_sectionVars['text_header']; ?></span>
                                <?php echo $_sectionVars['no_items_text']; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
<?php endif; ?>