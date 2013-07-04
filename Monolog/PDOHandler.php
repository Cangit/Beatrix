<?php

namespace Cangit\Beatrix\Monolog;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class PDOHandler extends AbstractProcessingHandler
{
    private $initialized = false;
    private $pdo;
    private $statement;
    private $table = 'monolog';
    private $fields;

    public function __construct($pdo, $level = Logger::NOTICE, $bubble = true)
    {
        $this->pdo = $pdo;
        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        if (!$this->initialized) {
            $this->initialize();
        }
        
        $fieldValues = array(
            'message' => $record['formatted'],
            'channel' => $record['channel'],
            'level' => $record['level'],
            'time' => $record['datetime']->format('U')
        );

        $arr = [];
        foreach ($this->fields as $arr) {
            $key = key($arr);
            $arr[$key] = $arr[$key];
        }

        $fieldValues = array_merge($fieldValues, $arr);

        $this->statement->execute($fieldValues);
    }

    public function addField($id, $value)
    {
        $this->fields[] = [$id => $value];
    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    private function initialize()
    {
        /*
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS monolog '
            .'(channel VARCHAR(255), level INTEGER, message LONGTEXT, time INTEGER UNSIGNED)'
        );
        */
       
        $fields = $values = '';

        foreach ($this->fields as $val) {
            $key = key($val);
            $fields .= $key . ', ';
            $values .= ':'. $key . ', ';
        }

        $this->statement = $this->pdo->prepare(
            'INSERT INTO '.$this->table.' ('.$fields.' message, channel, level, time) VALUES ('.$values.' :message, :channel, :level, :time)'
        );

        $this->initialized = true;
    }
}
