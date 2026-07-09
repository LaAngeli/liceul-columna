<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Atașamente la mesaje (poșta cabinetului)
    |--------------------------------------------------------------------------
    | Limite pentru fișierele/imaginile atașate unui mesaj. Extensiile sunt
    | deliberat FĂRĂ svg/html — acele tipuri s-ar putea executa la servire
    | inline (XSS); imaginile raster, PDF, documentele Office și textul sunt sigure.
    */

    'attachments' => [
        'max_files' => 5,
        'max_file_kb' => 8192, // 8 MB per fișier
        'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'],
    ],

];
