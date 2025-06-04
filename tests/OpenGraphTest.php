<?php

use Backstage\OgImage\Laravel\Facades\OgImage;

it('can generate an image using params', function (): void {
    $image = OgImage::createImageFromParams([
        'title' => 'title',
        'description' => 'description',
    ]);

    expect($image)->toBeString();
});
