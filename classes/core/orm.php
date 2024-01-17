<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

class Core_ORM
{
    /**
     * Тип выполняемого SQL-запроса
     * 0 - SELECT
	 * 1 - INSERT
	 * 2 - UPDATE
	 * 3 - DELETE
     * @var integer
     */
	protected $_queryType = NULL;

    /**
     * Наименование поля таблицы, в которой находится первичный ключ
     * В большинстве случаев это будет поле `id`, но нельзя исключать вероятность 
     * того, что кто-то назовёт поле с первичным ключом иначе
     * @var string
     */
    protected $_primaryKey = 'id';

    /**
     * Имя модели
     * @var string
     */
    protected $_modelName = '';

    /**
     * Имя таблицы в БД, соответствующей модели
     * @var string
     */
    protected $_tableName = '';
    
    /**
     * Статус инициализации объекта модели
     * @var boolean
     */
    protected $_init = FALSE;

    /**
     * Была ли загружена модель из базы данных
     * @var boolean
     */
    protected $_loaded = FALSE;

    /**
     * Объект подключения к СУБД
     * @var object Core_Database
     */
    protected $_database = NULL;

    /**
     * Объект взаимодействия с СУБД
     * @var object PDOStatement
     */
    protected $_queryBuilder = NULL;

    /**
     * Строка подготавливаемого запроса к БД
     * @var string
     */
    protected $_sql = NULL;
    

    /**
     * Строка последнего выполненного запроса к БД
     * @var string
     */
    protected $_lastQuery = NULL;

    /**
     * Объект результирующего запроса или подготовленного запроса
     * @var object
     */
    protected $_statement = NULL;
    
    /**
     * Исходные данные модели из таблицы в БД
     * @var array
     */
    protected $_initialData = [];

    /**
     * Новые данные модели для сохранения в БД
     * @var array
     */
    protected $_newData = [];

    /**
     * Имена столбцов таблицы в БД и их параметры
     * @var array
     */
    protected $_columns = [];

    /**
     * Перечень свойств модели, разрешенных к чтению
     * @var array
     */
    protected $_allowedProperties = [];

    /**
     * Перечень свойств модели, запрещенных к чтению 
     * @var array
     */
    protected $_forbiddenProperties = [];

    /**
     * Инициализирует объект модели
     * Этот метод на текущем этапе не имеет задач, которые должен решать,
     * поэтому просто устанавливаем, что объект инициализирован
     * А ещё загрузим информацию о столбцах таблицы
     */
    protected final function _init()
    {
        // Загружаем информацию о столбцах объекта
        $this->_loadColumns();
        
        // Устанавливаем, что объект инициализирован
        $this->_init = TRUE;
    }
    
    /**
     * Загружает информацию о столбцах таблицы модели в БД
     */
    protected function _loadColumns()
    {
        /**
         * Экземляров объектов класса может быть великое множество. Но все они, 
         * по сути, являются представлением строк одной и той же таблицы. 
         * А значит, у всех их будут одни и те же имена ячеек в результирующем наборе.
         * Можно один раз для модели эти имена считать, сохранить, и затем лишь
         * возвращать эти данные из хранилища
         */
        if (!Core_Cache::instance()->check($this->getModelName(), "columnCache"))
        {
            $oCore_Database = Core_Database::instance();
            $this->_columns = $oCore_Database->getColumns($this->getTableName());
            
            Core_Cache::instance()->set($this->getModelName(), "columnCache", $this->_columns);

            // Определяем, какое из полей таблицы имеет первичный ключ
            $this->findPrimaryKeyFieldName();

            return $this;
        }

        // Возвращаем данные из хранилища
        $this->_columns = Core_Cache::instance()->get($this->getModelName(), "columnCache", $this->_columns);
    }

    /**
     * Загружает первичную информацию модели
     */
    protected function _load($property = NULL)
    {
        // Создает персональный для объекта экземпляр класса построителя запросов
        $this->queryBuilder();
        
        // Если был указан идентификатор объекта
        !is_null($property) && !is_null($this->_initialData[$this->_primaryKey])
            // Добавляем его значение в условие отбора
            && $this->queryBuilder()
                    ->where($this->_primaryKey, '=', $this->getPrimaryKey());
        
        // Если задано имя свойства для загрузки
        if (!is_null($property))
        {
            // Вызываем построитель запросов
            $this->queryBuilder()
                // Очищаем перечень полей
                ->clearSelect()
                // Устанавливаем перечень полей
                ->select($property);

            $stmt = $this->queryBuilder()
                        // Формируем строку запроса
                        ->query()
                        // Результат получим в виде ассоциативного массива
                        ->asAssoc()
                        // Выполняем запрос
                        ->result();
            
            // Пытаемся получить результирующий набор
            $aPDOStatementResult = $stmt->fetch();

            // Очищаем перечень полей для запроса
            $this->queryBuilder()->clearSelect();

            $sCacheName = $this->_primaryKey . "." . $stmt->queryString;

            // Если результирующий набор не пустой, и если он не был кэширован
            if (!empty($aPDOStatementResult) && !Core_Cache::instance()->check($this->getModelName(), $sCacheName)) 
            {
                // Сохраняем в объект результирующий набор
                $this->_initialData[$property] = $aPDOStatementResult[$property];

                // Кэшируем результирующий набор
                Core_Cache::instance()->set($this->getModelName(), $sCacheName, $aPDOStatementResult[$property]);
            }
            // Если результирующий набор был кэширован
            elseif (Core_Cache::check($this->getModelName(), $this->_primaryKey . "." . $property))
            {
                $this->_initialData[$property] = Core_Cache::instance()->get($this->getModelName(), $sCacheName);
            }
        }
    }

