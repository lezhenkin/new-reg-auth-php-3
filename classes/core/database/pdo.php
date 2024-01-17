<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

class Core_Database_Pdo extends Core_DataBase
{
    /** Результат выполнения запроса
     * @var resource | NULL
     */
    protected $_result = NULL;

    /**
     * Представление результата запроса в виде ассоциативного массива либо объекта
     */
    protected $_fetchType = PDO::FETCH_OBJ;

    /**
     * Имя класса, который будет создаваться под результат выборки в ответ на SQL-запрос
     * @var object
     */
    protected $_asObject = NULL;

    /**
     * Возвращает активное подключение к СУБД
     * @return resource
     */
    public function getConnection()
    {
        $this->connect();

        return $this->_connection;
    }

    /**
     * Подключается к СУБД
     * @return boolean TRUE | FALSE
     */
    public function connect()
    {
        // Если подключение уже выполнено, ничего не делаем
        if ($this->_connection)
        {
            return TRUE;
        }
        $this->_config += array(
			'driverName' => 'mysql',
			'attr' => array(
				PDO::ATTR_PERSISTENT => FALSE
			)
		);

        // Подключаемся к СУБД
        try {
            // Адрес сервера может быть задан со значением порта
            $aHost = explode(":", $this->_config['host']);
            
            // Формируем строку источника подключения к СУБД
            $dsn = "{$this->_config['driverName']}:host={$aHost[0]}";

            // Если был указан порт
            !empty($aHost[1])
                && $dsn .= ";port={$aHost[1]}";

            // Указываем имя БД
            !is_null($this->_config['dbname'])
				&& $dsn .= ";dbname={$this->_config['dbname']}";
            
            // Кодировка
            $dsn .= ";charset={$this->_config['charset']}";

            // Подключаемся, и сохраняем подключение в экземпляре класса
            $this->_connection = new PDO(
				$dsn,
				$this->_config['user'],
				$this->_config['password'],
				$this->_config['attr']
			);
            
            // В случае ошибок будет брошено исключение
			$this->_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        }
        catch (PDOException $e)
        {
            throw new Exception("<p><strong>Ошибка при подключении к СУБД:</strong> {$e->getMessage()}</p>");
        }
        
        // Если ничего плохого не произошло
        return TRUE;
    }
    
    /**
     * Закрывает соединение с СУБД
     * @return self
     */
    public function disconnect()
    {
        $this->_connection = NULL;

        return $this;
    }
    
    /**
     * Устанавливает кодировку соединения клиента и сервера
     * @param string $charset указанное наименование кодировки, которое примет СУБД
     */
    public function setCharset($charset)
    {
        $this->connect();

		$this->_connection->exec('SET NAMES ' . $this->quote($charset));

		return $this;
    }
    
    /**
     * Экранирование строки для использования в SQL-запросах
     * @param string $unescapedString неэкранированная строка
     * @return string Экранированная строка
     */
    public function escape($unescapedString) : string
    {
        $this->connect();

		$unescapedString = addcslashes(strval($unescapedString), "\000\032");

		return $this->_connection->quote($unescapedString);
    }

    /**
     * Возвращает результат работы метода PDO::quote()
     * @return string
     */
    public function quote(string $value) : string
    {
        return $this->_connection->quote($value);
    }

    /**
     * Возвращает идентификатор последней вставленной записи в БД, если такой имеется
     * @return integer|string|NULL
     */
    public function lastInsertId()
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Устанавливает строку запроса, который будет выполнен позднее
     * @param string $query
     * @return object self
     */
    public function query($query)
    {
        // Переданную строку запроса сохраняем, чтобы её потом можно было просмотреть
        $this->_lastQuery = $query;

        // По умолчанию устанавливаем, что результат запроса хотим получать в виде объекта
        !$this->_fetchType && $this->_fetchType = PDO::FETCH_OBJ;

        return $this;
    }

    /**
     * Устанавливает тип представления данных в результате запроса в виде объекта
     * @param mixed object|NULL
     * @return object self
     */
    public function asObject($className = NULL)
    {
        $this->_fetchType = PDO::FETCH_CLASS;

        $this->_asObject = $className;
        
        return $this;
    }

    /**
     * Устанавливает тип представления данных в результате запроса в виде ассоциативного массива
     * @return object self
     */
    public function asAssoc()
    {
        $this->_fetchType = PDO::FETCH_ASSOC;

        return $this;
    }

    /**
     * Выполняет запрос SELECT, возвращает результат выполнения
     * @return object PDOStatement
     */
    public function result() : PDOStatement
    {
        // Результат выполнения запроса сохраняем внутри объекта
        $this->_result = match($this->_fetchType) {
                    PDO::FETCH_CLASS => $this->_connection->query($this->_lastQuery, $this->_fetchType, $this->_asObject),
                    PDO::FETCH_OBJ => $this->_connection->query($this->_lastQuery, $this->_fetchType),
                    PDO::FETCH_ASSOC => $this->_connection->query($this->_lastQuery, $this->_fetchType)
                };
        
        // Определяем количество строк в результате запроса, сохраняем внутри объекта
        $this->_lastQueryRows = $this->_result->rowCount();
        
        return $this->_result;
    }
}
?>