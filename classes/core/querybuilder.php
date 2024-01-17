<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Строит запросы к СУБД
 */
abstract class Core_Querybuilder
{
    protected function __construct($type)
    {

    }

    public static function factory($type, $arguments)
    {
        $queryBuilderClassName = __CLASS__ . "_" . ucfirst($type);

        return new $queryBuilderClassName($arguments);
    }
    
    public static function select()
    {
        return Core_Querybuilder::factory('Select', func_get_args());
    }

    public static function insert()
    {
        return Core_Querybuilder::factory('Insert', func_get_args());
    }

    public static function update()
    {
        return Core_Querybuilder::factory('Update', func_get_args());
    }
}
?>