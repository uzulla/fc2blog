<?php
declare(strict_types=1);

namespace Fc2blog\Tests\Helper\SampleDataGenerator;

use Fc2blog\Config;
use Fc2blog\Model\BlogSettingsModel;
use Fc2blog\Model\CommentsModel;
use InvalidArgumentException;
use RuntimeException;

class GenerateSampleComment
{
  use FakerTrait;
  use RandomUtilTrait;

  public function generateSampleComment(string $blog_id, int $entry_id, int $num = 10): array
  {
    $faker = static::getFaker();
    $comment_list = [];

    $open_status_list = [
      Config::get('COMMENT.OPEN_STATUS.PUBLIC'),
      Config::get('COMMENT.OPEN_STATUS.PRIVATE')
    ];

    while ($num-- > 0) {
      $request_comment = [
        'entry_id' => $entry_id,
        'name' => $faker->name,
        'title' => $faker->sentence(3),
        'mail' => $faker->email,
        'url' => $faker->url,
        'body' => $faker->text(500),
        'password' => "password",
        'open_status' => static::getRandomValue($open_status_list)
      ];

      // 入力チェック処理
      $white_list = ['entry_id', 'name', 'title', 'mail', 'url', 'body', 'password', 'open_status'];

      $comments_model = new CommentsModel();
      $errors_comment = $comments_model->registerValidate($request_comment, $data, $white_list);
      if (count($errors_comment) > 0) {
        throw new InvalidArgumentException("validation failed:" . print_r($errors_comment, true) . print_r($request_comment, true));
      }

      $data['blog_id'] = $blog_id;  // ブログIDの設定

      $blog_setting_model = new BlogSettingsModel();
      $blog_setting = $blog_setting_model->findByBlogId($blog_id);

      $id = $comments_model->insertByBlogSettingWithOutCookie($data, $blog_setting);

      if ($id === false) {
        throw new RuntimeException("insert failed:" . print_r($data, true) . print_r($blog_setting, true));
      }

      $comment_list[] = $comments_model->findById($id);
    }

    return $comment_list;
  }
}
