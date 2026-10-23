<?php

it('runs the install command and publishes assets', function () {
    $this->artisan('bpmn:install')
        ->expectsOutputToContain('Publishing configuration...')
        ->expectsOutputToContain('Publishing frontend assets...')
        ->expectsOutputToContain('Saccharine BPMN installed successfully!')
        ->expectsOutputToContain('php artisan migrate')
        ->assertSuccessful();
});