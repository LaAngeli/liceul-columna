<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mesaje de validare (RO)
    |--------------------------------------------------------------------------
    |
    | Folosite atât de formularele Inertia/Fortify, cât și de panoul Filament
    | (toate trec prin validatorul Laravel). `:attribute` e înlocuit cu numele
    | câmpului din lista `attributes` de mai jos.
    |
    */

    'accepted' => 'Câmpul :attribute trebuie acceptat.',
    'accepted_if' => 'Câmpul :attribute trebuie acceptat când :other este :value.',
    'active_url' => 'Câmpul :attribute nu este un URL valid.',
    'after' => 'Câmpul :attribute trebuie să fie o dată după :date.',
    'after_or_equal' => 'Câmpul :attribute trebuie să fie o dată după sau egală cu :date.',
    'alpha' => 'Câmpul :attribute poate conține doar litere.',
    'alpha_dash' => 'Câmpul :attribute poate conține doar litere, cifre, liniuțe și underscore.',
    'alpha_num' => 'Câmpul :attribute poate conține doar litere și cifre.',
    'array' => 'Câmpul :attribute trebuie să fie o listă.',
    'ascii' => 'Câmpul :attribute poate conține doar caractere și simboluri alfanumerice pe un octet.',
    'before' => 'Câmpul :attribute trebuie să fie o dată înainte de :date.',
    'before_or_equal' => 'Câmpul :attribute trebuie să fie o dată înainte de sau egală cu :date.',
    'between' => [
        'array' => 'Câmpul :attribute trebuie să aibă între :min și :max elemente.',
        'file' => 'Câmpul :attribute trebuie să aibă între :min și :max kiloocteți.',
        'numeric' => 'Câmpul :attribute trebuie să fie între :min și :max.',
        'string' => 'Câmpul :attribute trebuie să aibă între :min și :max caractere.',
    ],
    'boolean' => 'Câmpul :attribute trebuie să fie adevărat sau fals.',
    'can' => 'Câmpul :attribute conține o valoare neautorizată.',
    'confirmed' => 'Confirmarea câmpului :attribute nu se potrivește.',
    'contains' => 'Câmpului :attribute îi lipsește o valoare obligatorie.',
    'current_password' => 'Parola este incorectă.',
    'date' => 'Câmpul :attribute nu este o dată validă.',
    'date_equals' => 'Câmpul :attribute trebuie să fie o dată egală cu :date.',
    'date_format' => 'Câmpul :attribute nu corespunde formatului :format.',
    'decimal' => 'Câmpul :attribute trebuie să aibă :decimal zecimale.',
    'declined' => 'Câmpul :attribute trebuie respins.',
    'declined_if' => 'Câmpul :attribute trebuie respins când :other este :value.',
    'different' => 'Câmpul :attribute și :other trebuie să fie diferite.',
    'digits' => 'Câmpul :attribute trebuie să aibă :digits cifre.',
    'digits_between' => 'Câmpul :attribute trebuie să aibă între :min și :max cifre.',
    'dimensions' => 'Câmpul :attribute are dimensiuni de imagine nevalide.',
    'distinct' => 'Câmpul :attribute are o valoare duplicată.',
    'doesnt_end_with' => 'Câmpul :attribute nu trebuie să se termine cu una dintre: :values.',
    'doesnt_start_with' => 'Câmpul :attribute nu trebuie să înceapă cu una dintre: :values.',
    'email' => 'Câmpul :attribute trebuie să fie o adresă de email validă.',
    'ends_with' => 'Câmpul :attribute trebuie să se termine cu una dintre: :values.',
    'enum' => 'Valoarea selectată pentru :attribute nu este validă.',
    'exists' => 'Valoarea selectată pentru :attribute nu este validă.',
    'extensions' => 'Câmpul :attribute trebuie să aibă una dintre extensiile: :values.',
    'file' => 'Câmpul :attribute trebuie să fie un fișier.',
    'filled' => 'Câmpul :attribute trebuie completat.',
    'gt' => [
        'array' => 'Câmpul :attribute trebuie să aibă mai mult de :value elemente.',
        'file' => 'Câmpul :attribute trebuie să fie mai mare de :value kiloocteți.',
        'numeric' => 'Câmpul :attribute trebuie să fie mai mare decât :value.',
        'string' => 'Câmpul :attribute trebuie să aibă mai mult de :value caractere.',
    ],
    'gte' => [
        'array' => 'Câmpul :attribute trebuie să aibă :value elemente sau mai multe.',
        'file' => 'Câmpul :attribute trebuie să fie cel puțin :value kiloocteți.',
        'numeric' => 'Câmpul :attribute trebuie să fie cel puțin :value.',
        'string' => 'Câmpul :attribute trebuie să aibă cel puțin :value caractere.',
    ],
    'hex_color' => 'Câmpul :attribute trebuie să fie o culoare hexazecimală validă.',
    'image' => 'Câmpul :attribute trebuie să fie o imagine.',
    'in' => 'Valoarea selectată pentru :attribute nu este validă.',
    'in_array' => 'Câmpul :attribute trebuie să existe în :other.',
    'integer' => 'Câmpul :attribute trebuie să fie un număr întreg.',
    'ip' => 'Câmpul :attribute trebuie să fie o adresă IP validă.',
    'ipv4' => 'Câmpul :attribute trebuie să fie o adresă IPv4 validă.',
    'ipv6' => 'Câmpul :attribute trebuie să fie o adresă IPv6 validă.',
    'json' => 'Câmpul :attribute trebuie să fie un text JSON valid.',
    'lowercase' => 'Câmpul :attribute trebuie să conțină doar litere mici.',
    'lt' => [
        'array' => 'Câmpul :attribute trebuie să aibă mai puțin de :value elemente.',
        'file' => 'Câmpul :attribute trebuie să fie mai mic de :value kiloocteți.',
        'numeric' => 'Câmpul :attribute trebuie să fie mai mic decât :value.',
        'string' => 'Câmpul :attribute trebuie să aibă mai puțin de :value caractere.',
    ],
    'lte' => [
        'array' => 'Câmpul :attribute trebuie să aibă cel mult :value elemente.',
        'file' => 'Câmpul :attribute trebuie să fie cel mult :value kiloocteți.',
        'numeric' => 'Câmpul :attribute trebuie să fie cel mult :value.',
        'string' => 'Câmpul :attribute trebuie să aibă cel mult :value caractere.',
    ],
    'mac_address' => 'Câmpul :attribute trebuie să fie o adresă MAC validă.',
    'max' => [
        'array' => 'Câmpul :attribute nu poate avea mai mult de :max elemente.',
        'file' => 'Câmpul :attribute nu poate fi mai mare de :max kiloocteți.',
        'numeric' => 'Câmpul :attribute nu poate fi mai mare decât :max.',
        'string' => 'Câmpul :attribute nu poate avea mai mult de :max caractere.',
    ],
    'max_digits' => 'Câmpul :attribute nu poate avea mai mult de :max cifre.',
    'mimes' => 'Câmpul :attribute trebuie să fie un fișier de tip: :values.',
    'mimetypes' => 'Câmpul :attribute trebuie să fie un fișier de tip: :values.',
    'min' => [
        'array' => 'Câmpul :attribute trebuie să aibă cel puțin :min elemente.',
        'file' => 'Câmpul :attribute trebuie să aibă cel puțin :min kiloocteți.',
        'numeric' => 'Câmpul :attribute trebuie să fie cel puțin :min.',
        'string' => 'Câmpul :attribute trebuie să aibă cel puțin :min caractere.',
    ],
    'min_digits' => 'Câmpul :attribute trebuie să aibă cel puțin :min cifre.',
    'missing' => 'Câmpul :attribute trebuie să lipsească.',
    'missing_if' => 'Câmpul :attribute trebuie să lipsească când :other este :value.',
    'missing_unless' => 'Câmpul :attribute trebuie să lipsească dacă :other nu este :value.',
    'missing_with' => 'Câmpul :attribute trebuie să lipsească când :values este prezent.',
    'missing_with_all' => 'Câmpul :attribute trebuie să lipsească când :values sunt prezente.',
    'multiple_of' => 'Câmpul :attribute trebuie să fie multiplu de :value.',
    'not_in' => 'Valoarea selectată pentru :attribute nu este validă.',
    'not_regex' => 'Formatul câmpului :attribute nu este valid.',
    'numeric' => 'Câmpul :attribute trebuie să fie un număr.',
    'password' => [
        'letters' => 'Câmpul :attribute trebuie să conțină cel puțin o literă.',
        'mixed' => 'Câmpul :attribute trebuie să conțină cel puțin o literă mare și una mică.',
        'numbers' => 'Câmpul :attribute trebuie să conțină cel puțin o cifră.',
        'symbols' => 'Câmpul :attribute trebuie să conțină cel puțin un simbol.',
        'uncompromised' => 'Această parolă a apărut într-o scurgere de date. Te rugăm să alegi alta.',
    ],
    'present' => 'Câmpul :attribute trebuie să fie prezent.',
    'present_if' => 'Câmpul :attribute trebuie să fie prezent când :other este :value.',
    'present_unless' => 'Câmpul :attribute trebuie să fie prezent dacă :other nu este :value.',
    'present_with' => 'Câmpul :attribute trebuie să fie prezent când :values este prezent.',
    'present_with_all' => 'Câmpul :attribute trebuie să fie prezent când :values sunt prezente.',
    'prohibited' => 'Câmpul :attribute este interzis.',
    'prohibited_if' => 'Câmpul :attribute este interzis când :other este :value.',
    'prohibited_unless' => 'Câmpul :attribute este interzis dacă :other nu este în :values.',
    'prohibits' => 'Câmpul :attribute interzice prezența câmpului :other.',
    'regex' => 'Formatul câmpului :attribute nu este valid.',
    'required' => 'Câmpul :attribute este obligatoriu.',
    'required_array_keys' => 'Câmpul :attribute trebuie să conțină intrări pentru: :values.',
    'required_if' => 'Câmpul :attribute este obligatoriu când :other este :value.',
    'required_if_accepted' => 'Câmpul :attribute este obligatoriu când :other este acceptat.',
    'required_if_declined' => 'Câmpul :attribute este obligatoriu când :other este respins.',
    'required_unless' => 'Câmpul :attribute este obligatoriu dacă :other nu este în :values.',
    'required_with' => 'Câmpul :attribute este obligatoriu când :values este prezent.',
    'required_with_all' => 'Câmpul :attribute este obligatoriu când :values sunt prezente.',
    'required_without' => 'Câmpul :attribute este obligatoriu când :values nu este prezent.',
    'required_without_all' => 'Câmpul :attribute este obligatoriu când niciunul dintre :values nu este prezent.',
    'same' => 'Câmpul :attribute și :other trebuie să coincidă.',
    'size' => [
        'array' => 'Câmpul :attribute trebuie să conțină :size elemente.',
        'file' => 'Câmpul :attribute trebuie să aibă :size kiloocteți.',
        'numeric' => 'Câmpul :attribute trebuie să fie :size.',
        'string' => 'Câmpul :attribute trebuie să aibă :size caractere.',
    ],
    'starts_with' => 'Câmpul :attribute trebuie să înceapă cu una dintre: :values.',
    'string' => 'Câmpul :attribute trebuie să fie un text.',
    'timezone' => 'Câmpul :attribute trebuie să fie un fus orar valid.',
    'unique' => 'Această valoare pentru :attribute este deja folosită.',
    'uploaded' => 'Încărcarea câmpului :attribute a eșuat.',
    'uppercase' => 'Câmpul :attribute trebuie să conțină doar litere mari.',
    'url' => 'Câmpul :attribute trebuie să fie un URL valid.',
    'ulid' => 'Câmpul :attribute trebuie să fie un ULID valid.',
    'uuid' => 'Câmpul :attribute trebuie să fie un UUID valid.',

    /*
    |--------------------------------------------------------------------------
    | Mesaje personalizate per câmp
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'email' => [
            'unique' => 'Această adresă de email este deja folosită de alt cont.',
        ],
        'username' => [
            'unique' => 'Acest nume de utilizator este deja folosit.',
        ],
        'password' => [
            'confirmed' => 'Confirmarea parolei nu coincide cu parola.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Denumiri prietenoase pentru câmpuri (:attribute)
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'numele',
        'username' => 'numele de utilizator',
        'email' => 'adresa de email',
        'password' => 'parola',
        'password_confirmation' => 'confirmarea parolei',
        'current_password' => 'parola actuală',
        'remember' => 'ține-mă minte',
        'first_name' => 'prenumele',
        'last_name' => 'numele de familie',
        'value' => 'nota',
        'calificativ' => 'calificativul',
        'student_id' => 'elevul',
        'subject_id' => 'disciplina',
        'school_class_id' => 'clasa',
        'term_id' => 'semestrul',
        'grade_level' => 'clasa (treapta)',
        'occurred_on' => 'data',
        'graded_on' => 'data',
        'assigned_on' => 'data',
    ],

];
