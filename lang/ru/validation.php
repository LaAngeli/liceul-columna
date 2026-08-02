<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Сообщения валидации (RU)
    |--------------------------------------------------------------------------
    |
    | Laravel не поставляет русские переводы (в отличие от английских), поэтому
    | без этого файла все сообщения валидации в панели и в кабинете показывались
    | бы на румынском (запасной язык приложения). `:attribute` заменяется именем
    | поля из списка `attributes` ниже.
    |
    */

    'accepted' => 'Поле :attribute должно быть принято.',
    'accepted_if' => 'Поле :attribute должно быть принято, когда :other равно :value.',
    'active_url' => 'Поле :attribute содержит недействительный URL.',
    'after' => 'Поле :attribute должно содержать дату после :date.',
    'after_or_equal' => 'Поле :attribute должно содержать дату не раньше :date.',
    'alpha' => 'Поле :attribute может содержать только буквы.',
    'alpha_dash' => 'Поле :attribute может содержать только буквы, цифры, дефисы и подчёркивания.',
    'alpha_num' => 'Поле :attribute может содержать только буквы и цифры.',
    'array' => 'Поле :attribute должно быть списком.',
    'ascii' => 'Поле :attribute может содержать только однобайтовые буквенно-цифровые символы.',
    'before' => 'Поле :attribute должно содержать дату до :date.',
    'before_or_equal' => 'Поле :attribute должно содержать дату не позже :date.',
    // Понятное сообщение для проверок «дата в будущем» (оценки/пропуски/обоснования):
    // избавляет от вывода времени с секундами из `maxDate(now())`.
    'not_future_date' => 'Дата не может быть в будущем.',
    'between' => [
        'array' => 'Поле :attribute должно содержать от :min до :max элементов.',
        'file' => 'Размер файла в поле :attribute должен быть от :min до :max килобайт.',
        'numeric' => 'Поле :attribute должно быть между :min и :max.',
        'string' => 'Поле :attribute должно содержать от :min до :max символов.',
    ],
    'boolean' => 'Поле :attribute должно иметь значение «да» или «нет».',
    'can' => 'Поле :attribute содержит недопустимое значение.',
    'confirmed' => 'Подтверждение поля :attribute не совпадает.',
    'contains' => 'В поле :attribute отсутствует обязательное значение.',
    'current_password' => 'Неверный пароль.',
    'date' => 'Поле :attribute содержит недействительную дату.',
    'date_equals' => 'Поле :attribute должно содержать дату, равную :date.',
    'date_format' => 'Поле :attribute не соответствует формату :format.',
    'decimal' => 'Поле :attribute должно содержать :decimal знаков после запятой.',
    'declined' => 'Поле :attribute должно быть отклонено.',
    'declined_if' => 'Поле :attribute должно быть отклонено, когда :other равно :value.',
    'different' => 'Поля :attribute и :other должны различаться.',
    'digits' => 'Поле :attribute должно содержать :digits цифр.',
    'digits_between' => 'Поле :attribute должно содержать от :min до :max цифр.',
    'dimensions' => 'Поле :attribute содержит изображение с недопустимыми размерами.',
    'distinct' => 'Поле :attribute содержит повторяющееся значение.',
    'doesnt_end_with' => 'Поле :attribute не должно заканчиваться одним из: :values.',
    'doesnt_start_with' => 'Поле :attribute не должно начинаться с одного из: :values.',
    'email' => 'Поле :attribute должно содержать действительный адрес эл. почты.',
    'ends_with' => 'Поле :attribute должно заканчиваться одним из: :values.',
    'enum' => 'Выбранное значение для :attribute недопустимо.',
    'exists' => 'Выбранное значение для :attribute недопустимо.',
    'extensions' => 'Поле :attribute должно иметь одно из расширений: :values.',
    'file' => 'Поле :attribute должно содержать файл.',
    'filled' => 'Поле :attribute обязательно для заполнения.',
    'gt' => [
        'array' => 'Поле :attribute должно содержать более :value элементов.',
        'file' => 'Размер файла в поле :attribute должен быть больше :value килобайт.',
        'numeric' => 'Поле :attribute должно быть больше :value.',
        'string' => 'Поле :attribute должно содержать более :value символов.',
    ],
    'gte' => [
        'array' => 'Поле :attribute должно содержать :value элементов или больше.',
        'file' => 'Размер файла в поле :attribute должен быть не меньше :value килобайт.',
        'numeric' => 'Поле :attribute должно быть не меньше :value.',
        'string' => 'Поле :attribute должно содержать не менее :value символов.',
    ],
    'hex_color' => 'Поле :attribute должно содержать действительный шестнадцатеричный цвет.',
    'image' => 'Поле :attribute должно содержать изображение.',
    'in' => 'Выбранное значение для :attribute недопустимо.',
    'in_array' => 'Поле :attribute должно присутствовать в :other.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'ip' => 'Поле :attribute должно содержать действительный IP-адрес.',
    'ipv4' => 'Поле :attribute должно содержать действительный IPv4-адрес.',
    'ipv6' => 'Поле :attribute должно содержать действительный IPv6-адрес.',
    'json' => 'Поле :attribute должно содержать корректную JSON-строку.',
    'lowercase' => 'Поле :attribute должно содержать только строчные буквы.',
    'lt' => [
        'array' => 'Поле :attribute должно содержать менее :value элементов.',
        'file' => 'Размер файла в поле :attribute должен быть меньше :value килобайт.',
        'numeric' => 'Поле :attribute должно быть меньше :value.',
        'string' => 'Поле :attribute должно содержать менее :value символов.',
    ],
    'lte' => [
        'array' => 'Поле :attribute должно содержать не более :value элементов.',
        'file' => 'Размер файла в поле :attribute должен быть не больше :value килобайт.',
        'numeric' => 'Поле :attribute должно быть не больше :value.',
        'string' => 'Поле :attribute должно содержать не более :value символов.',
    ],
    'mac_address' => 'Поле :attribute должно содержать действительный MAC-адрес.',
    'max' => [
        'array' => 'Поле :attribute не может содержать более :max элементов.',
        'file' => 'Размер файла в поле :attribute не может превышать :max килобайт.',
        'numeric' => 'Поле :attribute не может быть больше :max.',
        'string' => 'Поле :attribute не может содержать более :max символов.',
    ],
    'max_digits' => 'Поле :attribute не может содержать более :max цифр.',
    'mimes' => 'Поле :attribute должно содержать файл типа: :values.',
    'mimetypes' => 'Поле :attribute должно содержать файл типа: :values.',
    'min' => [
        'array' => 'Поле :attribute должно содержать не менее :min элементов.',
        'file' => 'Размер файла в поле :attribute должен быть не менее :min килобайт.',
        'numeric' => 'Поле :attribute должно быть не менее :min.',
        'string' => 'Поле :attribute должно содержать не менее :min символов.',
    ],
    'min_digits' => 'Поле :attribute должно содержать не менее :min цифр.',
    'missing' => 'Поле :attribute должно отсутствовать.',
    'missing_if' => 'Поле :attribute должно отсутствовать, когда :other равно :value.',
    'missing_unless' => 'Поле :attribute должно отсутствовать, если :other не равно :value.',
    'missing_with' => 'Поле :attribute должно отсутствовать, когда указано :values.',
    'missing_with_all' => 'Поле :attribute должно отсутствовать, когда указаны :values.',
    'multiple_of' => 'Поле :attribute должно быть кратным :value.',
    'not_in' => 'Выбранное значение для :attribute недопустимо.',
    'not_regex' => 'Поле :attribute имеет недопустимый формат.',
    'numeric' => 'Поле :attribute должно быть числом.',
    'password' => [
        'letters' => 'Поле :attribute должно содержать хотя бы одну букву.',
        'mixed' => 'Поле :attribute должно содержать хотя бы одну заглавную и одну строчную букву.',
        'numbers' => 'Поле :attribute должно содержать хотя бы одну цифру.',
        'symbols' => 'Поле :attribute должно содержать хотя бы один символ.',
        'uncompromised' => 'Этот пароль встречался в утечке данных. Пожалуйста, выберите другой.',
    ],
    'present' => 'Поле :attribute должно присутствовать.',
    'present_if' => 'Поле :attribute должно присутствовать, когда :other равно :value.',
    'present_unless' => 'Поле :attribute должно присутствовать, если :other не равно :value.',
    'present_with' => 'Поле :attribute должно присутствовать, когда указано :values.',
    'present_with_all' => 'Поле :attribute должно присутствовать, когда указаны :values.',
    'prohibited' => 'Поле :attribute запрещено.',
    'prohibited_if' => 'Поле :attribute запрещено, когда :other равно :value.',
    'prohibited_unless' => 'Поле :attribute запрещено, если :other не входит в :values.',
    'prohibits' => 'Поле :attribute запрещает присутствие поля :other.',
    'regex' => 'Поле :attribute имеет недопустимый формат.',
    'required' => 'Поле :attribute обязательно для заполнения.',
    'required_array_keys' => 'Поле :attribute должно содержать записи для: :values.',
    'required_if' => 'Поле :attribute обязательно, когда :other равно :value.',
    'required_if_accepted' => 'Поле :attribute обязательно, когда :other принято.',
    'required_if_declined' => 'Поле :attribute обязательно, когда :other отклонено.',
    'required_unless' => 'Поле :attribute обязательно, если :other не входит в :values.',
    'required_with' => 'Поле :attribute обязательно, когда указано :values.',
    'required_with_all' => 'Поле :attribute обязательно, когда указаны :values.',
    'required_without' => 'Поле :attribute обязательно, когда не указано :values.',
    'required_without_all' => 'Поле :attribute обязательно, когда не указано ни одно из :values.',
    'same' => 'Поля :attribute и :other должны совпадать.',
    'size' => [
        'array' => 'Поле :attribute должно содержать :size элементов.',
        'file' => 'Размер файла в поле :attribute должен быть :size килобайт.',
        'numeric' => 'Поле :attribute должно быть равно :size.',
        'string' => 'Поле :attribute должно содержать :size символов.',
    ],
    'starts_with' => 'Поле :attribute должно начинаться с одного из: :values.',
    'string' => 'Поле :attribute должно быть строкой.',
    'timezone' => 'Поле :attribute должно содержать действительный часовой пояс.',
    'unique' => 'Такое значение поля :attribute уже используется.',
    'uploaded' => 'Не удалось загрузить файл в поле :attribute.',
    'uppercase' => 'Поле :attribute должно содержать только заглавные буквы.',
    'url' => 'Поле :attribute должно содержать действительный URL.',
    'ulid' => 'Поле :attribute должно содержать действительный ULID.',
    'uuid' => 'Поле :attribute должно содержать действительный UUID.',

    /*
    |--------------------------------------------------------------------------
    | Персональные сообщения для отдельных полей
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'email' => [
            'unique' => 'Этот адрес эл. почты уже используется другой учётной записью.',
        ],
        'username' => [
            'unique' => 'Это имя пользователя уже занято.',
        ],
        'password' => [
            'confirmed' => 'Подтверждение пароля не совпадает с паролем.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Понятные названия полей (:attribute)
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'имя',
        'username' => 'имя пользователя',
        'email' => 'адрес эл. почты',
        'password' => 'пароль',
        'password_confirmation' => 'подтверждение пароля',
        'current_password' => 'текущий пароль',
        'remember' => 'запомнить меня',
        'first_name' => 'имя',
        'last_name' => 'фамилия',
        'value' => 'оценка',
        'calificativ' => 'словесная оценка',
        'student_id' => 'ученик',
        'subject_id' => 'предмет',
        'school_class_id' => 'класс',
        'term_id' => 'семестр',
        'grade_level' => 'класс (ступень)',
        'occurred_on' => 'дата',
        'graded_on' => 'дата',
        'assigned_on' => 'дата',
        'period_start' => 'дата начала',
        'period_end' => 'дата окончания',
        'type' => 'тип заявки',
    ],

];
