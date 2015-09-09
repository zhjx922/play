<?php
/**
 * 牛逼呆逼
 * 绝对原(chao)创(xi),前无古人，后无来者
 *
 * 索引下图分为两部分(前半部分为hashTable)，后半部分为索引块儿
 * ================================================================================
 * =索引块偏移量==索引块偏移量=0000(空)===nextLink|keyLength|key|dataOffset|dataLength=
 * ================================================================================
 * @date 2015-01-24
 * @author zhjx922
 */

class NbDb
{
    const LINK_SIZE = 4; //链表单块儿大小
    const INDEX_FILE_SIZE = 1048576; //索引文件大小默认1MB(1048576)

    const INSERT_SUCCESS = 1; //插入成功
    const INSERT_LINK_SUCCESS = 2; //插入成功(冲突)
    const FIND_SUCCESS = 3; //查询成功
    const DELETE_SUCCESS = 4; //删除成功
    const KEY_EXISTS = 5; //key存在

    private $indexname; //索引文件名称
    private $dbname; //数据文件名称
    private $fd = array(); //fd 数组
    private $_index_size = 0; //当前索引文件大小
    private $_db_size = 0; //当前数据文件大小

    public function __construct($name = 'db')
    {
        $this->indexname = "{$name}.nbindex";
        $this->dbname = "{$name}.nbdb";
        $this->_open(); //打开数据库
    }

    public function __destruct()
    {
        fclose($this->fd['index']);
        fclose($this->fd['db']);
    }

    /**
     * 打开并且初始化数据库
     */
    private function _open()
    {
        $mode = file_exists($this->indexname) ? 'r+b' : 'w+b';

        $this->fd['index'] = fopen($this->indexname, $mode);
        $this->fd['db'] = fopen($this->dbname, $mode);

        if(0 == fstat($this->fd['index'])['size']) //初始化hashtable索引结构
            fwrite($this->fd['index'], str_pad('', self::INDEX_FILE_SIZE, pack('L', 0)), self::INDEX_FILE_SIZE);
        $this->_reloadSize();
    }

    private function _reloadSize()
    {
        $this->_index_size = (int)fstat($this->fd['index'])['size'];
        $this->_db_size = (int)fstat($this->fd['db'])['size'];
    }

    /**
     * 神奇的TIMES33(计算结果必须是有效的偏移量)
     * @param $key
     */
    private function _hash($key)
    {
        $strlen = 8; //数字越大，hash效果越好，速度越慢。。。自我衡量。。。
        $string = substr(md5($key), 0, $strlen);
        $hash = 0;
        for($i=0;$i<$strlen;$i++)
        {
            $hash += 33 * $hash + ord($string[$i]);
            //$hash += ($hash << 5) + ord($string[$i]); //hash分布效果差不多
        }

        return ($hash % (self::INDEX_FILE_SIZE / self::LINK_SIZE)) * self::LINK_SIZE;
    }

    /**
     * 写入数据
     */
    private function _writeData($index_offset, $block, $value)
    {
        fseek($this->fd['index'], $index_offset);
        fwrite($this->fd['index'], pack('L', $this->_index_size), self::LINK_SIZE); //将文件末尾的偏移量写入hashTable

        fseek($this->fd['index'], 0, SEEK_END); //移动到文件尾部
        fwrite($this->fd['index'], $block, strlen($block)); //将索引块儿写入

        fseek($this->fd['db'], 0, SEEK_END); //移到文件尾部
        fwrite($this->fd['db'], $value, strlen($value));
    }

    /**
     * 王琛超越不了的insert
     * @param $key
     * @param $value
     * @return bool
     */
    public function insert($key, $value)
    {
        $this->_reloadSize();
        $offset = $this->_hash($key); //通过key计算偏移量
        //$offset = 249168; //假设冲突,测试用

        //构建单个索引块儿
        $block = pack('L', 0); //nextLink
        $block .= pack('L', strlen($key)); //key长度
        $block .= $key; //key
        $block .= pack('L', $this->_db_size); //value偏移量
        $block .= pack('L', strlen($value)); //value长度


        //文件指针移动到hash后的位置
        fseek($this->fd['index'], $offset);
        $link = unpack('L', fread($this->fd['index'], self::LINK_SIZE))[1]; //解包，查看hashTable中是否包含记录

        if(0 == $link) //哦yeah，没有内容，强势插入
        {
            $this->_writeData($offset, $block, $value);
            return self::INSERT_SUCCESS;
        }


        $prev_link = 0;
        while($link) //妈蛋，被占用了，只能往后面放了
        {
            fseek($this->fd['index'], $link);
            $data = fread($this->fd['index'], self::LINK_SIZE * 2);

            $block_key_length = unpack('L',substr($data, self::LINK_SIZE))[1]; //获取当前block的key长度

            $block_key = fread($this->fd['index'], $block_key_length);

            if(0 == strncmp($key, $block_key, strlen($key))) //哇咔咔，劳资(key)已经存在了
            {
                return self::KEY_EXISTS;
            }

            $prev_link = $link; //记录上条记录
            $link = unpack('L', substr($data, 0, self::LINK_SIZE))[1]; //查询是否包含nextLink
        }

        //echo "Key:{$key} hash冲突，在新的位置插入<br/>";
        $this->_writeData($prev_link, $block, $value);
        return self::INSERT_LINK_SUCCESS;
    }

