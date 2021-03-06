<?php /** @noinspection PhpUndefinedMethodInspection */
declare(strict_types=1);

namespace Fc2blog\Model;

use PDOStatement;

trait QueryTrait
{
    /**
     * LIKE検索用にワイルドカードのエスケープ
     * @param string $str
     * @return string
     */
    public static function escape_wildcard(string $str): string
    {
        return addcslashes($str, '\\_%');
    }


    /**
     * @param string $sql
     * @param array $params
     * @param array $options
     * @return array|int 失敗時False、成功時はOptionにより不定
     */
    public function executeSql(string $sql, array $params = [], array $options = [])
    {
        return (new PDOQuery())->execute(PDOConnection::getInstance()->pdo, $sql, $params, $options);
    }

    /**
     * @param string $sql
     * @param array $params
     * @param array $options
     * @return array
     * @throw PDOException
     */
    public function findSql(string $sql, array $params = [], array $options = [])
    {
        return (new PDOQuery())->find(PDOConnection::getInstance()->pdo, $sql, $params, $options);
    }

    /**
     * 指定のOptionでSELECTされる件数を取得
     * @param array $options
     * @return int
     */
    public function getFoundRows(array $options = []): int
    {
        unset($options['limit']);
        unset($options['offset']);
        /** @var PDOStatement $stmt */
        $stmt = $this->find('statement', $options);
        return $stmt->rowCount();
    }

    /**
     * @param string $type
     * @param array $options
     * @return array
     * @throw PDOException
     */
    public function find(string $type, array $options = [])
    {
        if (!isset($options['options'])) {
            $options['options'] = [];
        }
        if (!isset($options['params'])) {
            $options['params'] = [];
        }
        switch ($type) {
            case 'count':
                $options['fields'] = 'COUNT(*)';
                $options['limit'] = 1;
                $options['options']['result'] = PDOQuery::RESULT_ONE;
                break;
            case 'one':
                $options['limit'] = 1;
                $options['options']['result'] = PDOQuery::RESULT_ONE;
                break;
            case 'row':
                $options['limit'] = 1;
                $options['options']['result'] = PDOQuery::RESULT_ROW;
                break;
            case 'list':
                $options['options']['result'] = PDOQuery::RESULT_LIST;
                break;
            case 'all':
                $options['options']['result'] = PDOQuery::RESULT_ALL;
                break;
            case 'statement':
            default:
                $options['options']['result'] = PDOQuery::RESULT_STAT;
                break;
        }
        $fields = '*';
        if (isset($options['fields'])) {
            $fields = is_array($options['fields']) ? implode(',', $options['fields']) : $options['fields'];
        }
        if (!empty($options['limit']) && isset($options['page'])) {
            $fields = 'SQL_CALC_FOUND_ROWS ' . $fields;
        }
        $sql = 'SELECT ' . $fields . ' FROM ' . $this->getTableName();
        if (!empty($options['from'])) {
            if (is_array($options['from'])) {
                $sql .= ', ' . implode(',', $options['from']);
            } else {
                $sql .= ', ' . $options['from'];
            }
        }
        if (isset($options['where']) && $options['where'] != "") {
            $sql .= ' WHERE ' . $options['where'];
        }
        if (isset($options['group']) && $options['group'] != "") {
            $sql .= ' GROUP BY ' . $options['group'];
        }
        if (isset($options['order']) && $options['order'] != "") {
            $sql .= ' ORDER BY ' . $options['order'];
        }
        if (!empty($options['limit'])) {
            $sql .= ' LIMIT ' . $options['limit'];
            if (isset($options['page'])) {
                $sql .= ' OFFSET ' . $options['limit'] * $options['page'];
            } else if (isset($options['offset'])) {
                $sql .= ' OFFSET ' . $options['offset'];
            }
        }
        return $this->executeSql($sql, $options['params'], $options['options']);
    }

    /**
     * 主キーをキーにしてデータを取得
     * @param int|string|null $id
     * @param array $options
     * @return array
     */
    public function findById($id, array $options = [])
    {
        if (empty($id)) {
            return [];
        }
        $options['where'] = isset($options['where']) ? 'id=? AND ' . $options['where'] : 'id=?';
        $options['params'] = isset($options['params']) ? array_merge([$id], $options['params']) : [$id];
        return $this->find('row', $options);
    }

    /**
     * idとblog_idの複合キーからデータを取得
     * @param int|string|null $id
     * @param string|null $blog_id
     * @param array $options
     * @return array
     */
    public function findByIdAndBlogId($id, ?string $blog_id, array $options = [])
    {
        if (empty($id) || empty($blog_id)) {
            return [];
        }
        $options['where'] = isset($options['where']) ? 'blog_id=? AND id=? AND ' . $options['where'] : 'blog_id=? AND id=?';
        $options['params'] = isset($options['params']) ? array_merge([$blog_id, $id], $options['params']) : [$blog_id, $id];
        return $this->find('row', $options);
    }

