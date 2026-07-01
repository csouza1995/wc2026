<?php

test('returns a successful response', function () {
    $response = $this->get('/jogos');

    $response->assertOk();
});
