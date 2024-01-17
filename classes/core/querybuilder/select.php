<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Выполняет запрос типа SELECT к БД
 */
class Core_Querybuilder_Select extends Core_Querybuilder_Statement
{
    public function __construct($arguments)
    {
        $this->_database = Core_Database::instance();

        if (empty($arguments))
        {
            $this->_select[] = "*";
        }
        else
        {
            $this->_select[] = $arguments[0];
        }
    }
    
    /**
     * Устанавливает перечень полей для запроса SELECT
     * @param string|array $data = "*"
     * @return object self
     */
    public function select($data = "*")
    {
        // Устанавливаем в объекте тип выполняемого запроса как SELECT
        $this->getQueryType() != 0 && $this->setQueryType(0);

        // Если методу не был передан перечень полей, очищаем все возможно установленные ранее поля
        if ($data == "*")
        {
            $this->clearSelect();
        }
        // Если передана строка, в которой поля перечислены через запятую
        elseif (count($aFields = explode(",", $data)))
        {
            $aQuotedFields = [];

            foreach ($aFields as $field)
            {
                $aQuotedFields[] = $this->_database->quoteColumnNames(trim($field));
            }
            
            $data = implode(", ", $aQuotedFields);
        }
        
        // Сохраняем поля
        try {
            // Если перечень полей был передан в виде строки
            if (is_string($data))
            {
                // Добавляем их к массиву в объекте
                $this->_select[] = $data;
            }
            // Если был передан массив, его нужно интерпретировать как указание имени поля и его псевдонима в запросе
            elseif (is_array($data))
            {
                // Если в переданном массиве не два элемента, это ошибка
                if (count($data) != 2)
                {
                    throw new Exception("<p>При передаче массива в качестве аргумента методу " . __METHOD__ . "() число элементов этого массива должно быть равным двум</p>");
                }
                // Если элементы переданного массива не являются строками, это ошибка
                elseif (!is_string($data[0]) || !is_string($data[1]))
                {
                    throw new Exception("<p>При передаче массива в качестве аргумента методу " . __METHOD__ . "() его элементы должны быть строками</p>");
                }
                // Если ошибок нет, сохраняем поля в массиве внутри объекта
                else
                {
                    // Имена полей экранируем
                    $this->_select[] = $this->_database->quoteColumnNames($data[0]) . " AS " . $this->_database->quoteColumnNames($data[1]);
                }
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
     * Устанавливает тип прелдставления результирующего набора в виде экземпляра объекта класса
     * @param mixed $className
     * @return object self
     */
    public function asObject($className)
    {
        $this->_database->asObject($className);
        
        return $this;
    }

    /**
     * Устанавливает тип прелдставления результирующего набора в виде ассоциативного массива
     * @return object self
     */
    public function asAssoc()
    {
        $this->_database->asAssoc();
        
        return $this;
    }

    /**
     * Очищает перечень полей для оператора SELECT
     * @return object self
     */
    public function clearSelect()
    {
        $this->_select = [];

        return $this;
    }

    /**
     * Строит предварительную строку запроса из переданных данных
     * @return string
     */
    public function build() : string
    {
        // Пустая строка для SQL-запроса
        $sQuery = "";

        // Строка оператора WHERE
        $sWhere = " WHERE ";

        // Сначала собираем строку для оператора WHERE
        foreach ($this->_where as $index => $sWhereRow)
        {
            // Для каждого из сохраненного массива для оператора WHERE формируем строку
            $sWhere .= (($index) ? " AND" : "") . " " . $sWhereRow;
        }
        
        $sQuery .= "SELECT " . ((!empty($this->_select)) ? implode(", ", $this->_select) : "*") . " FROM {$this->_from}" . $sWhere;

        return $sQuery;
    }

    public function query($query = NULL)
    {
        is_null($query) && $query = $this->build();
        
        return $this->_database->query($query);
    }
}
?>