<?php
/**
 * Параметры хэширования паролей подобраны в соответствии с рекомендациями на странице документации
 * https://www.php.net/manual/ru/function.password-hash
 */
return [
    'method' => 'password_hash',
    'algo' => PASSWORD_BCRYPT,
    'options' => [
        'cost' => 10
    ]
];
?>