    /**
     * idとuser_idのキーからデータを取得
     * @param $id
     * @param int $user_id
     * @param array $options
     * @return array
     */
    public function findByIdAndUserId($id, int $user_id, array $options = [])
    {
        if (empty($id) || empty($user_id)) {
            return [];
        }
        $options['where'] = isset($options['where']) ? 'id=? AND user_id=? AND ' . $options['where'] : 'id=? AND user_id=?';
        $options['params'] = isset($options['params']) ? array_merge([$id, $user_id], $options['params']) : [$id, $user_id];
        return $this->find('row', $options);
    }

    /**
     * 存在するかどうかを取得
     * @param array $options
     * @return bool
     */
    public function isExist(array $options = []): bool
    {
        return !!$this->find('row', $options);
    }


    /**
     * @param array $values
     * @param array $options
     * @return array|int 失敗時:false, 成功時 last insert id
     */
    public function insert(array $values, array $options = [])
    {
        if (!count($values)) {
            return 0;
        }
        $tableName = $this->getTableName();
        $compositeKey = $this->getAutoIncrementCompositeKey();
        if ($compositeKey && empty($values['id']) && !empty($values[$compositeKey])) {
            // 複合キーのauto_increment対応
            /** @noinspection SqlResolve */
            /** @noinspection SqlInsertValues */
            $sql = 'INSERT INTO ' . $tableName . ' (id, ' . implode(',', array_keys($values)) . ') '
                . 'VALUES ((SELECT LAST_INSERT_ID(COALESCE(MAX(id), 0)+1) FROM ' . $tableName . ' as auto_increment_temp '
                . 'WHERE ' . $compositeKey . '=?), ' . implode(',', array_fill(0, count($values), '?')) . ')';
            $value = $values[$compositeKey];
            $values = array_values($values);
            array_unshift($values, $value);
        } else {
            // 通常のINSERT
            $sql = 'INSERT INTO ' . $tableName . ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')';
            $values = array_values($values);
        }
        if (!isset($options['result'])) {
            $options['result'] = PDOQuery::RESULT_INSERT_ID;
        }
        return $this->executeSql($sql, $values, $options);
    }

    /**
     * @param array $values
     * @param string $where
     * @param array $params
     * @param array $options
     * @return array|int 失敗時:false, 成功時:1
     */
    public function update(array $values, string $where, array $params = [], array $options = [])
    {
        if (count($values) === 0) {
            return 0;
        }
        $sets = [];
        foreach ($values as $key => $value) {
            $sets[] = $key . '=?';
        }
        $sql = 'UPDATE ' . $this->getTableName() . ' SET ' . implode(',', $sets) . ' WHERE ' . $where;
        $params = array_merge(array_values($values), $params);
        $options['result'] = PDOQuery::RESULT_SUCCESS;
        return $this->executeSql($sql, $params, $options);
    }

    /**
     * idをキーとした更新
     * @param array $values
     * @param $id
     * @param array $options
     * @return array|int 失敗時:false, 成功時:1
     */
    public function updateById(array $values, $id, array $options = [])
    {
        return $this->update($values, 'id=?', [$id], $options);
    }

    /**
     * idとblog_idをキーとした更新
     * @param array $values
     * @param $id
     * @param string $blog_id
     * @param array $options
     * @return array|int 失敗時 False 、成功時1
     */
    public function updateByIdAndBlogId(array $values, $id, string $blog_id, array $options = [])
    {
        return $this->update($values, 'id=? AND blog_id=?', [$id, $blog_id], $options);
    }

    /**
     * @param string $where
     * @param array $params
     * @param array $options
     * @return array|int 失敗時:false, 成功時:1
     */
    public function delete(string $where, array $params = [], array $options = [])
    {
        $sql = 'DELETE FROM ' . $this->getTableName() . ' WHERE ' . $where;
        $options['result'] = PDOQuery::RESULT_SUCCESS;
        return $this->executeSql($sql, $params, $options);
    }

    /**
     * idをキーとした削除
     * @param $id
     * @param array $options
     * @return array|int 失敗時:false, 成功時:1
     */
    public function deleteById($id, array $options = [])
    {
        return $this->delete('id=?', [$id], $options);
    }

    /**
     * idとblog_idをキーとした削除
     * @param $id
     * @param string $blog_id
     * @param array $options
     * @return array|int 失敗時:false, 成功時:1
     */
    public function deleteByIdAndBlogId($id, string $blog_id, array $options = [])
    {
        return $this->delete('blog_id=? AND id=?', [$blog_id, $id], $options);
    }

