<?php
/**
 * Server模型实现(演示用)
 * 不考虑异常性能，不考虑乱七八糟的。。。
 * @author zhjx922
 */

$httpServer = new httpServer();
//$httpServer->run('single'); //single
//$httpServer->run('fork'); //fork
//$httpServer->run('prefork'); //prefork
$httpServer->run('event'); //event+prefork

class httpServer
{
    private $_socket; //全局socket
    private $_protocol = 'tcp'; //协议
    private $_address = '0.0.0.0'; //监听IP
    private $_port = 80; //监听端口
    private $_buffer = array(); //Buffer
    private $_root = '/Users/zhjx922/www'; //根目录
    private $_connect = array();
    public static $event = null;

    public function __construct($attr = array())
    {
        date_default_timezone_set("PRC");
    }

    /**
     * 初始化
     */
    public function init()
    {
        $context_option['socket']['backlog'] = 1024;
        $context = stream_context_create($context_option);
        $this->_socket = stream_socket_server(
            "{$this->_protocol}://{$this->_address}:{$this->_port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );
        if(!$this->_socket)
        {
            var_dump($errstr);
            exit();
        }
    }

    /**
     * 单进程模型
     */
    public function runSingle()
    {
        while($connect = stream_socket_accept($this->_socket, -1))
        {
            $conn = (int)$connect;

            do{
                $buffer = fread($connect, 8192);

                if(!isset($this->_buffer[$conn]))
                    $this->_buffer[$conn] = '';

                $this->_buffer[$conn] .= $buffer;

                //数据不全，继续
                $pack_length = httpProtocol::input($this->_buffer[$conn]);
                if(0 === $pack_length)
                    continue;

                break;
            }while(1);

            $this->exec(httpProtocol::decode($this->_buffer[$conn]), $connect);


            echo "end\n";
            unset($this->_buffer[$conn]);
            fclose($connect);
            //sleep(10);
        }
    }

    /**
     * fork模型
     */
    public function runFork()
    {
        while($connect = stream_socket_accept($this->_socket, -1))
        {
            $conn = (int)$connect;
            $pid = pcntl_fork();

            if($pid === 0)
            {
                //子进程处理
                do{
                    $buffer = fread($connect, 8192);

                    if(!isset($this->_buffer[$conn]))
                        $this->_buffer[$conn] = '';

                    $this->_buffer[$conn] .= $buffer;

                    //数据不全，继续
                    if(0 === httpProtocol::input($this->_buffer[$conn]))
                        continue;

                    break;
                }while(1);

                $this->exec(httpProtocol::decode($this->_buffer[$conn]), $connect);

                //echo "end\n";
                unset($this->_buffer[$conn]);
                fclose($connect);
                //sleep(10);
                posix_kill(getmypid(), SIGTERM);
                exit; //处理完毕关闭
            }else if($pid == -1){
                fclose($connect);
                die('不能Fork');
            }else if($pid){
                //pcntl_wait($status);
                fclose($connect); //关掉父进程中的连接，否则不会中断
                echo "pid:{$pid}\n";
            }

        }

    }

    /**
     * preFork模型
     */
    public function runPrefork()
    {
        $pids = array();
        $fork_num = 4; //启动4个进程

        while($fork_num--)
        {
            $pid = pcntl_fork(); //fork

            if($pid === 0)
            {
                while($connect = stream_socket_accept($this->_socket, -1))
                {

                    $conn = (int)$connect;

                    do {
                        $buffer = fread($connect, 8192);

                        if (!isset($this->_buffer[$conn]))
                            $this->_buffer[$conn] = '';

                        $this->_buffer[$conn] .= $buffer;

                        //数据不全，继续
                        if (0 === httpProtocol::input($this->_buffer[$conn]))
                            continue;

                        break;
                    } while (1);

                    $this->exec(httpProtocol::decode($this->_buffer[$conn]), $connect);


                    //echo "end\n";
                    unset($this->_buffer[$conn]);
                    fclose($connect);
                }
                //sleep(10);
                //usleep(1000);

            }else if($pid == -1){
                die('could not fork');
            }else{
                $pids[$pid] = $pid;
            }
        }

        echo "当前子进程ID:\n";
        echo implode("\n", $pids);
        echo "\n";

        while(1)
        {
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED); //等待子进程结束
            if($pid>0)
            {
                unset($pids[$pid]);
                echo "子进程:{$pid}，挂鸟。。\n";
            }
            if(0 == count($pids)) exit("进程死完了!\n");
        }

    }

