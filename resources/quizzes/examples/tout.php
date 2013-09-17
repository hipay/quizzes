<?php

$aAdditions = require __DIR__ . '/additions.php';
$aMultiplications = require __DIR__ . '/multiplications.php';
$aJavascript = require __DIR__ . '/javascript.php';

return array(
    'meta' => array(
        'title' => 'Toutes les questions !',
        'time_limit' => 5*20,
        'max_nb_questions' => 0,
        'status' => 'available' // {'available', 'deactivated', 'hidden'}
    ),
    'questions' => array_merge(
        $aAdditions['questions'],
        $aMultiplications['questions'],
        $aJavascript['questions']
    )
);
