<?php

namespace Cangit\Beatrix;

class DBAL
{

    private $handles = [];
    private $pdoHandles = [];
    private $activeHandle = 'default';
    private $cache;
    private $logger;
    
    public function __construct(Cache\CacheInterface $cache, $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getPdoHandle($handle = null)
    {
        return $this->getHandle($handle, true);
    }

    public function getHandle($handle = null, $rawPdo = false)
    {
        if ($handle === null){
            $handle = $this->activeHandle;
        }

        if (isset($this->handles[$handle])){
            if ($rawPdo === false){
                return $this->handles[$handle];
            } else {
                return $this->pdoHandles[$handle];
            }
        }

        if (!is_string($handle)){
            throw new \InvalidArgumentException(sprintf('Wrong argument type passed to getHandle(). Expecting string, was "%s"', gettype($handle)), E_ERROR);
        }

        $rawPDOConfig = $this->cache->file('beatrixDB', APP_ROOT.'/app/config/db.yml', 'yml');

        if (isset($rawPDOConfig[$handle])){
            return $this->createHandle(
                $handle,
                $rawPDOConfig[$handle]['connectionString'],
                $rawPDOConfig[$handle]['username'],
                $rawPDOConfig[$handle]['password'],
                $rawPDOConfig[$handle]['attributes'],
                $rawPdo
            );
        }
        
        throw new \Exception(sprintf('getHandle() was called, but the db handle "%s" was not defined.', $handle), E_ERROR);
    }

    public function setHandle($setHandle)
    {
        if(!is_string($setHandle)){
            throw new \InvalidArgumentException(sprintf("Wrong argument type passed to setHandle(). Expecting string, was '%s' ", gettype($setHandle)), E_ERROR);
        }

        $this->activeHandle = $setHandle;
    }
    
    public function createHandle($handle, $connectionStr, $username, $password, $attributes = [], $rawPdo = false)
    {
        try
        {
            // On some installations \PDO::MYSQL_ATTR_INIT_COMMAND is gone, Therefore we use 1002
            $dbh = new \PDO($connectionStr, $username, $password, [1002 => "SET NAMES utf8"]);
                        
            foreach($attributes as $key => $val){
                if (is_bool($val) or is_int($val)){
                    $dbh->setAttribute(constant("\PDO::".$key), $val);
                } else {
                    $dbh->setAttribute(constant("\PDO::".$key), constant("\PDO::".$val));
                }
            }

        } catch (\PDOException $e){
            $inject = ['connectionString' => $connectionStr, 'username' => $username];
            $this->logger->alert('PDOException. Unable to connect to database.', $inject);
            throw new \Exception('Failed to create db connection.', E_ERROR);
        } catch (\Exception $e){
            $inject = ['connectionString' => $connectionStr, 'username' => $username];
            $this->logger->alert('Unknown exception trying to establish db connection.', $inject);
            throw new \Exception('Failed to set up db connection.', E_ERROR);
        }

        $this->pdoHandles[$handle] = $dbh;
        $params = ['pdo' => $dbh];
        $dbh = \Doctrine\DBAL\DriverManager::getConnection($params);
        $this->handles[$handle] = $dbh;
        if ($rawPdo === false){
            return $this->handles[$handle];
        } else {
            return $this->pdoHandles[$handle];
        }
    }
    
    public function closeHandle($attrHandle = null)
    {
        if($attrHandle === null){
            $attrHandle = $activeHandle;
        }

        if (!isset($this->handles[$attrHandle])){
            throw new \Exception('Tried closing a handle that does not exist.', E_WARNING);
        }

        $this->handles[$attrHandle] = null;
    }

}
