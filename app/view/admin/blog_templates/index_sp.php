<?php throw new LogicException("Already converted to twig. something wrong."); ?>
<header><h1 class="sh_heading_main_b"><?php echo __('Template management'); ?></h1></header>

<div class="form_area">
  <div class="form_contents">
    <?php $devices = \Fc2blog\Config::get('DEVICE_NAME'); ?>
    <select onchange="location.href=$(this).val();">
      <?php foreach ($devices as $key => $device): ?>
        <option value="<?php echo \Fc2blog\Web\Html::url($request, array('device_type'=>$key)); ?>" <?php if ($request->get('device_type')==$key) echo 'selected="selected"'; ?>><?php echo $device; ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?php $devices = \Fc2blog\Config::get('DEVICE_NAME'); ?>
<?php foreach($device_blog_templates as $device_type => $blog_templates): ?>
  <div class="btn_area sp_no_template">
    <ul class="btn_area_inner">
      <li><button class="btn_contents touch" onclick="location.href='<?php echo \Fc2blog\Web\Html::url($request, array('controller'=>'BlogTemplates','action'=>'fc2_index', 'device_type'=>$device_type)); ?>'"><i class="btn_icon"></i><?php echo __('Template Search'); ?></button></li>
    </ul>
  </div>
  <h2><span class="h2_inner"><?php echo $devices[$device_type]; ?></span></h2>
  <ul class="list_radio">
    <?php foreach($blog_templates as $blog_template): ?>
    <li class="list_radio_item">
      <input name="blog_template[id]" type="radio" class="list_radio_input" value="<?php echo $blog_template['id']; ?>"
             id="sys-device-<?php echo $device_type; ?>-<?php echo $blog_template['id']; ?>"<?php if (in_array($blog_template['id'], $template_ids)): ?> disabled="disabled"<?php endif; ?>>
      <label for="sys-device-<?php echo $device_type; ?>-<?php echo $blog_template['id']; ?>">
        <?php echo th($blog_template['title'], 20); ?><?php if (in_array($blog_template['id'], $template_ids)): ?>
        <span class="contents_status"><i class="green_check_icon btn_icon"></i><span class="check_icon_text"><?php echo __('Applying'); ?></span></span><?php endif; ?>
      </label>
    </li>
    <?php endforeach; ?>
  </ul>
<?php endforeach; ?>

<div class="btn_area sp_no_template">
  <ul class="btn_area_inner">
    <li><button id="sys_template_delete" class="btn_contents touch"><i class="delete_icon btn_icon"></i><?php echo __('Delete'); ?></button></li>
    <li><button id="sys_template_adapt" class="btn_contents positive touch"><i class="check_icon btn_icon"></i><?php echo __('Apply'); ?></button></li>
  </ul>
</div>

<script>
$(function(){
  // テンプレートの削除
  $('#sys_template_delete').on('click', function(){
    var id = $('input[name="blog_template[id]"]:checked').val()
    if (!id) {
      alert('<?php echo __('Please select the template you want to delete'); ?>');
      return ;
    }
    if (confirm('<?php echo __('Are you sure you want to delete?'); ?>')) {
      location.href = common.fwURL('blog_templates', 'delete', {id: id, sig: "<?php echo \Fc2blog\Web\Session::get('sig'); ?>"});
    }
  });
  // テンプレートの適応
  $('#sys_template_adapt').on('click', function(){
    var id = $('input[name="blog_template[id]"]:checked').val()
    if (!id) {
      alert('<?php echo __('Please select the template to apply'); ?>');
      return ;
    }
    if (confirm('<?php echo __('Are you sure you want to apply this template?'); ?>')) {
      location.href = common.fwURL('blog_templates', 'apply', {id: id, sig: "<?php echo \Fc2blog\Web\Session::get('sig'); ?>"});
    }
  });
});
</script>

