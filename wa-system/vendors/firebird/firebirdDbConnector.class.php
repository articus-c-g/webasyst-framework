<?php

include_once('firebirdDbAdapter.class.php');

class firebirdDbConnector
{
    private static $connections = array();
    private static $config;
    
    protected function __construct() {}

    public static function getConnection($name = 'default', $writable = true)
    {
        if (is_array($name)) {
            $settings = $name;
            $name = md5(var_export($name, true));
            if (!isset($settings['type'])) {
                $settings['type'] = function_exists('mysqli_connect') ? 'mysqli' : 'mysql';
            }
        }
        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        } else {
            if (empty($settings)) {
                $settings = self::getConfig($name);
            }
            if (strtolower($settings['type']) == 'ibase') {
                $class = "firebirdDbAdapter";
            } else {
                $class = "waDb".ucfirst(strtolower($settings['type']))."Adapter";
            }
            if (!class_exists($class)) {
                throw new waDbException(sprintf("Database adapter %s not found", $class));
            }
            return self::$connections[$name] = new $class($settings);
        }
    }
            
    protected static function getConfig($name)
    {
        if (self::$config === null) {
            self::$config = waSystem::getInstance()->getConfig()->getDatabase();
        }
          if (!isset(self::$config[$name])) {
               throw new waDbException(sprintf("Unknown Database Connection %s", $name));
           }
           if (!isset(self::$config[$name]['type'])) {
               self::$config[$name]['type'] = function_exists('mysqli_connect') ? 'mysqli' : 'mysql';
           }
        return self::$config[$name];
    }

}