<?php
/**
 * Controllerの親クラス
 */

namespace Fc2blog\Web\Controller;

use Fc2blog\App;
use Fc2blog\Config;
use Fc2blog\Exception\RedirectExit;
use Fc2blog\Model\BlogsModel;
use Fc2blog\Util\Log;
use Fc2blog\Util\Twig\GetTextHelper;
use Fc2blog\Util\Twig\HtmlHelper;
use Fc2blog\Web\Controller\Admin\AdminController;
use Fc2blog\Web\Controller\User\UserController;
use Fc2blog\Web\Html;
use Fc2blog\Web\Request;
use Fc2blog\Web\Session;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

abstract class Controller
{
  protected $data = [];    // テンプレートへ渡す変数の保存領域
  protected $layout = '';  // 表示ページのレイアウトテンプレート
  protected $output = '';  // 送信するデータ、HTML等
  private $resolvedMethod;
  protected $request;

  public function __construct(Request $request)
  {
    $this->request = $request;
  }

  public function execute($method)
  {
    $template = $this->prepare($method);
    $this->render($template);
  }

  /**
   * render前のアクション実行処理群
   * @param string $method
   * @return string template file path
   */
  public function prepare(string $method): string
  {
    $this->beforeFilter($this->request);

    // アクションの実行(返り値はテンプレートファイルパスまたは空文字、レンダリング用データは$this->data)
    $this->resolvedMethod = $method;
    $template_path = $this->$method($this->request);

    // 空の場合は、規約に則ってテンプレートファイルを決定する
    if (empty($template_path)) {
      $template_path = strtolower($this->request->shortControllerName) . '/' . $method . '.php';
    }

    return $template_path;
  }

  /**
   * HTML(等)をレンダリングし、$this->outputに保存
   * @param string $template_path
   * @return void
   */
  public function render(string $template_path): void
  {
    // 出力を$this->outputで保持。後ほどemit()すること。
    // テンプレートファイル拡張子で、PHPテンプレートとTwigテンプレートを切り分ける
    if (preg_match("/\.twig\z/u", $template_path)) {
      $this->output = $this->renderByTwig($this->request, $template_path);
    } elseif ($this->layout === 'fc2_template.php') {
      $this->output = $this->renderByFc2Template($this->request, $template_path);
    } else { // $this->layout === '' を含む
      $this->output = "";
    }
  }

  /**
   * ヘッダーおよび$this->outputの送信
   * TODO Output Bufferによりエラー表示を隠蔽する
   */
  public function emit(): void
  {
    if (isset($this->data['http_status_code']) && is_int($this->data['http_status_code'])) {
      http_response_code($this->data['http_status_code']);
    }

    if (!headers_sent()) {
      // Content typeの送信
      if (isset($this->data['http_content_type']) && strlen(isset($this->data['http_content_type'])) > 0) {
        header("Content-Type: {$this->data['http_content_type']}");
      } else {
        header("Content-Type: text/html; charset=UTF-8");
      }
    }

    echo $this->output;
  }

  protected function beforeFilter(Request $request)
  {
  }

  public function set(string $key, $value)
  {
    $this->data[$key] = $value;
  }

