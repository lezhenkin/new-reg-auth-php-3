<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

class Core_Entity extends Core_ORM
{
    /**
     * Конструктор класса объекта модели
     * @param integer|NULL $primary_key
     */
    protected function __construct($primary_key = NULL)
    {
        // Вызываем родительский конструктор класса объекта модели
        parent::__construct($primary_key);
    }

    public static function factory($modelName, $primary_key = NULL) : Core_ORM
    {
        $className = ucfirst($modelName) . "_Model";
        
        return new $className($primary_key);
    }
  
    /**
     * Очищает объект от новых пользовательских данных
     * @return object self
     */
    public function clearEntity()
    {
        // Очищаем массив с данными
        $this->_newData = [];

        // Загружаем информацию об объекте из БД
        $this->_load();

        return $this;
    }

}
?>