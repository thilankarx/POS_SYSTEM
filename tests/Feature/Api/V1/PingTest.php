<?php

declare(strict_types=1);

it('answers the connectivity probe without auth and without a body', function () {
    $this->getJson('/api/v1/ping')
        ->assertNoContent();
});

it('answers a HEAD probe too', function () {
    $this->call('HEAD', '/api/v1/ping')
        ->assertNoContent();
});
