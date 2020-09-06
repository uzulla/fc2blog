<?php

namespace Fc2blog\Web\Controller\Admin;

use Fc2blog\Config;
use Fc2blog\Model\Model;
use Fc2blog\Model\TagsModel;
use Fc2blog\Web\Request;
use Fc2blog\Web\Session;

class TagsController extends AdminController
{

  /**
   * 一覧表示
   * @param Request $request
   * @return string
   */
  public function index(Request $request): string
  {
    $tags_model = new TagsModel();
    $blog_id = $this->getBlogId($request);
    $request->generateNewSig();

    $this->set('tag_limit_list', Config::get('TAG.LIMIT_LIST'));
    $this->set('tag_default_limit', Config::get('TAG.DEFAULT_LIMIT'));

    // 検索条件作成
    $where = 'blog_id=?';
    $params = [$blog_id];

    if ($name = $request->get('name')) {
      $name = Model::escape_wildcard($name);
      $name = "%{$name}%";
      $where .= ' AND name LIKE ?';
      $params = array_merge($params, [$name]);
    }

    // 並び順
    $order = 'count DESC, id DESC';
    switch ($request->get('order')) {
      default:
      case 'count_desc':
        break;
      case 'count_asc':
        $order = 'count ASC, id ASC';
        break;
      case 'name_desc':
        $order = 'name DESC, id DESC';
        break;
      case 'name_asc':
        $order = 'name ASC, id ASC';
        break;
    }
    $options = [
      'where' => $where,
      'params' => $params,
      'limit' => $request->get('limit', Config::get('TAG.DEFAULT_LIMIT'), Request::VALID_POSITIVE_INT),
      'page' => $request->get('page', 0, Request::VALID_UNSIGNED_INT),
      'order' => $order,
    ];
    if ($options['limit'] > max(array_keys(Config::get('TAG.LIMIT_LIST')))) {
      $options['limit'] = Config::get('TAG.DEFAULT_LIMIT');
    }
    if (ceil(PHP_INT_MAX / $options['limit']) <= $options['page']) {
      $options['page'] = 0;
    }
    $tags = $tags_model->find('all', $options);
    $paging = $tags_model->getPaging($options);

    $this->set('tags', $tags);
    $this->set('paging', $paging);
    $this->set('page_list', Model::getPageList($paging));

    return "admin/tags/index.twig";
  }

  /**
   * 編集
   * @param Request $request
   */
  public function edit(Request $request)
  {
    /** @var TagsModel $tags_model */
    $tags_model = Model::load('Tags');

    $id = $request->get('id');
    $blog_id = $this->getBlogId($request);

    if (!$tag = $tags_model->findByIdAndBlogId($id, $blog_id)) {
      $this->redirect($request, array('action' => 'index'));
    }
    $this->set('tag', $tag);

    // 初期表示時に編集データの取得&設定
    if (!$request->get('tag') || !Session::get('sig') || Session::get('sig') !== $request->get('sig')) {
      $request->set('tag', $tag);
      $back_url = $request->getReferer();
      if (!empty($back_url)) {
        $request->set('back_url', $request->getReferer());    // 戻る用のURL
      }
      return;
    }

    // 更新処理
    $tag_request = $request->get('tag');
    $tag_request['id'] = $id;
    $tag_request['blog_id'] = $blog_id;
    $errors['tag'] = $tags_model->validate($tag_request, $data, array('name'));
    if (empty($errors['tag'])) {
      if ($tags_model->updateByIdAndBlogId($data, $id, $blog_id)) {
        $this->setInfoMessage(__('I have updated the tag'));

        // 元の画面へ戻る
        $back_url = $request->get('back_url');
        if (!empty($back_url)) {
          $this->redirect($request, $back_url);
        }
        $this->redirect($request, array('action' => 'index'));
      }
    }

    // エラー情報の設定
    $this->setErrorMessage(__('Input error exists'));
    $this->set('errors', $errors);
  }

  /**
   * 削除
   * @param Request $request
   */
  public function delete(Request $request)
  {
    if (Session::get('sig') && Session::get('sig') === $request->get('sig')) {
      // 削除処理
      if (Model::load('Tags')->deleteByIdsAndBlogId($request->get('id'), $this->getBlogId($request))) {
        $this->setInfoMessage(__('I removed the tag'));
      } else {
        $this->setErrorMessage(__('I failed to remove'));
      }
    }

    // 元の画面へ戻る
    $back_url = $request->get('back_url');
    if (!empty($back_url)) {
      $this->redirect($request, $back_url);
    }
    $this->redirectBack($request, array('action' => 'index'));
  }

}

