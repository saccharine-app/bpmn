<?php

afterEach(function () {
    // Clean out the published migrations to prevent class collisions in other tests
    File::cleanDirectory(database_path('migrations'));
});

it('runs the install command and offers to migrate', function () {
    $this->artisan('bpmn:install')
        ->expectsConfirmation('Would you like to run the database migrations now?', 'no')
        ->expectsOutputToContain('Saccharine BPMN installed successfully!')
        ->assertSuccessful();
});