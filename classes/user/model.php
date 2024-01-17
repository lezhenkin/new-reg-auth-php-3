<?php
// Запрещаем прямой доступ к файлу
defined('MYSITE') || exit('Прямой доступ к файлу запрещен');

class User_Model extends Core_Entity
{
    /**
     * Имя модели
     * @var string
     */
    protected $_modelName = 'user';

    /**
     * Имя таблицы в БД, соответствующей модели
     * @var string
     */
    protected $_tableName = 'users';

    /**
     * Разрешенные для чтения и записи поля таблицы пользователей
     * @var array
     */
    protected $_allowedProperties = [
        'id',
        'login',
        'email',
        'registration_date',
        'active'
    ];

    /**
     * Запрещенные для чтения и записи поля таблицы пользователей
     * @var array
     */
    protected $_forbiddenProperties = [
        'password',
        'deleted'
    ];

    /**
     * Строка для действия регистрации
     * @param const 
     */
    public const ACTION_SIGNUP = 'sign-up';
    
    /**
     * Строка для действия регистрации
     * @param const 
     */
    public const ACTION_SIGNIN = 'sign-in';

    /**
     * Строка для действия выхода из системы
     * @param const 
     */
    public const ACTION_LOGOUT = 'exit';

    /**
     * Проверяет, не был ли авторизован пользователь ранее
     * @param string $value логин или адрес электропочты
     * @return boolean TRUE|FALSE
     */
    protected function _checkCurrent(string $value) : bool
    {
        $bIsAuth = FALSE;

        // Если в сессии сохранены логин или электропочта, а функции переданы значения для проверки, и они совпадают с теми, что хранятся в сессии
        if ((!empty($_SESSION['user_id']) || !empty($_SESSION['email'])) && ($_SESSION['login'] === $value || $_SESSION['email'] === $value))
        {
            // Пользователь авторизован
            $bIsAuth = TRUE;
        }
        // Если есть попытка подмены данных в сессии
        elseif ((!empty($_SESSION['login']) || !empty($_SESSION['email'])) && $_SESSION['login'] !== $value && $_SESSION['email'] !== $value)
        {
            // Стираем данные из сессии
            unset($_SESSION['login']);
            unset($_SESSION['email']);
            unset($_SESSION['password']);

            // Останавливаем работу скрипта
            die("<p>Несоответствие данных авторизации сессии. Работа остановлена</p>");
        }

        return $bIsAuth;
    }

    /**
     * Возвращает объект пользователя, для которого в сессию записаны данные об авторизации
     * @return Core_ORM
     */
    protected function _getCurrentUser() : Core_ORM
    {
        try {
            // Если в сессии имеются данные об авторизованном пользователе
            if (isset($_SESSION['user_id']))
            {
                return Core_Entity::factory('User', $_SESSION['user_id']);
            }
            else
            {
                throw new Exception("<p>Ошибка " . __METHOD__ . ": данные об авторизованном пользователе утеряны или отсутствуют</p>");
            }
        }
        catch(Exception $e)
        {
            print $e->getMessage();

            die();
        }
    }

    /**
     * Конструктор класса
     * @param int $id = 0
     */
    public function __construct($id = NULL)
    {
        parent::__construct($id);
    }

