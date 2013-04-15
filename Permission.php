<?php

namespace Cangit\Beatrix;

class Permission
{

    private $session;
    private $logger;
    private $user;
    private $group;
    private $nation;
    private $system;

    public function __construct($session, $logger)
    {
        $this->session = $session;
        $this->logger = $logger;

        $level = $this->session->get('beatrix/permission', '9999');

        $this->system = substr($level, 0, 1);
        $this->nation = substr($level, 1, 1);
        $this->group = substr($level, 2, 1);
        $this->user = substr($level, 3, 1);
    }

    public function validate($level)
    {
        $system = substr($level, 0, 1);
        $nation = substr($level, 1, 1);
        $group = substr($level, 2, 1);
        $user = substr($level, 3, 1);

        if ($user != 0 && $user >= $this->user){
            return true;
        }

        if ($group != 0 && $group >= $this->group){
            return true;
        }

        if ($nation != 0 && $nation >= $this->nation){
            return true;
        }

        if ($system != 0 && $system >= $this->system){
            return true;
        }

        $this->logger->notice('Permission denied');
        return false;

    }

    private function set()
    {
        $level = $this->system . $this->nation . $this->group . $this->user;
        $this->session->set('beatrix/permission', $level);
    }

    public function user($level = null)
    {
        if ($level === null){
            return $this->user;
        }

        if (!is_numeric($level)){
            throw new \InvalidArgumentException('Invalid argument passed to method.');
        }

        $this->user = $level;
        $this->set();
    }

    public function group($level = null)
    {
        if ($level === null){
            return $this->group;
        }

        if (!is_numeric($level)){
            throw new \InvalidArgumentException('Invalid argument passed to method.');
        }

        $this->group = $level;
        $this->set();
    }

    public function nation($level = null)
    {
        if ($level === null){
            return $this->nation;
        }

        if (!is_numeric($level)){
            throw new \InvalidArgumentException('Invalid argument passed to method.');
        }

        $this->nation = $level;
        $this->set();
    }

    public function system($level = null)
    {
        if ($level === null){
            return $this->system;
        }

        if (!is_numeric($level)){
            throw new \InvalidArgumentException('Invalid argument passed to method.');
        }

        $this->system = $level;
        $this->set();
    }

}
