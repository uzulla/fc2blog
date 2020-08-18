<header><h2><?php echo __('List of users'); ?></h2></header>

<?php $blogs_model = \Fc2blog\Model\Model::load('Blogs'); ?>

<?php foreach($users as $user): ?>
  <h3><?php echo h($user['login_id']); ?></h3>
  <?php $blogs = $blogs_model->findByUserId($user['id']); ?>
  <table>
    <tr>
      <th><?php echo __('Blog ID'); ?></th>
      <th><?php echo __('Blog name'); ?></th>
      <th><?php echo __('Nickname'); ?></th>
      <th>&nbsp;</th>
    </tr>
    <?php foreach ($blogs as $blog) : ?>
      <tr>
        <td style="width: 100px;"><?php echo h($blog['id']); ?></td>
        <td style="width: 200px;"><?php echo h($blog['name']); ?></td>
        <td><?php echo h($blog['nickname']); ?></td>
        <td style="width: 120px;"><a href="<?php echo \Fc2blog\Model\BlogsModel::getFullHostUrlByBlogId($blog['id'], \Fc2blog\Config::get('DOMAIN_USER')); ?><?php echo \Fc2blog\Web\Html::url($request, array('controller'=>'Entries', 'action'=>'index', 'blog_id'=>$blog['id'])); ?>" target="_blank"><?php echo __('Checking the blog'); ?></a></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endforeach; ?>

<?php $this->display($request, 'Common/paging.php', array('paging' => $paging)); ?>

