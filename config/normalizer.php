<?php

return [
    // When true, empty strings will be normalized to null
    'treat_empty_string_as_null' => true,

    // When true, strings that contain only whitespace will be treated as empty
    'treat_whitespace_as_empty' => true,

    // Controls how values are compared against the list below
    // - 'compressed': compare after removing all non-alphanumeric characters and lowercasing
    // - 'exact': compare exactly after trimming and lowercasing
    'na_match_mode' => 'compressed', // 'compressed' | 'exact'

    // A list of values that should be normalized to null when matched
    // Case-insensitive. Examples below cover common N/A variants.
    'na_values' => [
        'na',
        'n/a',
        'n a',
        'n-a',
        'n.a',
        'none',
        'null',
        '-',
    ],
];
