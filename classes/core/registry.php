<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Паттерн Реестр. Безопасная замена глобальным переменным
 */
class Core_Registry
{
    /**
     * Экземпляр статического класса
     * @var object self
     */
    static protected $_instance = NULL;

    /**
     * Хранимые данные
     * @var array
     */
    protected $_data = [];

    /**
     * Создает и возвращает экземпляр статического класса
     */
    static public function instance()
    {
        if (is_null(static::$_instance))
        {
            static::$_instance = new static();
        }

        return static::$_instance;
    }

    /**
     * Проверяет наличие данных в объекте
     * @param string $name
     * @return boolean 
     */
    public function check($name) : bool
    {
        return !empty($this->_data[$name]);
    }

    /**
     * Устанавливает в объект данные для хранения
     * @param string $name
     * @param mixed $data
     * @return object self
     */
    public function set($name, $data)
    {
        $this->_data[$name] = $data;

        return $this;
    }

    /**
     * Получает данные из объекта, если они были сохранены
     * @param string $name
     * @return mixed 
     */
    public function get($name)
    {
        return ($this->check($name)) ? $this->_data[$name] : NULL;
    }

    /**
     * Очищает объект от хранимых данных
     * @param mixed $name string|NULL
     * @return boolean
     */
    public function clean($name = NULL) : bool
    {
        $return = FALSE;

        if (is_null($name))
        {
            $this->_data = [];

            $return = $this->_data === [];
        }
        else
        {
            unset($this->_data[$name]);

            $return = !isset($this->_data[$name]);
        }

        return $return;
    }
    
    /**
     * Получает и возвращает все хранимые в объекте данные
     * @return array
     */
    public function getAll() : array
    {
        return $this->_data;
    }
}
?>
