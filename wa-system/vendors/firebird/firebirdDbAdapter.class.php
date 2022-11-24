<?php

class firebirdDbAdapter extends waDbIbaseAdapter
{
    protected $trh = null; // Transaction handler

    public function getHandler()
    {
        return $this->handler;
    }

    public function query($query)
    {
        if ($this->trh) {
            return @ibase_query($this->trh, $query);
        } else {
            return @ibase_query($this->handler, $query);
        }
    }

    public function error()
    {
        return ibase_errmsg();
    }

    public function errorCode()
    {
        return ibase_errcode();
    }

    public function transaction($args) 
    {
        $result = ibase_trans($this->handler, $args);
        if ($result) {
            $this->trh = $result;
        }
        return $result;
    }

    public function commit() 
    {
        if ($this->trh) {
            $result =  ibase_commit($this->trh);
            $this->trh = null;
        } else {
            $result = false;
        }
        return $result;
    }

    public function rollback() 
    {
        if ($this->trh) {
            $result = ibase_rollback($this->trh);
            $this->trh = null;
        } else {
            $result = false;
        }
        return $result;
    }

}