  /**
   * リダイレクト
   * MEMO: Blog idが特定できないときの強制的なSchemaがさだまらない
   * TODO: この時点でリダイレクトせず、 emit時にヘッダー送信する形にリファクタリングすべき
   * @param Request $request
   * @param $url
   * @param string $hash
   * @param bool $full_url BlogIdが特定できるとき、http(s)://〜からのフルURLを出力する、 HTTP<>HTTPS強制リダイレクト時に必要
   * @param string|null $blog_id
   * @throws RedirectExit
   */
  protected function redirect(Request $request, $url, $hash = '', bool $full_url = false, string $blog_id = null)
  {
    if (is_array($url)) {
      $url = Html::url($request, $url, false, $full_url);

    } else if ($full_url && is_string($blog_id) && strlen($blog_id) > 0) {
      $url = BlogsModel::getFullHostUrlByBlogId($blog_id) . $url;

    } else if ($full_url && preg_match("|\A/([^/]+)/|u", $url, $match)) {
      // Blog idをURLから抜き出して利用
      $url = BlogsModel::getFullHostUrlByBlogId($match[1]) . $url;
      $blog_id = $match[1];
    }
    $url .= $hash;

    // デバッグ時にSessionにログを保存
    Log::debug_log(__FILE__ . ":" . __LINE__ . " " . 'Redirect[' . $url . ']');

    if (!is_null($blog_id) && $full_url) {
      $status_code = BlogsModel::getRedirectStatusCodeByBlogId($blog_id);
    } else {
      $status_code = 302;
    }
    // TODO Twig化が完了したら、Redirectをここで行わずに上位で行えるようにしたい（途中でのexitをなくしたい）
    if (!headers_sent()) {
      // full url指定時のリダイレクトは、Blogの設定がもつステータスコードを利用する
      header('Location: ' . $url, true, $status_code);
    }
    $escaped_url = h($url);
    if (defined("THIS_IS_TEST")) {
      $e = new RedirectExit(__FILE__ . ":" . __LINE__ . " redirect to {$escaped_url} status code:{$status_code} stack trace:" . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
      $e->redirectUrl = $url;
      $e->statusCode = $status_code;
      throw $e;
    } else {
      exit;
    }
  }

  /**
   * 前のURLに戻す リファラーが取れなければ引数のURLに飛ばす
   * @param Request $request
   * @param $url
   * @param string $hash
   */
  protected function redirectBack(Request $request, $url, $hash = '')
  {
    // 元のURLに戻す
    if (!empty($_SERVER['HTTP_REFERER'])) {
      $this->redirect($request, $_SERVER['HTTP_REFERER']);
    }
    // リファラーが取れなければメインへ飛ばす
    $this->redirect($request, $url, $hash);
  }

  /**
   * TwigテンプレートエンジンでHTMLをレンダリング
   * 結果は $this->output に保管される
   * @param Request $request
   * @param string $twig_template
   * @return string
   */
  private function renderByTwig(Request $request, string $twig_template): string
  {
    $base_path = realpath(__DIR__ . "/../../../twig_templates/") . "/";
    $loader = new FilesystemLoader($base_path);
    $twig = new Environment($loader);

    foreach (
      array_merge(
        (new GetTextHelper())->getFunctions(),
        (new HtmlHelper())->getFunctions(),
      ) as $function) {
      $twig->addFunction($function);
    }

    $twig_template_path = $twig_template;
    $twig_template_device_path = preg_replace("/\.twig\z/u", '_' . App::getDeviceTypeStr($request) . '.twig', $twig_template_path);
    if (is_file($base_path . $twig_template_device_path)) { // デバイス用ファイルがある
      $twig_template_path = $twig_template_device_path;
    }

    if (!is_file($base_path . $twig_template_path)) {
      throw new InvalidArgumentException("Twig error: missing template: {$base_path}{$twig_template_path}");
    }

    // テンプレートエンジンに引き渡すデータを生成
    $this->data['request'] = $request;
    if($this instanceof AdminController) {
      // Admin系データの生成
      $blogs_model = new BlogsModel();
      $data = [
        'req' => $request,
        'sig' => Session::get('sig'),
        'lang' => $request->lang,
        'debug' => Config::get('APP_DEBUG') != 0,
        'preview_active_blog_url' => App::userURL($request, ['controller' => 'entries', 'action' => 'index', 'blog_id' => $this->getBlogId($request)]), // 代用できそう
        'is_register_able' => (Config::get('USER.REGIST_SETTING.FREE') == Config::get('USER.REGIST_STATUS')), // TODO 意図する解釈確認
        'active_menu' => App::getActiveMenu($request),
        'isLogin' => $this->isLogin(),
        'nick_name' => $this->getNickName(),
        'blog_list' => $blogs_model->getSelectList($this->getUserId()),
        'is_selected_blog' => $this->isSelectedBlog(),
        'flash_messages' => $this->removeMessage(),
        'js_common' => [
          'isURLRewrite' => $request->urlRewrite,
          'baseDirectory' => $request->baseDirectory,
          'deviceType' => $request->deviceType,
          'deviceArgs' => App::getArgsDevice($request)
        ],
        'cookie_common' => [
          'expire' => Config::get('COOKIE_EXPIRE'),
          'domain' => Config::get('COOKIE_DEFAULT_DOMAIN')
        ]
      ];
      // リクエストからログインblogを特定し、保存
      if ($this->getBlog($this->getBlogId($request)) !== false && is_string($this->getBlogId($request))) {
        $data['blog'] = $this->getBlog($this->getBlogId($request));
        $data['blog']['url'] = BlogsModel::getFullHostUrlByBlogId($this->getBlogId($request), Config::get('DOMAIN_USER')) . "/" . $this->getBlogId($request) . "/";
      }
    }else{
      // User系画面のデータ生成
      $data = [
        'req' => $request,
        'lang' => $request->lang,
      ];
    }

    $data = array_merge($data, $this->data);

    try {
      return $twig->render($twig_template_path, $data);
    } catch (LoaderError $e) {
      throw new RuntimeException("Twig error: {$e->getMessage()} {$e->getFile()}:{$e->getTemplateLine()}");
    } catch (RuntimeError $e) {
      throw new RuntimeException("Twig error: {$e->getMessage()} {$e->getFile()}:{$e->getTemplateLine()}");
    } catch (SyntaxError $e) {
      throw new RuntimeException("Twig error: {$e->getMessage()} {$e->getFile()}:{$e->getTemplateLine()}");
    }
  }

  /**
   * fc2blog形式のPHP Viewテンプレート内で利用する各種データを生成・変換
   * @param Request $request
   * @param array $data
   * @return array
   * TODO アクションではないので、なんらか別クラスへ切り出すべき
   */
  static public function preprocessingDataForFc2Template(Request $request, array $data):array
  {
    $data['request'] = $request;

    // FC2のテンプレート用にデータを置き換える
    if (!empty($data['entry'])) {
      $data['entries'] = [$data['entry']];
    }
    if (!empty($data['entries'])) {
      foreach ($data['entries'] as $key => $value) {
        // topentry系変数のデータ設定
        $data['entries'][$key]['title_w_img'] = $value['title'];
        $data['entries'][$key]['title'] = strip_tags($value['title']);
        $data['entries'][$key]['link'] = App::userURL($request, ['controller' => 'Entries', 'action' => 'view', 'blog_id' => $value['blog_id'], 'id' => $value['id']]);

        [
          $data['entries'][$key]['year'],
          $data['entries'][$key]['month'],
          $data['entries'][$key]['day'],
          $data['entries'][$key]['hour'],
          $data['entries'][$key]['minute'],
          $data['entries'][$key]['second'],
          $data['entries'][$key]['youbi'],
          $data['entries'][$key]['month_short']
        ] = explode('/', date('Y/m/d/H/i/s/D/M', strtotime($value['posted_at'])));
        $data['entries'][$key]['wayoubi'] = __($data['entries'][$key]['youbi']);

        // 自動改行処理
        if ($value['auto_linefeed'] == Config::get('ENTRY.AUTO_LINEFEED.USE')) {
          $data['entries'][$key]['body'] = nl2br($value['body']);
          $data['entries'][$key]['extend'] = nl2br($value['extend']);
        }

        // topentry_enc_* 系タグの生成
        $data['entries'][$key]['enc_title'] = urlencode($data['entries'][$key]['title']);
        $data['entries'][$key]['enc_utftitle'] = urlencode($data['entries'][$key]['title']);
        $data['entries'][$key]['enc_link'] = urlencode($data['entries'][$key]['link']);
      }
    }

    // コメント一覧の情報
    if (!empty($data['comments'])) {
      foreach ($data['comments'] as $key => $value) {
        $data['comments'][$key]['edit_link'] = Html::url($request, ['controller' => 'Entries', 'action' => 'comment_edit', 'blog_id' => $value['blog_id'], 'id' => $value['id']]);

        [
          $data['comments'][$key]['year'],
          $data['comments'][$key]['month'],
          $data['comments'][$key]['day'],
          $data['comments'][$key]['hour'],
          $data['comments'][$key]['minute'],
          $data['comments'][$key]['second'],
          $data['comments'][$key]['youbi']
        ] = explode('/', date('Y/m/d/H/i/s/D', strtotime($value['updated_at'])));
        $data['comments'][$key]['wayoubi'] = __($data['comments'][$key]['youbi']);
        $data['comments'][$key]['body'] = $value['body']; // TODO nl2brされていないのは正しいのか？

        [
          $data['comments'][$key]['reply_year'],
          $data['comments'][$key]['reply_month'],
          $data['comments'][$key]['reply_day'],
          $data['comments'][$key]['reply_hour'],
          $data['comments'][$key]['reply_minute'],
          $data['comments'][$key]['reply_second'],
          $data['comments'][$key]['reply_youbi']
        ] = explode('/', date('Y/m/d/H/i/s/D', strtotime($value['reply_updated_at'])));
        $data['comments'][$key]['reply_wayoubi'] = __($data['comments'][$key]['reply_youbi']);
        $data['comments'][$key]['reply_body'] = nl2br($value['reply_body']);
      }
    }

    // FC2用のどこでも有効な単変数
    $data['url'] = '/' . $data['blog']['id'] . '/';
    $data['blog_id'] = UserController::getBlogId($request); // TODO User系でしかこのメソッドは呼ばれないはずなので

    // 年月日系
    $data['now_date'] = (isset($data['date_area']) && $data['date_area']) ? $data['now_date'] : date('Y-m-d');
    $data['now_month_date'] = date('Y-m-1', strtotime($data['now_date']));
    $data['prev_month_date'] = date('Y-m-1', strtotime($data['now_month_date'] . ' -1 month'));
    $data['next_month_date'] = date('Y-m-1', strtotime($data['now_month_date'] . ' +1 month'));

    return $data;
  }

  /**
   * FC2タグを用いたユーザーテンプレート（PHP）でHTMLをレンダリング
   * @param Request $request
   * @param string $template_file_path
   * @return string
   * TODO User系のみで用いられるので、後日UserControllerへ移動
   */
  private function renderByFc2Template(Request $request, string $template_file_path)
  {
    if (is_null($template_file_path)) {
      throw new InvalidArgumentException("undefined template");
    }
    if (!is_file($template_file_path)) {
      throw new InvalidArgumentException("missing template");
    }

    $this->data = static::preprocessingDataForFc2Template($request, $this->data);

    // 設定されているdataを展開
    extract($this->data);

    // テンプレートをレンダリングして返す
    ob_start();
    /** @noinspection PhpIncludeInspection */
    include($template_file_path);
    return ob_get_clean();
  }

  // 存在しないアクションは404へ
  // TODO 存在しないメソッドコールはエラーにしたほうがわかりやすい
  public function __call($name, $arguments)
  {
    return $this->error404();
  }

  // 404 NotFound Action
  public function error404()
  {
    $this->data['http_status_code'] = 404;
    return 'user/common/error404.twig';
  }

  public function get(string $key)
  {
    return $this->data[$key];
  }

  public function getOutput(): string
  {
    if (!defined("THIS_IS_TEST")) {
      throw new LogicException("the method is only for testing.");
    }
    return $this->output;
  }

  public function getResolvedMethod(): string
  {
    if (!defined("THIS_IS_TEST")) {
      throw new LogicException("the method is only for testing.");
    }
    return $this->resolvedMethod;
  }

  public function getRequest(): Request
  {
    if (!defined("THIS_IS_TEST")) {
      throw new LogicException("the method is only for testing.");
    }
    return $this->request;
  }

  public function getData(): array
  {
    if (!defined("THIS_IS_TEST")) {
      throw new LogicException("the method is only for testing.");
    }
    return $this->data;
  }
}
