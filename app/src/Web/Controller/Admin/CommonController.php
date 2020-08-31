<?php

namespace Fc2blog\Web\Controller\Admin;

use Exception;
use Fc2blog\App;
use Fc2blog\Config;
use Fc2blog\Model\BlogsModel;
use Fc2blog\Model\Model;
use Fc2blog\Model\MSDB;
use Fc2blog\Model\PluginsModel;
use Fc2blog\Model\UsersModel;
use Fc2blog\Web\Cookie;
use Fc2blog\Web\Request;

class CommonController extends AdminController
{

  /**
   * 言語設定変更
   * @param Request $request
   */
  public function lang(Request $request)
  {
    // 言語の設定
    $lang = $request->get('lang');
    if ($language = Config::get('LANGUAGES.' . $lang)) {
      Cookie::set($request, 'lang', $lang);
    }

    // TOPへ戻す
    $url = Config::get('BASE_DIRECTORY');
    $device_name = App::getArgsDevice($request);
    if (!empty($device_name)) {
      $url .= '?' . $device_name;
    }
    $this->redirectBack($request, $url);
  }

  /**
   * デバイス変更
   * @param Request $request
   */
  public function device_change(Request $request)
  {
    // デバイスの設定
    $device_type = 0;
    $device = $request->get('device');
    switch ($device) {
      case 'pc':
        $device_type = Config::get('DEVICE_PC');
        break;
      case 'sp':
        $device_type = Config::get('DEVICE_SP');
        break;
      default:
        Cookie::set($request, 'device', null);
        $this->redirectBack($request, array('controller' => 'entries', 'action' => 'index'));
    }

    Cookie::set($request, 'device', $device_type);
    $this->redirectBack($request, array('controller' => 'entries', 'action' => 'index'));
  }

  /**
   * 初期表示ページ(ブログの設定よりリダイレクト)
   * @param Request $request
   */
  public function initial(Request $request)
  {
    $setting = Model::load('BlogSettings')->findByBlogId($this->getBlogId($request));
    if (is_array($setting)) {
      switch ($setting['start_page']) {
        default:
        case Config::get('BLOG.START_PAGE.NOTICE'):
          $this->redirect($request, array('controller' => 'Common', 'action' => 'notice'));
          break;

        case Config::get('BLOG.START_PAGE.ENTRY'):
          $this->redirect($request, array('controller' => 'Entries', 'action' => 'create'));
          break;
      }
    } else {
      $this->redirect($request, array('controller' => 'Common', 'action' => 'notice'));
    }
  }

  public function index(Request $request)
  {
    return $this->initial($request);
  }

  /**
   * お知らせ一覧画面
   * @param Request $request
   */
  public function notice(Request $request)
  {
    $blog_id = $this->getBlogId($request);

    $comments_model = Model::load('Comments');
    $this->set('unread_count', $comments_model->getUnreadCount($blog_id));
    $this->set('unapproved_count', $comments_model->getUnapprovedCount($blog_id));
  }