    /**
     * Устанавливает значение первичного ключа для объекта — не для записи в БД
     */
    protected function _setPrimarykey(int $primary_key)
    {
        $this->_initialData[$this->_primaryKey] = $primary_key;
    }

    /**
     * Конструктор класса. Его можно вызвать только изнутри, либо фабричным методом
     */
    protected function __construct($primary_key = NULL)
    {
        // Инициализируем объект модели
        $this->_init();
        
        // Если конструктору было передано значение первичного ключа, сохраняем его в исходных данных объекта
        if (!isset($this->_initialData[$this->_primaryKey]))
        {
            $this->_initialData[$this->_primaryKey] = $primary_key;
        }
        
        // Загружаем из БД информацию объекта
        $this->_load();
        
        // Очищаем массив для новый значений свойств
        $this->clear();

        // Объект модели загружен данными
        $this->_loaded = TRUE;
    }

    /**
     * Загружает указанные данные модели
     */
    public function load($property = NULL)
    {
        // Загружаем данные
        $this->_load($property);
        
        return $this->_initialData[$property];
    }

    /**
     * Возвращает значение поля первичного ключа
     * @param mixed $returnedValueIWasNotFound вернется если не было найдено значение поля первичного ключа в случае, если был задан параметр отличный от NULL
     */
    public function getPrimaryKey($returnedValueIWasNotFound = NULL)
    {
        return (!empty($this->_initialData[$this->_primaryKey]) ? $this->_initialData[$this->_primaryKey] : $returnedValueIWasNotFound);
    }

    /**
     * Получает и возвращает имя модели
     * @return string
     */
    public function getModelName() : string
    {
        return $this->_modelName;
    }

    /**
     * Получает и возвращает имя таблицы в БД, соответствующей модели
     * @return string
     */
    public function getTableName() : string
    {
        return $this->_tableName;
    }

