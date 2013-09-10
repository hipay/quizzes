<?php

return array(
    'meta' => array(
        'title' => 'Additions',
        'time_limit' => 2*20,
        'max_nb_questions' => 0
    ),
    'questions' => array(
        array(
            'Additions',
            "Combien font <code>0+0</code> ?",
            array(
                "<code>0</code>" => true,
                "<code>1</code>" => false,
                "<code>1-1</code>" => true,
                "aucune des autres réponses" => false,
            )
        ),
        array(
            'Additions',
            "Combien font <code>2+3</code> ?",
            array(
                "<code>3</code>" => false,
                "<code>4</code>" => false,
                "<code>5</code>" => true,
            )
        ),
    )
);
