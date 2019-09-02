<?php if (isset($_sectionVars['list']) && count($_sectionVars['list']) > 0): ?>
    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top: 15px solid #ffffff; border-left: 30px solid #ffffff; border-right: 30px solid #ffffff;background-color: #f4f4f4; padding-right: 10px; padding-left: 10px; padding-top: 20px; padding-bottom: 20px; font-family: 'Trebuchet MS', Tahoma, sans-serif"><![endif]-->
    <div style="border-top: 15px solid #ffffff; border-left: 30px solid #ffffff; border-right: 30px solid #ffffff; color:#0D0D0D;background-color: #f4f4f4;font-family:'Montserrat', 'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif;line-height:150%;padding-top:20px;padding-right:10px;padding-bottom:15px;padding-left:10px;">
    <div style="font-size: 12px; line-height: 18px; font-family: 'Montserrat', 'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif; color: #0D0D0D;">
    <p style="font-size: 14px; line-height: 36px; text-align: left; margin: 0;"><span style="font-size: 22px;"><?php echo $_sectionVars[
    	'section_header'
    ]; ?></span></p>
    </div>
    </div>
    <!--[if mso]></td></tr></table><![endif]-->
    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-bottom: 15px solid #ffffff; border-left: 30px solid #ffffff; border-right: 30px solid #ffffff;padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px; font-family: 'Trebuchet MS', Tahoma, sans-serif"><![endif]-->
    <div style="border-bottom: 15px solid #ffffff; border-left: 30px solid #ffffff; border-right: 30px solid #ffffff;color:#555555;background-color: #f4f4f4;font-family:'Montserrat', 'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif;line-height:120%;padding-top:0px;padding-right:10px;padding-bottom:10px;padding-left:10px;">
    <ul style="color: #555555; font-family: 'Montserrat', 'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif; line-height: 120%;margin-top:0px; list-style-type: none; margin-left: 0; padding-left: 0;">
    <?php if ($_sectionVars['list_header']): ?>
        <li style="list-style-type: none; margin-bottom: 6px; margin-left: 0;">
            <table style="width: 100%">
                <tr>
                    <th><?php echo $_sectionVars['list_header']['title'] ?: '';  ?></th>
                    <th style="text-align:right;"><?php echo $_sectionVars['list_header']['value'] ?: ''; ?></th>
                </tr>
            </table>
        </li>
    <?php endif; ?>
    <?php foreach ($_sectionVars['list'] as $item): ?>
        <li style="font-size: 12px; line-height: 14px; margin-bottom: 6px; margin-left: 0;">
        <table style="width: 100%">
                <tr>
                    <td><a href="<?php echo $item[
                    	'url'
                    ]; ?>" rel="noopener" style="text-decoration: underline; color: #5b2c82;" target="_blank"><?php echo $item[
	'title'
]; ?></a></td>
    <?php if (!empty($item['value'])): ?>
        <td style="text-align:right;">
            <?php echo $item['value']; ?>
        </td>
    <?php endif; ?>
                </tr>
        </table>
</li>
    <?php endforeach; ?>
    </ul>
    </div>
    <!--[if mso]></td></tr></table><![endif]-->
<?php else: ?>
    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top: 15px solid #ffffff; border-bottom: 15px solid #ffffff; border-left: 30px solid #ffffff; border-right: 30px solid #ffffff;background-color: #f4f4f4; padding-right: 10px; padding-left: 10px; padding-top: 20px; padding-bottom: 20px; font-family: 'Trebuchet MS', Tahoma, sans-serif"><![endif]-->
    <div style="border-top: 15px solid #ffffff; border-bottom: 15px solid #ffffff; border-left: 30px solid #ffffff; border-right: 30px solid #ffffff;color:#0D0D0D;background-color: #f4f4f4;font-family:'Montserrat', 'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif;line-height:150%;padding-top:20px;padding-right:10px;padding-bottom:15px;padding-left:10px;">
    <div style="font-size: 12px; line-height: 18px; font-family: 'Montserrat', 'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif; color: #0D0D0D;">
    <p style="font-size: 14px; line-height: 36px; text-align: left; margin: 0;"><span style="font-size: 22px;"><?php echo $_sectionVars[
    	'text_header'
    ]; ?></span></p>
    <p style="font-size: 14px; line-height: 21px; text-align: left; margin: 0;"><?php echo $_sectionVars[
    	'no_items_text'
    ]; ?></p>
    </div>
    </div>
    <!--[if mso]></td></tr></table><![endif]-->
<?php endif; ?>