  /**
   * インストール画面
   * @param Request $request
   * @return string|void
   */
  public function install(Request $request)
  {
    $this->layout = 'default_nomenu.php';

    $state = $request->get('state', 0);

    // インストール済みロックファイルをチェックする。ロックファイルがあればインストール済みと判定し、完了画面へ
    $installed_lock_file_path = Config::get('TEMP_DIR') . "installed.lock";
    if (file_exists($installed_lock_file_path)) {
      $state = 3;
    }

    switch ($state) {
      default:
      case 0:
        // 環境チェック確認

        // ディレクトリ書き込みパーミッション確認
        $is_write_temp = is_writable(Config::get('TEMP_DIR') . '.');
        $this->set('is_write_temp', $is_write_temp);
        $is_write_upload = is_writable(Config::get('WWW_UPLOAD_DIR') . '.');
        $this->set('is_write_upload', $is_write_upload);

        // DBライブラリ確認
        $is_db_connect_lib = defined('DB_CONNECT_LIB');
        $this->set('is_db_connect_lib', $is_db_connect_lib);

        // DB疎通確認
        $is_connect = true;
        $connect_message = '';
        try {
          MSDB::getInstance()->connect(false, false);
        } catch (Exception $e) {
          $is_connect = false;
          $connect_message = $e->getMessage();
        }
        $this->set('is_connect', $is_connect);
        $this->set('connect_message', $connect_message);

        // DB設定確認
        $is_character = version_compare(MSDB::getInstance()->getVersion(), '5.5.0') >= 0 || DB_CHARSET != 'UTF8MB4';
        $this->set('is_character', $is_character);

        // ドメイン確認
        $is_domain = DOMAIN != 'domain';
        $this->set('is_domain', $is_domain);

        // GDインストール済み確認
        $is_gd = function_exists('gd_info');
        $this->set('is_gd', $is_gd);

        // salt変更済み確認
        $is_salt = PASSWORD_SALT != '0123456789abcdef';
        $this->set('is_salt', $is_salt);

        $is_all_ok = $is_write_temp && $is_write_upload && $is_db_connect_lib && $is_connect && $is_character && $is_domain && $is_salt;
        $this->set('is_all_ok', $is_all_ok);

        return "";

      case 1:
        // 各種初期設定、DB テーブル作成、ディレクトリ作成

        // フォルダの作成
        !file_exists(Config::get('TEMP_DIR') . 'blog_template') && mkdir(Config::get('TEMP_DIR') . 'blog_template', 0777, true);
        !file_exists(Config::get('TEMP_DIR') . 'log') && mkdir(Config::get('TEMP_DIR') . 'log', 0777, true);

        // ディレクトリ製作成功チェック
        if (!file_exists(Config::get('TEMP_DIR') . 'log') || !file_exists(Config::get('TEMP_DIR') . 'blog_template')) {
          $this->setErrorMessage(__('Create /app/temp/blog_template and log directory failed.'));
          $this->redirect($request, Config::get('BASE_DIRECTORY') . 'common/install?state=1&error=mkdir');
        }

        // DB接続確認
        $msdb = MSDB::getInstance(true);
        try {
          // DB接続確認(DATABASEの存在判定含む)
          $msdb->connect();
        } catch (Exception $e) {
          // データベースの作成
          $msdb->close();
          $msdb->connect(false, false);
          $sql = 'CREATE DATABASE IF NOT EXISTS ' . DB_DATABASE . ' CHARACTER SET ' . DB_CHARSET;
          $msdb->execute($sql);
          $msdb->close();
          try {
            // 作成できたか確認
            $msdb->connect();
          } catch (Exception $e) {
            $this->setErrorMessage(__('Create database failed.'));
            $this->redirect($request, Config::get('BASE_DIRECTORY') . 'common/install?state=1&error=db_create');
          }
        }

        // テーブルの存在チェック
        $sql = "SHOW TABLES LIKE 'users'";
        $table = MSDB::getInstance()->find($sql);

        if (is_countable($table) && count($table)) {
          // 既にDB登録完了
          $this->redirect($request, Config::get('BASE_DIRECTORY') . 'common/install?state=2');
        }

        // DBセットアップ
        $sql_path = Config::get('APP_DIR') . 'db/0_initialize.sql';
        $sql = file_get_contents($sql_path);
        if (DB_CHARSET != 'UTF8MB4') {
          $sql = str_replace('utf8mb4', strtolower(DB_CHARSET), $sql);
        }
        $res = MSDB::getInstance()->multiExecute($sql);
        if ($res === false) {
          $this->setErrorMessage(__('Create table failed.'));
          $this->redirect($request, Config::get('BASE_DIRECTORY') . 'common/install?state=1&error=table_insert');
        }

        // DBセットアップ成功チェック
        $sql = "SHOW TABLES LIKE 'users'";
        $table = MSDB::getInstance()->find($sql);
        if (!is_countable($table)) {
          $this->setErrorMessage(__('Create table failed.'));
          $this->redirect($request, Config::get('BASE_DIRECTORY') . 'common/install?state=1&error=table_insert');
        }

        // 初期公式プラグインを追加
        $plugins_model = new PluginsModel();
        $plugins_model->addInitialOfficialPlugin();

        $this->redirect($request, Config::get('BASE_DIRECTORY') . 'common/install?state=2');
        return "";

      case 2:  // 管理者登録
        $users = new UsersModel();
        if ($users->isExistAdmin()) {
          // 既に管理者ユーザー登録完了済み
          $this->redirect($request, Config::get('BASE_DIRECTORY') . 'common/install?state=3');
        }

        // ユーザー登録画面を表示
        if (!$request->get('user')) {
          return 'common/install_user.php';
        }

        // 以下はユーザー登録実行
        $users_model = new UsersModel();
        $blogs_model = new BlogsModel();

        // ユーザーとブログの新規登録処理
        $errors = [];
        $errors['user'] = $users_model->registerValidate($request->get('user'), $user_data, array('login_id', 'password'));
        $errors['blog'] = $blogs_model->validate($request->get('blog'), $blog_data, array('id', 'name', 'nickname'));
        if (empty($errors['user']) && empty($errors['blog'])) {
          $user_data['type'] = Config::get('USER.TYPE.ADMIN');
          $user_id = $users_model->insert($user_data);
          $blog_data['user_id'] = $user_id;
          if ($blog_data['user_id'] && $blog_id = $blogs_model->insert($blog_data)) {
            // userのlogin_blog_idを更新
            $user_data['login_blog_id'] = $blog_id;
            $users_model->updateById($user_data, $user_id);

            // 成功したので完了画面へリダイレクト
            $this->setInfoMessage(__('User registration is completed'));
            $this->redirect($request, Config::get('BASE_DIRECTORY') . 'common/install?state=3');

          } else {
            // ブログ作成失敗時には登録したユーザーを削除（ロールバックの代用）
            $users_model->deleteById($blog_data['user_id']);

          }
          $this->setErrorMessage(__('I failed to register'));
          return 'common/install_user.php';
        }

        // エラー情報の設定
        $this->setErrorMessage(__('Input error exists'));
        $this->set('errors', $errors);

        return 'common/install_user.php';

      case 3:
        // 完了画面

        // 完了画面表示と同時に、インストール済みロックファイルの生成
        file_put_contents($installed_lock_file_path, "This is installed check lockfile.\nThe blog already installed. if you want re-enable installer, please delete this file.");

        return 'common/installed.php';
    }
  }
}
