<?php

it('responds at the root with the API pointer', function () {
    $this->getJson('/')
        ->assertOk()
        ->assertJson(['api' => '/api/v1']);
});
