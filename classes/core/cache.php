<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Сохраняет данные, возвращает их в случае однотипных запросов или выражений, которые должны возвращать данные
 */
class Core_Cache
{
    /**
     * Экземпляр статического класса
     * @var object self
     */
    static protected $_instance = NULL;

    /**
     * Кэшированные данные
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
     * @param mixed string|NULL $key
     * @return boolean 
     */
    public function check($name, $key = NULL) : bool
    {
        $return = FALSE;

        if (is_null($key))
        {
            $return = !empty($this->_data[$name]);
        }
        else
        {
            $return = !empty($this->_data[$name][$key]);
        }

        return $return;
    }

    /**
     * Устанавливает в объект данные для хранения
     * @param string $name
     * @param string $key
     * @param mixed $data
     * @return object self
     */
    public function set($name, $key, $data)
    {
        $this->_data[$name][$key] = $data;

        return $this;
    }

    /**
     * Получает данные из объекта, если они были сохранены
     * @param string $name
     * @param string $key
     * @return mixed 
     */
    public function get($name, $key = NULL)
    {
        return ($this->check($name, $key)) ? $this->_data[$name][$key] : NULL;
    }

    /**
     * Очищает объект от хранимых данных
     * @param mixed $name string|NULL
     * @param mixed $key string|NULL
     * @return boolean
     */
    public function clean($name = NULL, $key = NULL) : bool
    {
        $return = FALSE;

        if (is_null($name) && is_null($key))
        {
            $this->_data = [];

            $return = $this->_data === [];
        }
        elseif (!is_null($name) && is_null($key))
        {
            unset($this->_data[$name]);

            $return = !isset($this->_data[$name]);
        }
        elseif (!is_null($name) && !is_null($key))
        {
            unset($this->_data[$name][$key]);

            $return = !isset($this->_data[$name][$key]);
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
