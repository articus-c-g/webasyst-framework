<?php

include_once('firebirdDbConnector.class.php');

class ibaseModel extends waModel implements Iterator
{
    private static $_models = array();

    private $query_result = false;
    private $records = array();
    private $key = -1;
    private $eof = true;
    private $fetch_assoc = true;
    protected $params = array();
    protected $sql = '';
    protected $where = '';

    /**
     * Database Adapter
     * @var firebirdDbAdapter
     */
    public $adapter;

    public function __construct($name)
    {
        $this->fetch_assoc = true;
        $this->writable = false;
        $this->adapter = firebirdDbConnector::getConnection($name, $this->writable);
        if ($this->table && !$this->fields) {
            $this->getMetadata();
        }
        if (ibase_errcode() !== false) {
            throw new Exception(ibase_errmsg(), ibase_errcode());
        }
    }

    public static function model($className = __CLASS__, $connection = null)
    {
        if (isset(self::$_models[$className])) {
            return self::$_models[$className];
        } else {
            $model = self::$_models[$className] = new $className($connection);
            return $model;
        }
    }

    public function transaction($args)
    {
        $this->adapter->transaction($args);
    }

    protected function arrayToLoLower(&$ar)
    {
        if (is_array($ar) || ($ar instanceof Traversable))
            foreach ($ar as $k => $value) {
                if ($k)
                    $ar[mb_strtolower($k)] = $value;
            }
        return $ar;
    }

    public function set_fetch_assoc($fetch_assoc)
    {
        $this->fetch_assoc = $fetch_assoc;
    }

    public function active()
    {
        return $this->query_result ? true : false;
    }

    private function make_field_info()
    {
        if (!$this->active()) {
            return;
        }
        if (!$this->query_result)
            return;
        if (is_resource($this->query_result)) {
            $c = ibase_num_fields($this->query_result);
            for ($i = 0; $i < $c; $i++) {
                $field_info = ibase_field_info($this->query_result, $i);
                $field_info['num'] = $i;
                $this->fields[$field_info['alias']] = $field_info;
            }
        }
    }

    public function fetch_sql($sql = null, $params = null)
    {
        $this->set_fetch_assoc(true);
        $this->execute_sql($sql, $params);
        return $this->next();
    }


    public function count()
    {
        $sql = $this->sql;
        $count_sql = 'select count(*) as result from (' . $this->sql . ')';
        $this->execute_sql($count_sql);
        $this->sql = $sql;
        $row = $this->next();
        return $row['RESULT'];

    }

    public function execute_sql($sql = null, $params = null)
    {
        $this->close();
        if (isset($sql)) {
            $this->sql = $sql;
        }
        $this->execute($params);
    }

    public function execute($params = null)
    {
        $this->key = -1;
        $this->records = array();
        if (isset($params)) {
            $this->query_result = $this->exec($this->sql, $params);
        } else {
            $this->query_result = $this->exec($this->sql, $this->params);
        }
        $this->make_field_info();
    }


    public function open()
    {
        // выполняем текущий запрос, если он еще не был выполнен
        if ($this->active()) {
            // если уже запрос открыт, то выходим
            return;
        }
        $this->execute();
    }

    public function close()
    {
        if ($this->active()) {
            if (is_resource($this->query_result))
                ibase_free_result($this->query_result);
            $this->query_result = null;
        }
    }

    public function set_param_value($param, $value)
    {
        $this->params[$param] = $value;
    }

    public function field_value($field_name)
    {
        if ($this->key == -1) {
            // если не было прочитано не одной записи, то считываем первую запись
            $record = $this->next();
        } else {
            $record = $this->records[$this->key];
        }
        $field_info = $this->fields[strtoupper($field_name)];
        if ($this->fetch_assoc) {
            return $record[strtoupper($field_name)];
        } else {
            return $record[$field_info['num']];
        }
    }

    public function key()
    {
        return $this->key;
    }

    private function next_fetch()
    {
        if ($this->fetch_assoc) {
            $row = @ibase_fetch_assoc($this->query_result);
        } else {
            $row = @ibase_fetch_row($this->query_result);
        }
        if (!$row && ibase_errcode()) {
            throw new Exception(ibase_errmsg(), ibase_errcode());
        }
        if ($row) {
            $this->records[] = $row;
            $this->key = count($this->records) - 1;
        }
        return $row;
    }

    private function next_record()
    {
        $this->key++;
        return $this->records[$this->key];
    }

    private function _next()
    {
        if (count($this->records) - 1 == $this->key) {
            // последняя элемент в массиве
            // берем следующую запись из базы
            $row = $this->next_fetch();
        } else {
            // берем следующий элемент массива
            $row = $this->next_record();
        }
        $this->eof = $row ? false : true;
        return $row;
    }

    public function last()
    {
        while (true) {
            $this->next();
            if ($this->eof)
                break;
        }
    }

    public function rewind()
    {
        if ($this->active()) {
            $this->key = -1;
        } else {
            $this->open();
        }
        $this->next(); // Читаем первую запись    
    }

    public function valid()
    {
        return !$this->eof;
    }

    public function escape($value, $type = null)
    {
        if (is_string($value)) {
            $value = str_replace("'", "''", (string)$value);
            return str_replace('"', '"', (string)$value);
        } else {
            return $value;
        }
    }

    private function run($sql, $unbuffer = false)
    {
        $sql = trim($sql);
        $result = $this->adapter->query($sql);

        if (!$result) {
            $error = "Query Error\nQuery: " . $sql .
                "\nError: " . $this->adapter->errorCode() .
                "\nMessage: " . $this->adapter->error();

            $trace = debug_backtrace();
            $stack = "";
            $default = array('file' => 'n/a', 'line' => 'n/a');

            foreach ($trace as $i => $row) {
                $row = array_merge($row, $default);
                $stack .= $i . ". " . $row['file'] . ":" . $row['line'] . "\n" .
                    (isset($row['class']) ? $row['class'] : '') .
                    (isset($row['type']) ? $row['type'] : '') .
                    $row['function'] . "()\n";
            }

            waLog::log($error . "\nStack:\n" . $stack, 'db.log');
            throw new Exception($error, $this->adapter->errorCode());
        }
        return $result;
    }

    public function getBlob($blobId)
    {
        if ($blob_hndl = @ibase_blob_open($blobId)) {
            $result = ibase_blob_get($blob_hndl, 8192);
            while ($data = ibase_blob_get($blob_hndl, 8192)) {
                $result .= $data;
            }
            ibase_blob_close($blob_hndl);
            return $result;
        }
        return null;
    }

    public function next()
    {
        $buffer = $this->_next();

        if ($buffer) {
            return $this->arrayToLoLower($buffer);
        } else {
            return $buffer;
        }
    }

    public function current()
    {
        return $this->arrayToLoLower($this->records[$this->key]);
    }

    public function getIterator()
    {
        $records = array();
        while ($record = $this->next()) {
            $records[] = $record;
        }
        return $records;
    }


}