    /**
     * idとuser_idをキーとした削除
     * @param $id
     * @param int $user_id
     * @param array $options
     * @return array|int 失敗時:false, 成功時:1
     */
    public function deleteByIdAndUserId($id, int $user_id, array $options = [])
    {
        return $this->delete('id=? AND user_id=?', [$id, $user_id], $options);
    }

    /**
     * バルクインサート
     * @param array $columns
     * @param array $params
     * @param array $options
     * @return array|int false時失敗, 成功時はlast insert idだが、複数INSERTなので活用は難しい
     */
    public function multipleInsert(array $columns = [], array $params = [], array $options = [])
    {
        $sql = 'INSERT INTO ' . $this->getTableName() . ' (' . implode(',', $columns) . ') VALUES ';
        $len = count($params) / count($columns);
        $sql_array = [];
        for ($i = 0; $i < $len; $i++) {
            $sql_array[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        }
        $sql .= implode(',', $sql_array);
        $options['result'] = PDOQuery::RESULT_INSERT_ID;
        return $this->executeSql($sql, $params, $options);
    }


    /**
     * 階層構造の一覧取得
     * @param array $options
     * @return array
     */
    public function findNode(array $options)
    {
        $options['order'] = 'lft ASC';
        $nodes = $this->find('all', $options);

        // levelを付与
        $level = 0;
        $levels = [];
        foreach ($nodes as $key => $value) {
            // 最初のノード
            if ($level == 0) {
                $levels[] = $value;
                $nodes[$key]['level'] = $level = count($levels);
                continue;
            }
            // left=left+1であれば子供ノードとして解釈
            if ($value['lft'] == $levels[$level - 1]['lft'] + 1) {
                $levels[] = $value;
                $nodes[$key]['level'] = $level = count($levels);
                continue;
            }
            // left=right+1であれば兄弟ノードとして解釈
            if ($value['lft'] == $levels[$level - 1]['rgt'] + 1) {
                $levels[$level - 1] = $value;
                $nodes[$key]['level'] = $level;
                continue;
            }
            // 兄弟ノードになるまで階層を遡る
            while (array_pop($levels)) {
                $level = count($levels);
                if ($value['lft'] == $levels[$level - 1]['rgt'] + 1) {
                    $levels[$level - 1] = $value;
                    $nodes[$key]['level'] = $level;
                    break;
                }
            }
        }
        return $nodes;
    }

    /**
     * 階層構造の追加
     * @param array $data 追加するノード情報
     * @param string $where 親ノード検索時のwhere句
     * @param array $params 親ノード検索時のバインドデータ
     * @param array $options
     * @return array|false|int falseか、Last insert id
     */
    public function addNode(array $data = [], string $where = '', array $params = [], array $options = [])
    {
        // 親として末尾に追加する場合
        if (empty($data['parent_id'])) {
            $max_right = $this->find('one', array('fields' => 'MAX(rgt)', 'where' => $where, 'params' => $params, 'options' => $options));
            // 親として末尾に追加
            $data['lft'] = $max_right + 1;
            $data['rgt'] = $max_right + 2;
            return $this->insert($data);
        }

        // 親の子供として末尾に追加する場合
        $parent = $this->findById($data['parent_id'], array('fields' => 'rgt', 'where' => $where, 'params' => $params, 'options' => $options));
        if (!$parent) {
            return false;
        }
        $right = $parent['rgt'];

        // 挿入する場所を確保する
        $table = $this->getTableName();
        if ($where != "") {
            $where .= ' AND ';
        }
        /** @noinspection SqlResolve */
        /** @noinspection SqlCaseVsIf */
        $updateSql = <<<SQL
        UPDATE {$table} SET
           lft = CASE WHEN lft > {$right} THEN lft + 2 ELSE lft END,
           rgt = CASE WHEN rgt >= {$right} THEN rgt + 2 ELSE rgt END
        WHERE {$where} rgt >= {$right}
        SQL;

        if (!$this->executeSql($updateSql, $params)) {
            return false;
        }

        // 子供として末尾に追加
        $data['lft'] = $right;
        $data['rgt'] = $right + 1;
        return $this->insert($data);
    }

    /**
     * 階層構造の更新
     * @param array $data
     * @param string|int $id
     * @param string $where
     * @param array $params
     * @param array $options
     * @return false|int
     */
    public function updateNodeById(array $data, string $id, string $where = '', array $params = [], array $options = [])
    {
        $idWhere = $where ? 'id=? AND ' . $where : 'id=?';

        // 自身取得
        $self_params = array_merge(array($id), $params);
        $self = $this->find('row', array('where' => $idWhere, 'params' => $self_params, 'options' => $options));
        if (!$self) {
            return false;
        }

        // 親が変更されていない場合そのまま更新
        if ($self['parent_id'] == $data['parent_id']) {
            return $this->update($data, $idWhere, $self_params, $options);
        }

        if ($self['parent_id'] && empty($data['parent_id'])) {
            // 親から外れた時
            $parent = [];
            $parent['lft'] = $parent['rgt'] = $this->find('one', array('fields' => 'MAX(rgt)', 'where' => $where, 'params' => $params, 'options' => $options)) + 1;
        } else {
            // 変更先の親を取得
            $parent_params = array_merge(array($data['parent_id']), $params);
            $parent = $this->find('row', array('where' => $idWhere, 'params' => $parent_params, 'options' => $options));
            if (!$parent) {
                return false;
            }
        }

        // 変更先の親が自身や自分の子供の場合はエラー
        if ($self['lft'] <= $parent['lft'] && $parent['rgt'] <= $self['rgt']) {
            return false;
        }

        // ノードの変更位置計算
        $self_lft = $self['lft'];
        $self_rgt = $self['rgt'];
        $parent_rgt = $parent['rgt'];
        $space = $self_rgt - $self_lft + 1;

        $table = $this->getTableName();
        $where = $where ? $where . ' AND ' : '';
        if ($self_rgt > $parent_rgt) {
            // 自身を左へ移動
            $move = $parent_rgt - $self_lft;
            /** @noinspection SqlResolve */
            $sql = <<<SQL
            UPDATE $table SET
            lft = CASE
                WHEN lft > $parent_rgt AND lft < $self_lft
                    THEN lft + $space
                WHEN lft >= $self_lft AND lft < $self_rgt
                    THEN lft + $move
                ELSE lft END,
            rgt = CASE
                WHEN rgt >= $parent_rgt AND rgt < $self_lft
                    THEN rgt + $space
                WHEN rgt > $self_lft AND rgt <= $self_rgt
                    THEN rgt + $move
                ELSE rgt END
            WHERE $where
                rgt >= $parent_rgt AND lft < $self_rgt
            SQL;
        } else {
            // 自身を右へ移動
            $move = $parent_rgt - $self_rgt - 1;
            /** @noinspection SqlResolve */
            $sql = <<<SQL
            UPDATE $table SET
            lft = CASE 
                WHEN lft > $self_rgt AND lft < $parent_rgt
                    THEN lft - $space
                WHEN lft >= $self_lft AND lft < $self_rgt
                    THEN lft + $move
                ELSE lft END,
            rgt = CASE
                WHEN rgt > $self_rgt AND rgt < $parent_rgt
                    THEN rgt - $space
                WHEN rgt > $self_lft AND rgt <= $self_rgt
                    THEN rgt + $move
                ELSE rgt END
            WHERE $where
            rgt > $self_lft AND lft < $parent_rgt
            SQL;
        }

        // 親の位置変更処理
        if (!$this->executeSql($sql, $params, $options)) {
            return false;
        }

        // 自身の更新処理
        return $this->update($data, $idWhere, $self_params, $options);
    }

    /**
     * 階層構造のノード削除
     * @param $id
     * @param string $where
     * @param array $params
     * @param array $options
     * @return array|false|int
     */
    public function deleteNodeById($id, string $where = '', array $params = [], array $options = [])
    {
        // 自身取得
        $idWhere = $where ? 'id=? AND ' . $where : 'id=?';
        $self_params = array_merge(array($id), $params);
        $self = $this->find('row', array('where' => $idWhere, 'params' => $self_params, 'options' => $options));

        if (!$self) {
            return false;
        }

        $self_lft = $self['lft'];
        $self_rgt = $self['rgt'];
        $space = $self_rgt - $self_lft + 1;

        $table = $this->getTableName();
        $where = $where ? $where . ' AND ' : '';

        // 削除処理
        $sql = 'DELETE FROM ' . $table . ' WHERE ' . $where . ' lft >= ' . $self_lft . ' AND rgt <= ' . $self_rgt;
        if (!$this->executeSql($sql, $params, $options)) {
            return false;
        }

        // 詰める処理
        /** @noinspection SqlResolve */
        /** @noinspection SqlCaseVsIf */
        $sql = <<<SQL
        UPDATE $table SET
            lft = CASE
                  WHEN lft > $self_rgt
                      THEN lft - $space
                  ELSE lft END,
            rgt = CASE
                WHEN rgt > $self_rgt
                    THEN rgt - $space
                ELSE rgt END
        WHERE
            $where
            rgt > $self_rgt
        SQL;
        return $this->executeSql($sql, $params, $options);
    }
}