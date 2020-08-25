<header><h2><?php echo __('Comments Settings'); ?></h2></header>

<?php $this->display($request, 'BlogSettings/tab.php', array('tab'=>'comment_edit')); ?>

<form method="POST" id="sys-blog-template-form" class="admin-form">

<table>
  <tbody>
    <tr>
      <th><?php echo __('Approval settings'); ?></th>
      <td>
        <?php echo \Fc2blog\Web\Html::input($request, 'blog_setting[comment_confirm]', 'select', array('options'=>\Fc2blog\Model\BlogSettingsModel::getCommentConfirmList())); ?>
        <?php if (isset($errors['blog_setting']['comment_confirm'])): ?><p class="error"><?php echo $errors['blog_setting']['comment_confirm']; ?></p><?php endif; ?>
      </td>
    </tr>
    <tr>
      <th><?php echo __('Display awaiting message'); ?></th>
      <td>
        <?php echo \Fc2blog\Web\Html::input($request, 'blog_setting[comment_display_approval]', 'select', array('options'=>\Fc2blog\Model\BlogSettingsModel::getCommentDisplayApprovalList())); ?>
        <?php if (isset($errors['blog_setting']['comment_display_approval'])): ?><p class="error"><?php echo $errors['blog_setting']['comment_display_approval']; ?></p><?php endif; ?>
      </td>
    </tr>
    <tr>
      <th><?php echo __('Display private comment'); ?></th>
      <td>
        <?php echo \Fc2blog\Web\Html::input($request, 'blog_setting[comment_display_private]', 'select', array('options'=>\Fc2blog\Model\BlogSettingsModel::getCommentDisplayPrivateList())); ?>
        <?php if (isset($errors['blog_setting']['comment_display_private'])): ?><p class="error"><?php echo $errors['blog_setting']['comment_display_private']; ?></p><?php endif; ?>
      </td>
    </tr>
    <tr>
      <th><?php echo __('Sender information'); ?></th>
      <td>
        <?php echo __('Save sender\'s information into Cookie?'); ?>
        <?php echo \Fc2blog\Web\Html::input($request, 'blog_setting[comment_cookie_save]', 'select', array('options'=>\Fc2blog\Model\BlogSettingsModel::getCommentCookieSaveList())); ?>
        <?php if (isset($errors['blog_setting']['comment_cookie_save'])): ?><p class="error"><?php echo $errors['blog_setting']['comment_cookie_save']; ?></p><?php endif; ?>
      </td>
    </tr>
    <tr>
      <th><?php echo __('Comment confirmation setting'); ?></th>
      <td>
        <?php echo \Fc2blog\Web\Html::input($request, 'blog_setting[comment_captcha]', 'select', array('options'=>\Fc2blog\Model\BlogSettingsModel::getCommentCaptchaList())); ?>
        <?php if (isset($errors['blog_setting']['comment_captcha'])): ?><p class="error"><?php echo $errors['blog_setting']['comment_captcha']; ?></p><?php endif; ?>
      </td>
    </tr>
    <tr>
      <th><?php echo __('Display the latest comments'); ?></th>
      <td>
        <?php echo \Fc2blog\Web\Html::input($request, 'blog_setting[comment_display_count]', 'text', array('maxlength'=>10)); ?>
        <?php if (isset($errors['blog_setting']['comment_display_count'])): ?><p class="error"><?php echo $errors['blog_setting']['comment_display_count']; ?></p><?php endif; ?>
      </td>
    </tr>
    <tr>
      <th><?php echo __('Display order of comments'); ?></th>
      <td>
        <?php echo \Fc2blog\Web\Html::input($request, 'blog_setting[comment_order]', 'select', array('options'=>\Fc2blog\Model\BlogSettingsModel::getCommentOrderList())); ?>
        <?php if (isset($errors['blog_setting']['comment_order'])): ?><p class="error"><?php echo $errors['blog_setting']['comment_order']; ?></p><?php endif; ?>
      </td>
    </tr>
    <tr>
      <th><?php echo __('Quotes Comments'); ?></th>
      <td>
        <?php echo \Fc2blog\Web\Html::input($request, 'blog_setting[comment_quote]', 'select', array('options'=>\Fc2blog\Model\BlogSettingsModel::getCommentQuoteList())); ?>
        <?php if (isset($errors['blog_setting']['comment_quote'])): ?><p class="error"><?php echo $errors['blog_setting']['comment_quote']; ?></p><?php endif; ?>
      </td>
    </tr>
    <tr>
      <td class="form-button" colspan="2">
        <input type="submit" value="<?php echo __('Update'); ?>" />
      </td>
    </tr>
  </tbody>
</table>
<input type="hidden" name="sig" value="<?php echo \Fc2blog\Web\Session::get('sig'); ?>">

</form>

