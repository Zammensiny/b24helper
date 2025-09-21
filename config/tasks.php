<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Secret for showing hidden tasks
    |--------------------------------------------------------------------------
    |
    | Used to allow certain users to see hidden tasks via the search input.
    |
    */
    'secret' => env('TASK_SECRET', 'default_secret'),
];
