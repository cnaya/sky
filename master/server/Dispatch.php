<?php
namespace Sky;


class Dispatch
{
    protected $_service = array(
        'node',//节点控制服务
        'str',//文本服务
        'file',//文件服务
        'sub', //订阅服务
        'heart', //node心跳
        'cmd', //指令服务
    );
    public $service = array();

    public $worker_id;
    public function __construct($sky)
    {
        $this->sky = $sky;
    }

    public function onStart(\swoole_server $server, $worker_id)
    {
        global $argv;
        \Swoole\Console::setProcessName("$argv[0] [master server] : worker");
        $this->worker_id = $worker_id;
        if ($this->worker_id == 0)
        {
            $this->sky->log("set timer {$this->sky->serverSetting['user_heartbeat_check_interval']}ms on worker {$this->worker_id} ");
            $server->addtimer($this->sky->serverSetting['user_heartbeat_check_interval']);
        }
    }

    /*
     * worker 被节点连接保存节点信息
     */
    public function onConnect($server, $fd, $from_id)
    {
        if (!isset($this->service['node']) || empty($this->service['node']))
        {
            $this->service['node'] = new \Sky\Service\Node($this->sky);
        }
        $params['service'] = 'node';
        $params['cmd'] = 'addnode';
        $this->service['node']->handler($server, $fd, $from_id, $params);
    }

    /*
     * $data 协议解析
     * ascii 明文
     */
    public function onReceive($server, $fd, $from_id, $data)
    {
        $this->parse($server, $fd, $from_id, $data);
    }

    public function onClose($server, $fd, $from_id)
    {
        if (!isset($this->service['node']) || empty($this->service['node']))
        {
            $this->service['node'] = new \Sky\Service\Node($this->sky);
        }
        $params['service'] = 'node';
        $params['cmd'] = 'delnode';
        $this->service['node']->handler($server, $fd, $from_id, $params);
    }

    public function onShutdown()
    {

    }

    public function onTimer(\swoole_server $server, $interval)
    {
        if (!empty($this->sky->nodes))
        {
            $fds = $server->heartbeat(false);//不要扩展关闭链接
            if ($fds)
            {
                foreach ($fds as $fd)
                {
                    $info  = $server->connection_info($fd);
                    if (in_array($info['remote_ip'],$this->sky->white_list))
                    {
                        continue;
                    }
                    $this->sky->log("on timer close fd");
                    $server->close($fd);//关闭fd
                }
            }
        }

    }

    protected function parse($server, $fd, $from_id, $params)
    {
        $res = $this->getOpt($params);
        if ($res === 1)// "\r\n" 跳出
        {
            return;
        }
        if ($res)
        {
            foreach ($res as $re)
            {
                $service = strtolower($re['service']);
                if (in_array($service,$this->_service))
                {
                    if (!isset($this->service[$service]) || empty($this->service[$service]))
                    {
                        $_name = "\\Sky\\Service\\".ucfirst(strtolower($service));
                        $this->service[$service] = new $_name($this->sky);
                    }
                    $this->service[$service]->onReceive($server, $fd, $from_id,$re);
                }
                else
                {
                    $this->sky->res->error($fd,9001);
                }
            }
        }
        else
        {
            $this->sky->res->error($fd,9001);
        }
    }

    public function getOpt($params)
    {
        if ($params === "\r\n")
        {
            return 1;
        }
        $tmp = explode("\r\n", $params);
        $return = array();
        foreach ($tmp as $key => $value)
        {
            if (empty($value))
                continue;
            $cmd_line = explode(' ', $value, 3);
            $return[$key]['service'] = $cmd_line[0];
            $return[$key]['cmd'] = $cmd_line[1];
            $return[$key]['params'] = array();
            if (isset($cmd_line[2]) and !empty($cmd_line[2]))
            {
                $tmp = explode('-',trim($cmd_line[2]));
                $_data = array();
                foreach ($tmp as $v)
                {
                    if (!empty($v))
                    {
                        $tmp = explode(' ',$v,2);
                        if (!empty($tmp[0]) and !empty($tmp[1]))
                            $_data[trim($tmp[0])] = trim($tmp[1]);
                    }
                }
                $return[$key]['params'] = $_data;
            }
        }
        return $return;
    }
}