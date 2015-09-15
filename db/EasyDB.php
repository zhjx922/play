<?php
/**
 * 超简单数据库实现(B+TREE)
 * 希望：支持数字+字符串索引
 *
 * 头部8K=4K(头信息)+4K(表结构信息+索引)
 *
 * @author zhjx922
 */

include('EasyDBDefine.php');
include('EasyDBInterface.php');
include('EasyDBCore.php');


/**
 * 主要用于暴露对外的接口
 */
class EasyDB extends EasyDBCore {

    protected function init() {

    }

    /**
     * 创建数据表文件
     * @param string $table
     * @param array $field
     * @return bool
     */
    public function createTable($table, $field) {
        $this->log('开始创建数据表');
        $this->initTableFile($table, $field);
        $this->log('结束创建数据表');
    }

    /**
     * 查看数据表列表
     * @return array
     */
    public function showTables() {
        $db_list = [];
        $iterator = new DirectoryIterator($this->getDbPath());
        foreach($iterator as $file) {
            if($file->isDot()) continue; //..跳过
            if($file->isDir()) continue; //是目录跳过
            $db_list[] = pathinfo($file->getFilename())['filename'];
        }
        return $db_list;
    }

    /**
     * 插入数据
     * @param string $table
     * @param array $values ('name'=>'zhjx922', 'sex'=>'man')
     */
    public function insert($table, $values) {
        $this->useTable($table);
        $this->insertValues($table, $values);
    }

    //更新数据
    public function update($table, $values, $where = []) {

    }

    //删除数据
    public function delete($table, $where = []) {

    }

    /**
     * 查询数据
     * @param string $table
     * @param string $field
     * @param array $where
     */
    public function select($table, $field = '*', $where = []) {
        $this->useTable($table);
    }

    /**
     * 为某字段添加索引
     * @param $table
     * @param $field
     */
    public function addIndex($table, $field) {

    }

    //删除索引
    public function deleteIndex($table, $field) {

    }
}

//连接数据库zhjx922
$db = new EasyDB('zhjx922');

//$db->createTable('test', ['id int 11', 'name varchar 255', 'sex char 3']);

//$db->select('test');
//var_dump($db->showTables());
$db->insert('test', ['name'=>'zhjx922', 'sex'=>'man']);
