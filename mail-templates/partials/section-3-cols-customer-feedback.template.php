<?php if (isset($_sectionVars['list']) && count($_sectionVars['list']) > 0) : ?>
<?php if ($_sectionVars['list_header']) : ?>
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
                                    style="font-size: 22px; color: rgb(13, 13, 13);"><?php echo $_sectionVars['section_header']; ?></span>
                                <br>
                                <br>
                                <?php endif; ?>
                                <table style="width: 100%;">
                                    <tbody>
                                        <tr>
                                            <td style="padding: 5px 0px; width: 31.7277%;"><span
                                                    style="color: rgb(85, 85, 85);"><span
                                                        style="font-size: 18px;"><?php echo $_sectionVars['list_header']['title'] ?: ''; ?></span></span>
                                            </td>
                                            <td style="width: 33.3333%; text-align: center;"><span
                                                    style="color: rgb(85, 85, 85);"><span
                                                        style="font-size: 18px;"><?php echo $_sectionVars['list_header']['feedback'] ?: ''; ?></span></span>
                                            </td>
                                            <td style="padding: 5px 0px; text-align: right; width: 34.8105%;"><span
                                                    style="color: rgb(85, 85, 85);"><span
                                                        style="font-size: 18px;"><?php echo $_sectionVars['list_header']['value'] ?: ''; ?></span>
                                                    <br>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php foreach ($_sectionVars['list'] as $item) : ?>
                                        <tr>
                                            <td style="padding: 5px 0px; width: 31.7277%;">
                                                <a href="<?php echo $item['url']; ?>" rel="noopener" target="_blank"><?php echo $item['title']; ?></a>
                                            </td>
                                            <td style="padding: 5px 0px; width: 33.3333%; text-align: center;">
                                                <table cellpadding="0" cellspacing="0" style="width: 100%">
                                                    <tbody>
                                                        <tr>
                                                            <td style="width: 50%; text-align: right">
                                                                <table cellpadding="0" cellspacing="0"
                                                                    style="width: 100%">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td style="width: 50%; text-align: right">
                                                                                <span
                                                                                    style="color: #56a54f"><?php echo $item['feedback']['yes'] ?></span>
                                                                                <br />
                                                                            </td>
                                                                            <td style="
                          width: 50%;
                          vertical-align: middle;
                          padding-left: 5px;
                        ">
                                                                                <img src="<?php echo CONTENT_INSIGHTS_FOR_EDITORS_URL .
                                                        '/source/images/thumbs-up.jpg'; ?>" class="fr-fii fr-dib"
                                                                                    style="margin: initial; width: 16px"
                                                                                    width="16" />
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </td>
                                                            <td
                                                                style="width: 50%; vertical-align: middle; padding-left: 5px">
                                                                <table cellpadding="0" cellspacing="0"
                                                                    style="width: 100%">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td style="width: 50%; text-align: right">
                                                                                <span
                                                                                    style="color: #cc5151"><?php echo $item['feedback']['no'] ?></span>
                                                                                <br />
                                                                            </td>
                                                                            <td style="
                          width: 50%;
                          vertical-align: middle;
                          padding-left: 5px;
                        ">
                                                                                <img src="<?php echo CONTENT_INSIGHTS_FOR_EDITORS_URL .
                                                        '/source/images/thumbs-down.jpg'; ?>" class="fr-fii fr-dib"
                                                                                    style="margin: initial; width: 16px"
                                                                                    width="16" />
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                            <td style="padding: 5px 0px; text-align: right; width: 34.8105%;"><span
                                                    style="color: rgb(85, 85, 85);"><?php echo $item['value']; ?></span>
                                            </td>
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