    /**
     * event+preFork模型
     */
    public function runEvent()
    {
        $pids = array();
        stream_set_blocking($this->_socket, 0); //设置为非阻塞

        $fork_num = 4; //启动4个进程

        while($fork_num--)
        {
            $pid = pcntl_fork(); //fork

            if($pid === 0)
            {
                if(!self::$event) {
                    self::$event = new Events();
                }

                //事件设置
                self::$event->add($this->_socket, array($this, 'accept'));
                self::$event->loop();

            }else if($pid == -1){
                die('could not fork');
            }else{
                $pids[$pid] = $pid;
            }
        }

        echo "当前子进程ID:\n";
        echo implode("\n", $pids);
        echo "\n";

        while(1)
        {
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED); //等待子进程结束
            if($pid>0)
            {
                unset($pids[$pid]);
                echo "子进程:{$pid}，挂鸟。。\n";
            }
            if(0 == count($pids)) exit("进程死完了!\n");
        }
    }

    /**
     * 接收一个新请求
     */
    public function accept($socket)
    {
        $connect = @stream_socket_accept($socket, 0);
        if(false === $connect) //惊群
            return;

        stream_set_blocking($connect, 0);

        $conn = (int)$connect;
        if(!isset($this->_buffer[$conn])) $this->_buffer[$conn] = '';

        $this->_connect[$conn] = $connect;

        self::$event->add($this->_connect[$conn], array($this, 'read'));
    }

    /**
     * 接收数据
     */
    public function read($connect)
    {
        $conn = (int)$connect;

        if(!is_resource($connect) || feof($connect))
        {
            self::$event->del($connect);
            fclose($this->_connect[$conn]);
            return;
        }

        while(1)
        {
            $buffer = fread($connect, 8192);

            if($buffer === '' || $buffer === false)
            {
                break;
            }
            $this->_buffer[$conn] .= $buffer;
        }

        if($this->_buffer[$conn])
        {
            $pack_length = httpProtocol::input($this->_buffer[$conn]);

            if($pack_length === 0)
                return;

            $this->exec(httpProtocol::decode($this->_buffer[$conn]), $connect);

            //echo "end\n";
            unset($this->_buffer[$conn]);
            fclose($this->_connect[$conn]);
            self::$event->del($connect);
        }


    }

    /**
     * 执行
     * @param $header
     * @param $connect
     */
    public function exec($header, $connect)
    {
        $header_404 = "HTTP/1.0 404 Zhao Bu Dao!\r\n";
        $header_200 = "HTTP/1.0 200 ARE YOU OK?\r\n";
        if('GET' == $header['method'])
        {
            $file = $header['uri'];
            $file = '/' == $file ? '/index.html' : $file;
            if(false !== strpos($file, '?'))
            {
                echo '不支持有参数';
            }else{
                $content = '';
                if(is_file($this->_root . $file))
                {
                    fwrite($connect, $header_200);
                    $content = file_get_contents($this->_root . $file);
                }else{
                    fwrite($connect, $header_404);
                }

                fwrite($connect, "Content-Type: text/html\r\n");
                fwrite($connect, "Server: zhaojingxian De Server 1.0\r\n");
                fwrite($connect, "Date: " . date('Y-m-d H:i:s') . "\r\n");
                fwrite($connect, "\r\n");
                fwrite($connect, $content . "<br/>当前服务器时间：" . date('Y-m-d H:i:s') . "<br/>");
                fwrite($connect, "微秒：" . microtime(true));
            }
        }else{
            echo '暂不支持其它Method';
        }
    }

    /**
     * 运行
     * @param string $mode 运行模式(single,fork,prefork,event)
     */
    public function run($mode = 'single')
    {
        $this->init();
        $method = "run" . ucfirst($mode);
        $this->{$method}();
    }
}

/**
 * Http协议解析
 */
class httpProtocol
{
    //从workerman里面偷的
    public static function input($buffer)
    {
        //未解析完毕
        if(false === strpos($buffer, "\r\n\r\n"))
            return 0;

        list($header, $body) = explode("\r\n\r\n", $buffer, 2);

        if(0 === strpos($buffer, "POST"))
        {
            // find Content-Length
            $match = array();
            if(preg_match("/\r\nContent-Length: ?(\d*)\r\n/", $header, $match))
            {
                $content_lenght = $match[1];
            }
            else
            {
                return 0;
            }
            if($content_lenght <= strlen($body))
            {
                return strlen($header)+4+$content_lenght;
            }
            return 0;
        }
        else
        {
            return strlen($header)+4;
        }
        return;
    }

    //解码请求头
    public static function decode($buffer)
    {
        list($http_header, $http_body) = explode("\r\n\r\n", $buffer, 2);
        $header_data = explode("\r\n", $http_header);

        list($request_method, $request_uri, $request_protocol) = explode(' ', $header_data[0]);

        return array(
            'method'    =>  $request_method,
            'uri'       =>  $request_uri,
            'protocol'  =>  $request_protocol
        );
    }
}

/**
 * event封装
 */
class Events
{
    private $_base;
    private $_events;

    public function __construct()
    {
        $this->_base = event_base_new();
    }

    /**
     * 添加事件
     * @param $fd
     * @param $func
     */
    public function add($fd, $func)
    {
        $fd_int = (int)$fd;
        $event = event_new();

        event_set($event, $fd, EV_READ | EV_PERSIST, $func, null);
        event_base_set($event, $this->_base);
        event_add($event);
        $this->_events[$fd_int] = $event;
    }

    public function del($fd)
    {
        $fd_int = (int)$fd;
        if(isset($this->_events[$fd_int]))
            event_del($this->_events[$fd_int]);
    }

    /**
     * 事件循环
     */
    public function loop()
    {
        event_base_loop($this->_base);
    }
}
