<?php

it('requires login before displaying the dashboard', function () {
    $this->get('/')->assertRedirect('/login');
});