    /**
     * 飞快的find
     * @param $key
     */
    public function find($key)
    {
        $this->_reloadSize();
        $offset = $this->_hash($key); //通过key计算偏移量
        fseek($this->fd['index'], $offset);
        $link = unpack('L', fread($this->fd['index'], self::LINK_SIZE))[1];

        while($link) //hash后的key存在，找啊找
        {
            fseek($this->fd['index'], $link);
            $data = fread($this->fd['index'], self::LINK_SIZE * 2);

            $block_key_length = unpack('L',substr($data, self::LINK_SIZE))[1]; //获取当前block的key长度

            $block_key = fread($this->fd['index'], $block_key_length);

            if(0 == strncmp($key, $block_key, strlen($key))) //哇咔咔，找到了
            {
                $value_data = fread($this->fd['index'], self::LINK_SIZE * 2);
                $value_offset = unpack('L', substr($value_data, 0, self::LINK_SIZE))[1];
                $value_length = unpack('L', substr($value_data, self::LINK_SIZE))[1];
                break;
            }

            $link = unpack('L', substr($data, 0, self::LINK_SIZE))[1];
        }

        if(isset($value_offset))
        {
            fseek($this->fd['db'], $value_offset);
            return fread($this->fd['db'], $value_length);
        }

        echo "没找到啊<br/>";
        return false;
    }

    /**
     * 不负责的delete
     * @param $key
     * @return bool
     */
    public function delete($key)
    {
        $offset = $this->_hash($key);
        fseek($this->fd['index'], $offset);

        $link = unpack('L', fread($this->fd['index'], self::LINK_SIZE))[1];
        $prev_link = 0;

        $find = false;
        while($link)
        {
            fseek($this->fd['index'], $link);
            $data = fread($this->fd['index'], self::LINK_SIZE * 2);

            $next_link = unpack('L',substr($data, 0,self::LINK_SIZE))[1];

            $block_key_length = unpack('L',substr($data, self::LINK_SIZE))[1]; //获取当前block的key长度
            $block_key = fread($this->fd['index'], $block_key_length);
            if(0 == strncmp($key, $block_key, strlen($key))) //哇咔咔，找到了
            {
                $find = true;
                break;
            }
            $prev_link = $link; //如果key不符合,当前link标记为prev
            $link = $next_link;
        }

        if(!$find)
            return false;

        //@todo 删除data数据文件也不能回收利用，不删了。。。
        if(0 == $prev_link)
        {
            fseek($this->fd['index'], $offset);
            fwrite($this->fd['index'], pack('L', $next_link), self::LINK_SIZE);
        }else{
            fseek($this->fd['index'], $prev_link);
            fwrite($this->fd['index'], pack('L', $next_link), self::LINK_SIZE);
        }

        return true;

    }
}

//一个计算时间的小函数
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

$key = '老毛子';
$NbDb = new NbDb('a');

/*
//插入
var_dump($NbDb->insert('key', 'value'));

//查找
var_dump($NbDb->find('key'));

//删除
var_dump($NbDb->delete('key'));
exit;
*/
set_time_limit(0);
$time_start = microtime_float();

//批量插入测试
$conflict_count = 0;
$count = 100000;
for($i=0;$i<$count;$i++)
{
    $result = $NbDb->insert('key' . $i, 'value' . $i);

    $result == NbDb::INSERT_LINK_SUCCESS && $conflict_count++;
    //$NbDb->find('key' . $i);
}

$time_end = microtime_float();
$time = $time_end - $time_start;
echo "<br/>运行次数:{$count}<br/>";
echo "<br/>冲突次数:{$conflict_count}<br/>";
echo "<br/>运行时间:{$time} seconds<br/>";