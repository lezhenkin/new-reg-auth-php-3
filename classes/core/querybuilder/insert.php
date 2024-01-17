<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

/**
 * Выполняет запрос типа INSERT к БД
 */
class Core_Querybuilder_Insert extends Core_Querybuilder_Statement
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
                throw new Exception("<p>Ошибка " . __METHOD__ . ": не передано имя таблицы для оператора INSERT</p>");
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
        $sPseudoValues = "(";
        $sFields = "(";

        try {
            if (!empty($this->_fields))
            {
                foreach ($this->_fields as $index => $sField)
                {
                    $sPseudoValues .= (($index) ? "," : "") . "?";
                    $sFields .= (($index) ? "," : "") . $this->_database->quoteColumnNames($sField);
                }

                $sPseudoValues .= ")";
                $sFields .= ")";

                $sQuery .= "INSERT INTO " . $this->_database->quoteColumnNames($this->_tableName) . " " . $sFields . " VALUES " . $sPseudoValues;
            }
            else
            {
                throw new Exception("<p>Ошибка " . __METHOD__ . ": не переданы поля для выполнения запроса INSERT</p>");
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
                throw new Exception("<p>Ошибка " . __METHOD__ . ": не строка запроса для оператора INSERT</p>");
            } 
            elseif (!empty($this->_values))
            {
                $dbh = $this->_database->getConnection();
                $stmt = $dbh->prepare($query);
                
                foreach ($this->_values as $aValues)
                {
                    for ($i = 1; $i <= count($aValues); ++$i)
                    {
                        $stmt->bindParam($i, $aValues[$i - 1]);
                    }
                    
                    $stmt->execute();
                }
            }
            else
            {
                throw new Exception("<p>Ошибка " . __METHOD__ . ": не переданы значения полей для выполнения запроса INSERT</p>");
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