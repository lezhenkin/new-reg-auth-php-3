--
    -- Структура таблицы `users`
    --

    CREATE TABLE `users` (
    `id` int(10) UNSIGNED NOT NULL,
    `login` char(16) NOT NULL,
    `email` char(32) NOT NULL,
    `password` char(255) NOT NULL,
    `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
    `active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
    `deleted` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

    --
    -- Индексы сохранённых таблиц
    --

    --
    -- Индексы таблицы `users`
    --
    ALTER TABLE `users`
    ADD PRIMARY KEY (`id`),
    ADD KEY `active` (`active`),
    ADD KEY `deleted` (`deleted`);

    --
    -- AUTO_INCREMENT для сохранённых таблиц
    --

    --
    -- AUTO_INCREMENT для таблицы `users`
    --
    ALTER TABLE `users`
    MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;