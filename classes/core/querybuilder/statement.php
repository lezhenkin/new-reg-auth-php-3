<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Хранит результат выполнения SQL-запроса или подготавливает его к выполнению
 */
abstract class Core_Querybuilder_Statement
{
    /**
     * Ссылка на соединение с СУБД
     * @var object Core_Database
     */
    protected $_database = NULL;
    
    /**
     * Перечень полей для запроса SELECT
     * @var array
     */
    protected $_select = [];

    /**
     * Имя таблицы для запроса SELECT
     * @var string
     */
    protected $_from = NULL;
    
    /**
     * Перечень полей для запроса INSERT
     * @var array
     */
    protected $_fields = [];
    
    /**
     * Перечень значений для запроса INSERT
     * @var array
     */
    protected $_values = [];

    /**
     * Имя таблицы для запроса INSERT
     * @var string
     */
    protected $_tableName = NULL;

    /**
     * Перечень условий для оператора WHERE
     * @var array
     */
    protected $_where = [];
    
    /**
	 * Тип SQL-запроса:
	 * 0 - SELECT
	 * 1 - INSERT
	 * 2 - UPDATE
	 * 3 - DELETE
	 */
	protected $_queryType = NULL;

    /**
     * Строит предварительную строку запроса из переданных данных
     */
    abstract function build();

    /**
     * Устанавливает имя таблицы для оператора SELECT
     * @param string $from
     * @return object self
     */
    public function from(string $from)
    {
        try {

            if (!is_string($from))
            {
                throw new Exception("<p>Методу " . __METHOD__ . "() нужно передать имя таблицы для запроса</p>");
            }
            
            // Экранируем данные
            $this->_from = Core_Database::instance()->quoteColumnNames($from);
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        return $this;
    }

    /**
     * Очищает массив условий отбора для оператора WHERE
     * @return object self
     */
    public function clearWhere()
    {
        $this->_where = [];

        return $this;
    }

    /**
     * Сохраняет перечень условий для оператора WHERE в SQL-запросе
     * @param string $field
     * @param string $condition
     * @param string $value
     * @return object self
     */
    public function where(string $field, string $condition, $value)
    {
        try {
            if (empty($field) || empty($condition))
            {
                throw new Exception("<p>Методу " . __METHOD__ . "() обязательно нужно передать значения имени поля и оператора сравнения</p>");
            }

            // Экранируем имена полей и значения, которые будут переданы оператору WHERE
            $this->_where[] = Core_Database::instance()->quoteColumnNames($field) . " " . $condition . " " . Core_Database::instance()->getConnection()->quote($value);
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        return $this;
    }

    /**
     * Устанавливает имя таблицы для оператора INSERT
     * @param string $tableName
     * @return object self
     */
    public function insert(string $tableName)
    {
        // Экранируем имя таблицы
        $this->_tableName = $this->quoteColumnNames($tableName);

        // Устанавливаем тип запроса INSERT
        $this->_queryType = 1;

        return $this;
    }

    /**
     * Устанавливает перечень полей для оператора INSERT
     * @return object self
     */
    public function fields()
    {
        try {
            // Если не было передано перечня полей
            if (empty(func_get_args()))
            {
                throw new Exception("Метод " . __METHOD__ . "() нельзя вызывать без параметров. Нужно передать перечень полей либо в виде строки, либо в виде массива");
            }

            // Сохраняем перечень полей в переменную
            $mFields = func_get_arg(0);

            // Если передан массив
            if (is_array($mFields))
            {
                // Просто сохраняем его
                $this->_fields = $mFields;
            }
            // Если передана строка
            elseif (is_string($mFields))
            {
                // Разбираем её, полученный массив сохраняем
                $this->_fields = explode(',', $mFields);
            }
            // В ином случае будет ошибка
            else
            {
                throw new Exception("Метод " . __METHOD__ . "() ожидает перечень полей либо в виде строки, либо в виде массива");
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        return $this;
    }

    /**
     * Устанавливает перечень значений, которые будут переданы оператору INSERT
     * @return object self
     */
    public function values()
    {
        try {
            // Если значения не переданы, это ошибка
            if (empty(func_get_args()))
            {
                throw new Exception("Метод " . __METHOD__ . "() нельзя вызывать без параметров. Нужно передать перечень значений либо в виде строки, либо в виде массива");
            }

            // Сохраняем переденные значения в переменную
            $mValues = func_get_arg(0);

            // Если был передан массив
            if (is_array($mValues))
            {
                // Просто сохраняем его
                $this->_values[] = $mValues;
            }
            // Если была передана строка
            elseif (is_string($mValues))
            {
                // Разбираем её, полученный массив сохраняем в объекте
                $this->_values[] = explode(',', $mValues);
            }
            // В ином случае будет ошибка
            else
            {
                throw new Exception("Метод " . __METHOD__ . "() ожидает перечень значений либо в виде строки, либо в виде массива");
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        return $this;
    }

    /**
     * Устанавливает тип запроса SELECT, INSERT и т.п.
     * @param integer $queryType
     * @return object self
     */
    public function setQueryType(int $queryType)
    {
        $this->_queryType = $queryType;

        return $this;
    }

    /**
	 * Возвращает тип запроса
	 * @return integer
	 */
	public function getQueryType()
	{
		return $this->_queryType;
	}
}
?>