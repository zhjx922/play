<?php
/**
 * 超简单数据库实现(B+TREE)
 * 希望：支持数字+字符串索引
 *
 * 头部8K=4K(头信息)+4K(表结构信息+索引)
 *
 * @author zhjx922
 */

//require_once('Base.php');

define('VERSION',               '0.0.1'); //版本号
define('VERSION_LENGTH',        0x0010); //版本号长度

define('BLOCK_SIZE',            0x2000); //单个块儿大小
define('BLOCK_INIT_COUNT',      0x0005); //初始化块儿个数

define('BLOCK_NODE_ROOT',       0x0001); //根结点块
define('BLOCK_NODE_INTERNAL',   0x0002); //内部结点块
define('BLOCK_NODE_LEAF',       0x0003); //叶子结点块

define('FIELD_NAME_LENGTH',     0x0010); //字段名最大长度
define('FIELD_INT',             0x0001); //字段类型INT
define('FIELD_CHAR',            0x0002); //字段类型CHAR
define('FIELD_VARCHAR',         0x0003); //字段类型VARCHAR
define('FIELD_SIZE',            0x0014); //字段占空间大小

//define('KEY_LENGTH', 4);
//define('KEY_MAX_SIZE', intval((BLOCK - KEY_LENGTH) / (KEY_LENGTH * 2)));

/**
 * DB Interface
 */
interface EasyDBInterface {
    //创建数据表
    public function createTable($table, $field);
    //查询数据表
    public function select($table, $field = '*', $where = []);
}

/**
 * DB核心类
 */
abstract class EasyDBCore implements EasyDBInterface {
    public static $fd = array();
    private $_data_path = './EasyData/'; //DB路径
    private $_db; //数据库名称

    /**
     * 初始化数据库
     * @param string $db
     */
    final public function __construct($db) {
        $this->_db = strval($db);
        $this->_initDB();

        $this->init();
    }

    protected function init() {

    }

    /**
     * 初始化数据库
     */
    private function _initDB() {
        $pathname = $this->_data_path . $this->_db;
        if(!is_dir($pathname)) {
            if(!mkdir($pathname, 0755, true)) {
                $this->error('数据库文件夹创建失败');
            } else {
                $this->log('数据库文件夹创建成功');
            }
        } else {
            $this->log('数据库文件夹存在');
        }
    }

    /**
     * 获取数据表路径
     * @param string$table
     * @return string
     */
    protected function getTablePath($table) {
        return $this->_data_path . $this->_db . '/' . $table . '.edb';
    }

    /**
     * 创建数据表文件
     * @param string $table
     * @return bool
     */
    protected function createTableFile($table) {
        //生成文件并记录句柄
        self::$fd[$table] = fopen($this->getTablePath($table), 'w+b');

        if(!self::$fd[$table]) return false;

        //拼接头信息(4K)
        $header_block = pack('a' . VERSION_LENGTH, 'EasyDB ' . VERSION);
        $header_block = pack('a' . BLOCK_SIZE / 2, $header_block);

        //var_dump($header_block);

        //拼接表信息(4K)

        //拼接字段信息
        $fileds = [
            'id int 11',
            'name varchar 255',
            'sex char 3'
        ];

        $filed_block = '';

        foreach($fileds as $filed) {
            $filed = explode(' ', $filed);

            $type = FIELD_VARCHAR;
            switch($filed[1]) {
                case 'int':
                    $type = FIELD_INT;
                    break;
                case 'char':
                    $type = FIELD_CHAR;
                    break;
                case 'varchar':
                    $type = FIELD_VARCHAR;
                    break;
            }

            $filed_block .= pack('a' . FIELD_NAME_LENGTH . 'S1S1', $filed[0], $type, $filed[2]);
        }

        $header_block .= pack('a' . BLOCK_SIZE / 4, $filed_block);

        //$a = unpack('a' . FIELD_NAME_LENGTH . 'field/S1type/S1length', $a);
        //echo var_dump(trim($a['field']) == 'id');exit;

        //拼接索引信息
        //var_dump($header_block);exit;
        $header_block .= pack('a' . BLOCK_SIZE / 4, $filed_block);


        fwrite(self::$fd[$table], $header_block, BLOCK_SIZE);

        return (bool)self::$fd[$table];
    }

    //protected function makeTable

    /**
     * 设置当前使用表
     * @param string $table
     */
    protected function useTable($table) {
        if(!isset(self::$fd[$table])) {
            self::$fd[$table] = fopen($this->getTablePath($table), 'r+b');
        }
    }

    /**
     * 日志打印
     * @param string $message
     */
    public function log($message) {
        echo "===" . $message . "===\n";
    }

    /**
     * 错误信息
     * @param string $message
     */
    public function error($message) {
        $this->log($message);
        exit;
    }
}

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
        $this->createTableFile($table);
        $this->log('结束创建数据表');
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
}

//连接数据库zhjx922
$db = new EasyDB('zhjx922');
$db->createTable('test', [
    'id int 11',
    'name varchar 255',
    'sex char 3'
]);
$db->select('test');

class EasyDB1 {
    private $_path = './EasyDB/'; //DB路径
    private $_db; //DB名称
    private $_table; //表名
    private $_fd; //表对应的句柄
    private $_index_size = 0; //索引文件大小
    private $_data_size = 0; //数据文件大小

    /**
     * 初始化参数
     * @param string $db
     */
    public function __construct($db) {
        $this->_db = $db;
    }

    /**
     * 切换数据库
     * @param $db
     * @return $this
     */
    public function switchDb($db) {
        $this->_db = $db;
        return $this;
    }