    /**
     * Хэширует переданное значения пароля
     * @param string $password
     * @return string
     */
    public function preparePassword(string $password) : string
    {
        try {
            if (!empty($password))
            {
                // Получаем параметры для хэширования пароля
                $aConfig = Core_Config::instance()->get('core_password');

                // Если указан метод хэширования и он является функцией
                if (!empty($aConfig['method']) && is_callable($aConfig['method']))
                {
                    // Получаем имя функции для хэширования
                    $sMethod = $aConfig['method'];

                    // Удаляем из массива имя фнукции
                    unset($aConfig['method']);
                    
                    /**
                     * Для вызова функции хэширования будем использовать 
                     * встроенную в PHP функцию call_user_func_array
                     * Поэтому готовим массив для передачи данных
                     */
                    $aParams = ['password' => $password] + $aConfig;

                    // Хэшируем пароль
                    $sHash = call_user_func_array($sMethod, $aParams);
                }
                // Если не указан метод хэширования
                else
                {
                    throw new Exception("<p>Ошибка " . __METHOD__ . ": не передано имя функции для создания хэша</p>");
                }
            }
            else
            {
                throw new Exception("<p>Ошибка " . __METHOD__ . ": не передано значение пароля для создания хэша</p>");
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        // Возвращаем хэш пароля вызову
        return $sHash;
    }

    /**
     * Получает информацию об авторизованном пользователе
     * @return mixed self | NULL если пользователь не авторизован
     */
    public function getCurrent()
    {
        $return = NULL;
        
        /**
         * Информация о пользователе, если он авторизован, хранится в сессии
         * Поэтому нужно просто проверить, имеется ли там нужная информация
         * Если в сессии её нет, значит пользователь не авторизован
         */
        (!empty($_SESSION['user_id']))
                && $return = $this->_getCurrentUser();
        
        // Возвращаем результат вызову
        return $return;
    }

    /**
     * Устанавливает в сесии параметры пользователя, прошедшего авторизацию
     * @return object self
     */
    public function setCurrent()
    {
        $_SESSION['user_id'] = $this->getPrimaryKey();

        return $this;
    }

    /**
     * Завершает сеанс пользователя в системе
     * @return object self
     */
    public function unsetCurrent()
    {
        // Уничтожение данных о пользователе в сессии
        unset($_SESSION['user_id']);

        header("Refresh:0;"); die();

        return NULL;
    }

    /**
     * Ищет в БД запись по переданному значению полей login или email
     * @param string $value
     * @return mixed Core_ORM|NULL
     */
    public function getByLoginOrEmail(string $value) : User_Model|NULL
    {
        // Определяем тип авторизации: по логину или адресу электропочты
        $sType = NULL;
        $sType = match($this->validateEmail($value)) {
                    TRUE => 'email',
                    FALSE => 'login'
        };

        $oUser = Core_Entity::factory('User');
        $oUser->queryBuilder()
            ->clearSelect()
            ->select($this->_primaryKey)
            ->where($sType, '=', $value);

        // Ищем пользователя
        $aUsers = $oUser->findAll();
        
        // Возвращаем объект вызову
        return isset($aUsers[0]) ? $aUsers[0] : NULL;
    }

    /**
     * Проверяет пароль пользователя, совпадает ли он с хранимым в БД
     * @param string $password пароль пользователя
     * @return boolean TRUE|FALSE
     */
    public function checkPassword(string $password) : bool
    {
        $return = FALSE;

        try {
            // Если у объекта установлено значение первичного ключа
            if ($this->getPrimaryKey())
            {
                // Создаем объект для запроса SELECT 
                $oCore_Querybuilder_Select = Core_Querybuilder::select('password');
                $oCore_Querybuilder_Select->from($this->getTableName())
                                        ->where($this->_primaryKey, '=', $this->getPrimaryKey())
                                        ->where('deleted', '=', 0);
                
                // Получаем данные
                $aResult = $oCore_Querybuilder_Select->query()->asAssoc()->result()->fetch();

                // Если данные получены
                if (Core_Database::instance()->getRowCount())
                {
                    $sHash = $aResult['password'];
                }
                else
                {
                    $return = FALSE;
                }
            }
            else
            {
                throw new Exception("<p>Ошибка: " . __METHOD__ . ": невозможно проверить пароль для пустого объекта модели пользователя</p>");
            }
        }
        catch (Exception $e)
        {
            print $e->getMessage();

            die();
        }

        /**
         * Согласно документации к PHP, мы для подготовки пароля пользователя к сохранению в БД
         * мы использовали функцию password_hash() https://www.php.net/manual/ru/function.password-hash
         * Теперь для проверки пароля для авторизации нам нужно использовать функцию password_verify()
         * https://www.php.net/manual/ru/function.password-verify.php
         */
        if (password_verify($password, $sHash))
        {
            $return = TRUE;
        }

        return $return;
    }

    /**
     * Проверяет правильность адреса электронной почты
     * @param string $email
     * @return TRUE | FALSE
     */
    public function validateEmail(string $email) : bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Проверяет уникальность логина в системе
     * @param string $value
     * @param string $field
     * @return TRUE | FALSE
     */
    public function isValueExist($value, $field) : bool
    {
        // Подключаемся к СУБД
        $oCore_Querybuilder_Select = Core_Querybuilder::select();
        $oCore_Querybuilder_Select
            ->from('users')
            ->where($field, '=', $value)
            ->where('deleted', '=', 0);

        // Выполняем запрос
        try {
            $stmt = $oCore_Querybuilder_Select->query()->result();
        }
        catch (PDOException $e)
        {
            die("<p><strong>При выполнении запроса произошла ошибка:</strong> {$e->getMessage()}</p>");
        }
        
        // Если логин уникален, в результате запроса не должно быть строк
        return $stmt->rowCount() !== 0;
    }

    /**
     * Меняет статус активации учетной записи пользователя
     * @return object self
     */
    public function changeActive()
    {
        // Пробуем изменить статус
        try {
            // Если объект заполнен данными
            if (!is_null($this->getPrimaryKey()))
            {
                // Переключаем значение активации учетной записи
                $this->active = $this->active ? 0 : 1;
                $this->save();
            }
            else
            {
                throw new Exception("<p>Ошибка: " . __METHOD__ . ": управлять активацией учетных записей можно только для существующих пользователей</p>");
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
     * Обрабатывает данные, которыми пользователь заполнил форму
     * @param array $post
     */
    public function processUserData(array $post)
    {
        $aReturn = [
            'success' => FALSE,
            'message' => "При обработке формы произошла ошибка",
            'data' => [],
            'type' => static::ACTION_SIGNIN
        ];
        
        // Если не передан массив на обработку, останавливаем работу сценария
        if (empty($post))
        {
            die("<p>Для обработки пользовательских данных формы должен быть передан массив</p>");
        }
        
        // Если в массиве отсутствуют данные о типе заполненной формы, останавливаем работу сценария
        if (empty($post[static::ACTION_SIGNIN]) && empty($post[static::ACTION_SIGNUP]))
        {
            die("<p>Метод <code>User_Model::processUserData()</code> должен вызываться только для обработки данных из форм авторизации или регистрации</p>");
        }

        // Флаг регистрации нового пользователя
        $bRegistrationUser = !empty($post[static::ACTION_SIGNUP]);

        // Логин и пароль у нас должны иметься в обоих случаях
        $sLogin = strval(htmlspecialchars(trim($post['login'])));
        $sPassword = strval(htmlspecialchars(trim($post['password'])));

        // А вот электропочта и повтор пароля будут только в случае регистрации
        if ($bRegistrationUser)
        {
            $aReturn['type'] = static::ACTION_SIGNUP;

            $sEmail = strval(htmlspecialchars(trim($_POST['email'])));
            $sPassword2 = strval(htmlspecialchars(trim($_POST['password2'])));

            // Проверяем данные на ошибки
            if ($this->validateEmail($sEmail))
            {
                // Логин и пароли не могут быть пустыми
                if (empty($sLogin))
                {
                    $aReturn['message'] = "Поле логина не было заполнено";
                    $aReturn['data'] = $post;
                }
                elseif (empty($sPassword))
                {
                    $aReturn['message'] = "Поле пароля не было заполнено";
                    $aReturn['data'] = $post;
                }
                // Пароли должны быть идентичны
                elseif ($sPassword !== $sPassword2)
                {
                    $aReturn['message'] = "Введенные пароли не совпадают";
                    $aReturn['data'] = $post;
                }
                // Если логин не уникален
                elseif ($this->isValueExist($sLogin, 'login'))
                {
                    $aReturn['message'] = "Указанный вами логин ранее уже был зарегистрирован";
                    $aReturn['data'] = $post;
                }
                // Если email не уникален
                elseif ($this->isValueExist($sEmail, 'email'))
                {
                    $aReturn['message'] = "Указанный вами email ранее уже был зарегистрирован";
                    $aReturn['data'] = $post;
                }
                // Если все проверки прошли успешно, можно регистрировать пользователя
                else
                {
                    $this->login = $sLogin;
                    // Пароль теперь нет необходимости отдельно хэшировать перед сохранением
                    // Это происходит автоматически с помощью метода __set()
                    $this->password = $sPassword;
                    $this->email = $sEmail;
                    $this->save();

                    if (Core_Database::instance()->lastInsertId())
                    {
                        $aReturn['success'] = TRUE;
                        $aReturn['message'] = "Пользователь с логином <strong>{$sLogin}</strong> и email <strong>{$sEmail}</strong> успешно зарегистрирован.";
                        $aReturn['data']['user_id'] = Core_Database::instance()->lastInsertId();
                    }
                }
            }
            else
            {
                $aReturn['message'] = "Указанное значение адреса электропочты не соответствует формату";
                $aReturn['data'] = $post;
            }
        }
        // Если пользователь авторизуется
        else
        {
            // Если не передан пароль
            if (empty($sPassword))
            {
                $aReturn['message'] = "Поле пароля не было заполнено";
                $aReturn['data'] = $post;
            }
            else 
            {
                // Ищем соответствие переданной информации в БД
                $oUserTarget = $this->getByLoginOrEmail($sLogin);

                // Если была найдена запись
                if (!is_null($oUserTarget))
                {
                    // Проверяем пароль пользователя
                    // Если хэш пароля совпадает
                    if ($oUserTarget->checkPassword($sPassword))
                    {
                        // Авторизуем пользователя
                        // Устанавливаем значение перв
                        $oUserTarget->setCurrent();
                        
                        $aReturn['success'] = TRUE;
                        $aReturn['message'] = "Вы успешно авторизовались на сайте";
                        $aReturn['data'] = $post;
                        $aReturn['data']['user_id'] = $oUserTarget->id;
                    }
                    else
                    {
                        $aReturn['message'] = "Для учетной записи <strong>{$sLogin}</strong> указан неверный пароль";
                        $aReturn['data'] = $post;
                    }
                }
            }
        }

        return $aReturn;
    }

    /**
     * Устанавливает значения для необъявленных свойств класса
     * Если устанавливается пароль, перед вызовом логики родительского метода
     * __set() значение пароля будет хэшировано. Для остальных свойств 
     * поведение магического метода будет обычным
     * @param string $property
     * @param string $value
     */
    public function __set($property, $value)
    {
        // Если устанавливается пароль пользователя
        if ($property == 'password')
        {
            $value = $this->preparePassword($value);
        }

        // Вызываем родительский метод __set()
        parent::__set($property, $value);
    }
}
?>