<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        // The back end has no storefront page of its own (the shop is the separate Next.js app): the root address sends visitors to the login page
        // (HomeController::index). The stub that expected a 200 page came with the framework skeleton and never matched this application.
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