    /**
     * Получает имя поля, которое имеет первичный ключ
     */
    public function findPrimaryKeyFieldName()
    {
        try {
            if (is_null($this->getColumns()))
            {
                throw new Core_Exception("<p>Ошибка " . __METHOD__ . ": информация о полях таблицы ещё не была загружена.</p>");
            }

            foreach ($this->getColumns() as $name => $aField)
            {
                if (!empty($aField['key']) && $aField['key'] == "PRI")
                {
                    $this->_primaryKey = $name;

                    break;
                }
                else
                {
                    throw new Exception("<p>Ошибка " . __METHOD__ . ": таблица " . $this->getTableName() . " не имеет полей с первичным клочом.</p>");
                }
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();
        }
    }

    /**
     * Получает и возвращает перечень загруженных полей таблицы
     * @return array
     */
    public function getColumns() : array
    {
        return $this->_columns;
    }

    /**
     * Взаимодействует с СУБД от лица объекта модели
     */
    public function queryBuilder()
    {
        // Сохраняем в объекте ссылку на соединение с БД
        if (is_null($this->_database))
        {
            $this->_database = Core_Database::instance();
        }

        // Создаем экзепляр объекта построителя запросов, если его ещё нет
        if (is_null($this->_queryBuilder))
        {
            // Запрос типа SELECT
            $this->_queryBuilder = Core_Querybuilder::select();
            // Выбираем данные, которые не были удалены
            $this->_queryBuilder->from($this->getTableName())
                ->where('deleted', '=', 0);
        }

        return $this->_queryBuilder;
    }
    
    /**
     * Получает из базы данных все записи, которые вернет подготовленный запрос в соответствии с условиями
     * @return array
     */
    public function findAll()
    {
        // Переключаемся на выполнение запроса к БД
        $oCore_Querybuilder_Select = $this->queryBuilder()
                                        // Очищаем перечень запрашиваемых полей
                                        ->clearSelect()
                                        // Выбираем из базы данных значений тех полей модели, которые разрешены к чтению
                                        ->select(implode(", ", $this->_allowedProperties))
                                        ->asObject(get_class($this));
        
        /**
         * Если у объекта модели установлено значение первичноо ключа, добавляем его к условиям запроса
         * В ином случае вернется всё множество строк, соответствующих SQL-запросу
         */
        !is_null($this->getPrimaryKey()) 
            && $oCore_Querybuilder_Select->where($this->_primaryKey, '=', $this->getPrimaryKey());

        $sCacheKey = $oCore_Querybuilder_Select->build();

        // Если результат такого же запроса уже был кэширован
        if (!Core_Cache::instance()->check($this->getModelName(), $sCacheKey))
        {
            // Результат запроса сохраняем в объекте
            $this->_statement = $oCore_Querybuilder_Select->query()
                                    ->result();

            $return = $this->_statement->fetchAll();

            Core_Cache::instance()->set($this->getModelName(), $sCacheKey, $return);
        }
        else
        {
            $return = Core_Cache::instance()->get($this->getModelName(), $sCacheKey);
        }

        // Возвращаем резльутирующий набор вызову
        return $return;
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
    
    /**
     * Очищает объект от пользовательских данных
     * @return object self
     */
    public function clear()
    {
        // Если объект был создан результирующим набором СУБД, в него установлены значения полей, 
        // но они установлены с помощью магического метода __set()
        if (!$this->_loaded && !empty($this->_newData))
        {
            $this->_initialData = $this->_newData;
        }

        $this->_newData = [];

        return $this;
    }

    /**
     * Сохраняет информацию о модели в БД
     * @return object self
     */
    public function save()
    {
        // Если массив $this->_newData не пустой
        if (!empty($this->_newData))
        {
            // Если в объекте есть значение первичного ключа записи таблицы БД, значит информация не добавляется
            // в таблицу, а обновляется для существующей записи
            if (!is_null($this->getPrimaryKey()))
            {
                // Выполняем метод update()
                return $this->update();
            }

            $oCore_Querybuilder_Insert = Core_Querybuilder::insert($this->getTableName());

            // Устанавливаем поля и значения полей для оператора INSERT
            $aFields = [];
            $aValues = [];
            
            foreach ($this->_newData as $field => $value)
            {
                $aFields[] = $field;
                $aValues[] = $value;
            }

            $oCore_Querybuilder_Insert->fields($aFields)
                    ->values($aValues);
            
            $this->_statement = $oCore_Querybuilder_Insert->execute();

            // Если запрос был выполнен успешно, очищаем массив с данными для записи в БД
            if (Core_Database::instance()->getRowCount())
            {
                // Если данные модели ранее были кэшированы
                if (Core_Cache::instance()->check($this->getModelName()))
                {
                    // Удаляем их
                    Core_Cache::instance()->clean($this->getModelName());
                }

                // Если это был новый объект, который мы только что сохранили
                if (is_null($this->getPrimaryKey()))
                {
                    // Устанавливаем для объекта значение первичного ключа — не для записи в БД
                    $this->_setPrimaryKey(Core_Database::instance()->lastInsertId());
                }

                $this->clearEntity();
            }
        }

        /**
         * Мы должны запретить возможность установки значения ключевого поля таблицы. Оно у нас устанавливается как AUTO_INCREMENT
         */
        return $this;
    }

    public function update()
    {
        try {
            // Если массив $this->_newData не пустой, и у модели уже есть соответствующая ей запись в таблице БД
            if (!empty($this->_newData) && !is_null($this->getPrimaryKey()))
            {
                $oCore_Querybuilder_Update = Core_Querybuilder::update($this->getTableName());

                // Устанавливаем поля и значения полей для оператора INSERT
                $aFields = [];
                $aValues = [];
                
                foreach ($this->_newData as $field => $value)
                {
                    $aFields[] = $field;
                    $aValues[] = $value;
                }

                $oCore_Querybuilder_Update->fields($aFields)
                        ->values($aValues)
                        ->where($this->_primaryKey, '=', $this->getPrimaryKey());
                
                $this->_statement = $oCore_Querybuilder_Update->execute();

                // Если запрос был выполнен успешно, очищаем массив с данными для записи в БД
                if (Core_Database::instance()->getRowCount())
                {
                    // Если данные модели ранее были кэшированы
                    if (Core_Cache::instance()->check($this->getModelName()))
                    {
                        // Удаляем их
                        Core_Cache::instance()->clean($this->getModelName());
                    }
                    
                    // Перезагружаем объект
                    $this->clearEntity();
                }
            }
            else 
            {
                throw new Exception("<p>Ошибка: " . __METHOD__ .  ": обновлять информацию можно только для существующих записей в таблицах БД</p>");
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
     * Магический метод для установки значений необъявленных свойств класса
     * @param string $property
     * @param mixed $value
     */
    public function __set(string $property, $value)
    {
        // Если объект был создан загрузкой данных из результирующего набора, информации о столбцах может не быть
        $this->_loadColumns();
        
        // Если у таблицы, соответствующей модели, есть столбец с теким именем, 
        // то устанавливаем значение для последующего сохранения
        if (array_key_exists($property, $this->_columns))
        {
            $this->_newData[$property] = $value;
        }

        return $this;
    }

    /**
     * Магический метод для получения значения необъявленного свойства класса
     * Вернет значение из запрошенного поля таблицы, если оно разрешено в массиве $_allowedProperties и есть среди полей таблицы
     * @return mixed string|NULL
     */
    public function __get(string $property) : string | NULL
    {
        return ((array_key_exists($property, $this->_columns) && in_array($property, $this->_allowedProperties)) ? $this->load($property) : NULL);
    }
}
?>