    /**
     * 创建一个数据库
     * @return boolean
     */
    public function createDb() {
        //数据库路径
        $pathname = $this->getDbPath();
        if(!is_dir($pathname)) {
            if(!mkdir($pathname, 0755, true)) {
                Base::exception("创建数据库文件夹失败！");
            }
        }
        return true;
    }

    /**
     * 查看数据库列表
     * @return array
     */
    public function showDb() {
        $db_list = [];
        $iterator = new DirectoryIterator($this->_path);
        foreach($iterator as $file) {
            if($file->isDot()) continue; //..跳过
            if(!$file->isDir()) continue; //不是目录跳过
            $db_list[] = $file->getFilename();
        }
        return $db_list;
    }

    /**
     * 获取数据库路径
     * @return string
     */
    public function getDbPath() {
        return $this->_path . $this->_db . '/';
    }

    public function getTablePath($fix) {
        $this->getDbPath() . '' . $fix;
    }

    /**
     * 打开表文件句柄
     */
    private function _openFile() {
        $field_file = $this->getDbPath() . "{$this->_table}.field";
        $index_file = $this->getDbPath() . "{$this->_table}.index";
        $data_file = $this->getDbPath() . "{$this->_table}.data";
        $mode = is_file($index_file) ? 'r+b' : 'w+b';
        $this->_fd[$this->_table]['field'] = fopen($field_file, $mode);
        $this->_fd[$this->_table]['index'] = fopen($index_file, $mode);
        $this->_fd[$this->_table]['data'] = fopen($data_file, $mode);
    }

    /**
     * 文件大小
     */
    private function _reloadFileSize() {
        $this->_index_size = fstat($this->_fd[$this->_table]['index'])['size'];
        $this->_data_size = fstat($this->_fd[$this->_table]['data'])['size'];
    }

    /**
     * 创建一个数据表
     * @param string $table_name
     * @param array $field
     * @return boolean
     */
    public function createTable($table_name, $field) {
        $this->_table = $table_name;
        $this->_openFile();
        $this->_initIndex();
        $this->_initField($field);
        return true;
    }

    /**
     * 初始化索引
     */
    private function _initIndex() {
        //初始化根结点指针
        fwrite($this->_fd[$this->_table]['index'], pack('L', 0), KEY_LENGTH);

        //初始化当前最大PK(主键ID)
        fwrite($this->_fd[$this->_table]['index'], pack('L', 0), KEY_LENGTH);

        //初始化一个储存块
        $block = str_repeat(pack('L', 0), KEY_MAX_SIZE * 2 + 1);
        fwrite($this->_fd[$this->_table]['index'], $block, strlen($block));
    }

    /**
     * 初始化字段
     * @param array $field
     */
    private function _initField($field) {
        $fields = serialize($field);
        fwrite($this->_fd[$this->_table]['field'], $fields, strlen($fields));
    }

    /**
     * 获取表字段
     * @return mixed
     */
    private function _getField() {
        return unserialize(stream_get_contents($this->_fd[$this->_table]['field']));
    }

    /**
     * 数据插入
     * $values = [
     *      'name'=>'zhjx922',
     *      'sex'=>'man',
     * ];
     * @param string $table_name
     * @param array $values
     */
    public function insert($table_name, $values) {
        $this->_table = $table_name;
        $this->_openFile();
        $this->_lock();
        $this->_reloadFileSize();
        $this->_insertData($values);
        $this->_insertIndex();
        $this->_reloadFileSize();
        $this->_unlock();
    }

    /**
     * 获取最大ID
     */
    private function _getMaxId() {
        fseek($this->_fd[$this->_table]['index'], KEY_LENGTH);
        $max_id = fread($this->_fd[$this->_table]['index'], KEY_LENGTH);
        return unpack('L', $max_id)[1];
    }

    /**
     * 插入数据
     * @param $values
     */
    private function _insertData($values) {
        $this->_packData($values);
    }

    private function _insertIndex() {

    }

    /**
     * 打包数据
     * @param $values
     */
    private function _packData($values) {
        $id = $this->_getMaxId() + 1;
        $fields = $this->_getField();

        $data = "";
        foreach($fields as $field=>$length) {
            if(isset($values[$field])) {
                $str = str_pad($values[$field], $length, "\0", STR_PAD_RIGHT);
            }
        }
        //fseek($this->_fd[$this->_table]['index'], 0, SEEK_END);
        //echo ftell($this->_fd[$this->_table]['index']);
    }

    /**
     * 加锁(尝试3次)
     */
    private function _lock() {
        $index_times = 1;
        while( $index_times++ < 3 && (!flock($this->_fd[$this->_table]['index'], LOCK_EX)) ) {
            usleep(100000);
        }

        if(3 == $index_times) {
            exit('锁不上，挂了算了。。。');
        }

        $data_times = 1;
        while( $data_times++ < 3 && (!flock($this->_fd[$this->_table]['data'], LOCK_EX)) ) {
            usleep(100000);
        }

        if(3 == $data_times) {
            exit('锁不上，挂了算了。。。');
        }
    }

    /**
     * 释放锁
     */
    private function _unlock() {
        flock($this->_fd[$this->_table]['index'], LOCK_UN);
        flock($this->_fd[$this->_table]['data'], LOCK_UN);
    }

}



//$db = new EasyDB('zhjx922');
//$db->createDb();
//var_dump($db->showDb());
//$db->createTable('test', ['name'=>3*4, 'title'=>3*20]);
//$db->insert('test', ['name'=>'zhjx922', 'title'=>'PHP开发工程师']);