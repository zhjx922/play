<?php
/**
 * DB Core
 */
abstract class EasyDBCore implements EasyDBInterface {
    public static $fd = array();
    public static $header = array();
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
     * 获取数据库路径
     * @return string
     */
    protected function getDbPath() {
        return $this->_data_path . $this->_db . '/';
    }

    /**
     * 获取数据表路径
     * @param string $table
     * @return string
     */
    protected function getTablePath($table) {
        return $this->getDbPath() . $table . '.edb';
    }

    /**
     * 初始化数据表文件
     * @param string $table
     * @param array $field
     */
    protected function initTableFile($table, $field) {
        $this->createTableFile($table);
        $this->fillTableFile($table, $field);
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

        return (bool)self::$fd[$table];
    }

    /**
     * 填充表文件
     * @param string $table
     * @param array $fields
     */
    protected function fillTableFile($table, $fields) {
        //拼接头信息(4K)

        //16字节(版本信息)
        $header_block = pack('a' . VERSION_LENGTH, 'EasyDB ' . VERSION);

        //4字节(主键自增ID)
        $header_block .= pack('L', 0x0000);

        $header_block = pack('a' . BLOCK_SIZE / 2, $header_block);

        //拼接表信息(4K)
        //拼接字段信息(2K，最多支持100个字段，好屌。。。)
        $filed_block = '';

        foreach($fields as $filed) {
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

        //解包逻辑：判断行头是否为\0
        //解包方法：unpack('a' . FIELD_NAME_LENGTH. 'field/S1type/S1length', $filed_block);
        $header_block .= pack('a' . BLOCK_SIZE / 4, $filed_block);

        //拼接索引信息
        $index_block = '';
        $header_block .= pack('a' . BLOCK_SIZE / 4, $index_block);

        fwrite(self::$fd[$table], $header_block, BLOCK_SIZE);
    }

    /**
     * 设置当前使用表
     * @todo 获取索引
     * @param string $table
     */
    protected function useTable($table) {
        //保存数据表文件句柄
        if(!isset(self::$fd[$table])) {
            self::$fd[$table] = fopen($this->getTablePath($table), 'r+b');
        }

        //保存头部数据到内存中
        if(!isset(self::$header[$table])) {
            //移动指针到文件头部
            fseek(self::$fd[$table], 0);

            //读取header全部信息
            $header = fread(self::$fd[$table], BLOCK_SIZE);

            //截取版本
            self::$header[$table]['version'] = rtrim(unpack('a*', substr($header, 0, VERSION_LENGTH))[1], "\0");

            //截取最大ID
            self::$header[$table]['max_id'] = (int)rtrim(unpack('L', substr($header, VERSION_LENGTH, MAX_ID_LENGTH))[1], "\0");

            //获取表结构
            $fileds = substr($header, BLOCK_SIZE / 2, BLOCK_SIZE / 4);
            for($i = 0; $i < floor(BLOCK_SIZE / 4 / FIELD_SIZE); $i++) {
                $field = substr($fileds, $i * FIELD_SIZE, FIELD_SIZE);
                if($field[0] == "\0") break;
                $f = unpack('a' . FIELD_NAME_LENGTH. 'field/S1type/S1length', $field);
                $f['field'] = rtrim($f['field'], "\0");
                self::$header[$table]['field'][$f['field']] = $f;
            }

            //获取索引
            //var_dump(self::$header[$table]);exit;

        }
    }

    /**
     * 获取当前主键最大ID
     */
    protected function getMaxId() {

    }

    /**
     * 插入数据
     * @param string $table
     * @param array $values
     */
    protected function insertValues($table, $values) {

    }

    /**
     * 自动过滤右侧的\0
     * @param $format
     * @param $data
     * @return mixed
     */
    protected function unpack($format, $data) {
        return rtrim(unpack($format, $data)[1], "\0");
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