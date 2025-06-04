<?php

return [
    'extension' => 'jpg', // jpg, png, webp
    'quality' => 100,
    'width' => 1200,
    'height' => 630,

    'chrome' => [
        'binary' => 'chrome',
        'flags' => [],
    ],

    // The location to save cached versions of OG images
    'storage' => [
        'disk' => 'public',
        'path' => 'og-images',
    ],

    'metatags' => [
        'og:title' => 'title',
        'og:description' => 'description',
        'og:type' => 'type',
        'og:url' => 'url',
    ],
];
