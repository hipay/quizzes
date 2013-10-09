<?php

return array(
    'meta' => array(
        'title' => 'Multiplications',
        'time_limit' => 2*20,
        'max_nb_questions' => 0,
        'status' => 'available' // {'available', 'deactivated', 'hidden'}
    ),
    'questions' => array(
        array(
            'Multiplications',
            "Combien font <code>3*7</code> ?",
            array(
                "<code>21</code>" => true,
                "<code>20</code>" => false,
                "<code>42/2</code>" => true,
                "aucune des autres réponses" => false,
            )
        ),
        array(
            'Multiplications',
            "Combien font <code>2*0</code> ?",
            array(
                "<code>0</code>" => true,
                "<code>2</code>" => false,
                "<code>1-1</code>" => true,
            )
        ),
    )
);
