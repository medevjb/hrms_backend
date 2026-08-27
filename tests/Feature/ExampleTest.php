<?php

test('the root path redirects to the system console', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/system');
});
