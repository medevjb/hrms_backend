<?php

test('an unknown api route gets a not found code in the shared envelope', function () {
    $response = $this->getJson('/api/v1/this-route-does-not-exist');

    $response->assertStatus(404);
    $response->assertJson(['code' => 'NOT_FOUND']);
});

test('a validation failure keeps its specific code rather than the generic fallback', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422);
    $response->assertJson(['code' => 'VALIDATION_FAILED']);
});
