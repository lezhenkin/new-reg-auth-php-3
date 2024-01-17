<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Выполняет запрос типа UPDATE к БД
 */
class Core_Querybuilder_Update extends Core_Querybuilder_Statement
{
    /**
     * Конструктор класса
     */
    public function __construct($arguments)
    {
        $this->_database = Core_Database::instance();
        
        try {
            if (!empty($arguments) && !empty($arguments[0]))
            {
                $this->_tableName = $arguments[0];
            }
            else
            {
                throw new Exception("<p>Ошибка " . __METHOD__ . ": не передано имя таблицы для оператора UPDATE</p>");
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }
    }
    
    /**
     * Строит предварительную строку запроса из переданных данных
     * @return string
     */
    public function build() : string
    {
        // Пустая строка для SQL-запроса
        $sQuery = "";
        
        /**
         * Здесь мы воспользуемся механизмом подготовки запроса от PDO
         * https://www.php.net/manual/ru/pdo.prepared-statements.php
         */

        
        // Строка оператора WHERE
        $sWhere = " WHERE ";

        // Сначала собираем строку для оператора WHERE
        foreach ($this->_where as $index => $sWhereRow)
        {
            // Для каждого из сохраненного массива для оператора WHERE формируем строку
            $sWhere .= (($index) ? " AND" : "") . " " . $sWhereRow;
        }

        $aSets = [];

        try {
            if (!empty($this->_fields))
            {
                foreach ($this->_fields as $index => $sField)
                {
                    $aSets[] = $sField . " = :{$sField}";
                }

                $sQuery .= "UPDATE " . $this->_database->quoteColumnNames($this->_tableName) . " SET " . implode(", ", $aSets) . $sWhere;
            }
            else
            {
                throw new Exception("<p>Ошибка " . __METHOD__ . ": не переданы поля для выполнения запроса UPDATE</p>");
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        return $sQuery;
    }

    public function execute() : PDOStatement
    {
        $query = $this->build();
        
        try {
            if (empty($query))
            {
                throw new Exception("<p>Ошибка " . __METHOD__ . ": не строка запроса для оператора UPDATE</p>");
            }
            // Защитим себя от обновления всех записей таблицы вместо требуемых
            elseif (empty($this->_where))
            {
                throw new Exception("<p>Ошибка " . __METHOD__ . ": для оператора UPDATE следует указать значение для выбора обновляемых данных</p>");
            } 
            elseif (!empty($this->_values))
            {
                $dbh = $this->_database->getConnection();
                $stmt = $dbh->prepare($query);
                
                foreach ($this->_fields as $index => $sField)
                {
                    $stmt->bindParam(":{$sField}", $this->_values[0][$index]);
                }

                $stmt->execute();
            }
            else
            {
                throw new Exception("<p>Ошибка " . __METHOD__ . ": не переданы значения полей для выполнения запроса UPDATE</p>");
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }
        
        // Возвращаем реультат запроса
        return $stmt;
    }
}
?>