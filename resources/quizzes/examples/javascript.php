<?php

return array(
    'meta' => array(
        'title' => 'JavaScript',
        'time_limit' => 1*20,
        'max_nb_questions' => 0
    ),
    'questions' => array(
        array(
            'JavaScript',
            "Que retourne <code>a.b()</code> à la suite de ce code JavaScript ?
<pre class=\"js\">
var a = {
    b: function() {
        return this;
    }
};
</pre>",
            array(
                "<code>a</code>" => true,
                "<code>b</code>" => false,
                "<code>window</code> (ou <code>undefined</code> en mode strict)" => false,
                "Cela dépend du contexte." => false,
            )
        ),
    )
);
