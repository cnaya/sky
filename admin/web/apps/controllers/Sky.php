<?php
namespace App\Controller;
use Swoole;

class Sky extends \App\LoginController
{
    public function home()
    {
        $config = $this->config['websocket'];
        $projects = table('project')->gets(array('order'=>'id asc'));

        $parmams['order'] = 'id desc';
        $nodes = table('node')->gets($parmams);
        $this->assign('nodes', $nodes);
        $this->assign('projects', $projects);
        $this->assign('config', $config);
        $this->assign('user', $_SESSION['userinfo']);
        $this->display();
    }

    public function getProjectFiles()
    {
        $return = array();
        if (empty($_POST))
        {
            $return['status'] = 400;
        }
        else
        {
            $project = $_POST['name'];
            $info = table('project')->get($project,'project_name')->get();
            $params['project_name'] =  $project;
            $release = table('version')->gets($params);
            $content = array();
            foreach ($release as $k => $filename)
            {
                if (!file_exists(WEBPATH.$filename['path']))
                {
                    continue;
                }
                $content[$k]['filename'] = basename($filename['path']);
                $content[$k]['version'] = $filename['version'];
            }
            $return['status'] = 200;
            $return['content'] = $content;
            $return['current_release'] = $info['current_release'];
        }
        echo json_encode($return);
    }

    public function getRelease($name)
    {
        $tmp = explode('_',$name);
        return $tmp[1];
    }

    public function getNodeInfo()
    {
        $ip = $_POST['ip'];
        $gets['ip'] = $ip;
        $gets['order'] = 'type desc';
        $res = table('app_install')->gets($gets);
        $return['status'] = 400;
        $user  = table('user')->getMap(array('order'=>'id desc'));

        $map = array(
            '1' => '动态服务',
            '2' => '静态服务',
            '3' => '自带服务',
        );
        if (!empty($res))
        {
            foreach ($res as $k => $re)
            {
                $install = $user[$re['last_install_by']];
                $res[$k]['last_install_name'] = !empty($install['realname'])?$install['realname']:$install['username'];
                $start = $user[$re['last_install_by']];
                $res[$k]['last_start_name'] = !empty($start['realname'])?$start['realname']:$start['username'];
                $res[$k]['type_name'] = $map[$re['type']];
            }
            $return['status'] = 200;
            $return['content'] = $res;
        }
        echo json_encode($return);
    }

    public function updateVersion()
    {
        $name = $_POST['name'];
        $ip = $_POST['ip'];
        $version = $_POST['version'];
        $return['status'] = 400;
        //\Swoole\Error::dbd();
        $now = date('Y-m-d H:i:s');
        if (!empty($name))
        {
            if (table('app_install')->exists(array('name'=>$name,'ip'=>$ip)))
            {
                $user  = table('user')->get($_SESSION['userinfo']['username'],'username')->get();
                $updates['version'] = $version;
                $updates['last_install_time'] = $now;
                $updates['last_start_by'] = $user['id'];
                $where = array('name'=>$name,'ip'=>$ip);
                $res = table('app_install')->sets($updates,$where);
                if ($res)
                {
                    $return['status'] = 200;
                    $return['last_install_time'] = $now;
                }
            }
        }
        echo json_encode($return);
    }

    public function updateStartTime()
    {
        $name = $_POST['name'];
        $ip = $_POST['ip'];
        $return['status'] = 400;
        $now = date('Y-m-d H:i:s');
        //\Swoole\Error::dbd();
        if (!empty($name))
        {
            if (table('app_install')->exists(array('name'=>$name,'ip'=>$ip)))
            {
                $user  = table('user')->get($_SESSION['userinfo']['username'],'username')->get();
                $updates['last_start_time'] = $now;
                $updates['last_start_by'] = $user['id'];
                $where = array('name'=>$name,'ip'=>$ip);
                $res = table('app_install')->sets($updates,$where);
                if ($res)
                {
                    $return['status'] = 200;
                    $return['last_start_time'] = $now;
                }
            }
        }
        echo json_encode($return);
    }
}