<?php

it('reports ok when the database and cache respond', function () {
    $this->getJson(route('health'))
        ->assertOk()
        ->assertJson(['status' => 'ok', 'checks' => ['database' => true, 'cache' => true]]);
});

it('sends security headers on web responses', function () {
    $this->get(route('login'))
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
