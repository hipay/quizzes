<?php

$aAll = require __DIR__ . '/tout.php';

return array(
    'meta' => array(
        'title' => 'Un petit peu de tout…',
        'time_limit' => 2*20,
        'max_nb_questions' => 2
    ),
    'questions' => $aAll['questions']
);
