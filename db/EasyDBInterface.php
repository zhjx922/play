<?php
/**
 * DB Interface
 */
interface EasyDBInterface {
    //创建数据表
    public function createTable($table, $field);

    //查看数据表
    public function showTables();

    //插入数据
    public function insert($table, $values);

    //更新数据
    public function update($table, $values, $where = []);

    //删除数据
    public function delete($table, $where = []);

    //查询数据表
    public function select($table, $field = '*', $where = []);

    //新增索引
    public function addIndex($table, $field);

    //删除索引
    public function deleteIndex($table, $field);
}