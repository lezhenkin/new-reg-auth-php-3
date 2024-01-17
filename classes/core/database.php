<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Абстрактый класс
 * Обеспечивает подключение к СУБД
 * Класс является абстрактным, так как оставляет пользователю право определять, через какой
 * модуль будет реализовано взаимодействие с СУБД
 * 
 * Реализация такого взаимодействия должна быть написана в дочерних классах
 * Например, вызов Core_Database::instance('mysql') вернет экземпляр класса Core_Database_Mysql
 */
abstract class Core_DataBase
{
    // Экземпляр класса
    static protected $_instance = [];

    // Параметры подключение к СУБД
    protected $_config = [];

    // Здесь будет храниться последний выполненный запрос к БД
    protected $_lastQuery = NULL;

    /**
     * Подключение к СУБД
     * @var resource
     */
    protected $_connection = NULL;

    /**
     * Число строк в результате запросе
     * @var int
     */
    protected $_lastQueryRows = NULL;

    /** Абстрактные методы не имеют реализации. Они должны быть реализованы в дочерних классах */

    // Подключение к СУБД
    abstract public function connect();

    // Отключение от СУБД
    abstract public function disconnect();

    // Установка кодировки соединения
    abstract public function setCharset($charset);

    // Экранирование данных
    abstract public function escape($unescapedString);

    // Представление результата в виде объекта
    abstract public function asObject();

    // Представление результата в виде ассоциативного массива
    abstract public function asAssoc();

    // Установка SQL-запроса
    abstract public function query($query);

    // Выполнение запроса
    abstract public function result();

    // Имена методов говорят сами за себя
    abstract function lastInsertId();

    /**
     * Защищенный конструктор класса, который невозможно вызвать откуда-либо, кроме как из самого класса
     * Получает параметры подключения к СУБД
     */
    protected function __construct(array $config)
    {
        $this->setConfig($config);
    }

    /**
     * Возвращает и при необходимости создает экзепляр класса
     * @return object Core_Database
     */
    static public function instance(string $name = 'pdo')
    {
        // Если экземпляр класса не был создан
        if (empty(static::$_instance[$name]))
        {
            // Получаем параметры подключения к СУБД
            $aConfig = Core::$config->get('core_database', array());
            
            if (!isset($aConfig[$name]))
			{
				throw new Exception('Для запрошенного типа подключения к СУБД нет конфигурации');
			}

            // Определяем, какой именно класс будем использовать
            // Он будет именоваться Core_Database_{$name}, например Core_Database_Pdo или Core_Database_Mysql
			$driver = __CLASS__ . "_" . ucfirst($name);
			
			static::$_instance[$name] = new $driver($aConfig[$name]);
        }

        // Возвращем вызову экземпляр класса для работы с СУБД
        return static::$_instance[$name];
    }

    public function setConfig(array $config)
    {
        $this->_config = $config + [
			'host' => 'localhost',
			'user' => '',
			'password' => '',
			'dbname' => NULL,
			'charset' => 'utf8'
        ];

        return $this;
    }

    /**
     * Получает перечень полей таблицы из БД
     * @param string $tableName имя таблицы
     * @param string $likeCondition значение для применения оператора LIKE
     * @return array
     */
    public function getColumns(string $tableName, string $likeCondition = NULL) : array
    {
        // Подключаемся к СУБД
        $this->connect();

        // Составляем строку запроса
        $sQuery = "SHOW COLUMNS FROM " . $this->quoteColumnNames($tableName);

        // Если есть значения для условия оператора LIKE
        if (!is_null($likeCondition))
		{
			$sQuery .= ' LIKE ' . $this->quote($likeCondition);
		}

        // Выполняем запрос, результат получаем в виде ассоциативного массива
        $result = $this->query($sQuery)->asAssoc()->result();

		$return = [];
		
        // Собираем информацию о столбцах
        foreach ($result as $row)
		{
			$column['name'] = $row['Field'];
			$column['columntype'] = $row['Type'];
			$column['null'] = ($row['Null'] == 'YES');
			$column['key'] = $row['Key'];
			$column['default'] = $row['Default'];
			$column['extra'] = $row['Extra'];
			
			$return[$column['name']] = $column;
		}
        
        // Возвращаем вызову результат
        return $return;
    }

    /**
     * Экранирует имена полей или таблиц для применения в строке SQL-запроса
     * @param string $value
     * @return string
     */
    public function quoteColumnNames(string $value) : string
    {
        return preg_replace('/(?<=^|\.)(\w+)(?=$|\.)/ui', '`$1`', $value);
    }

    /**
     * Возвращает строку последнего выполненного запроса
     * @return string | NULL
     */
    public function getLastQuery()
    {
        return $this->_lastQuery;
    }

    /**
     * Возвращает число строк из последнего результата запроса
     */
    public function getRowCount()
    {
        return $this->_lastQueryRows;
    }

}
?>