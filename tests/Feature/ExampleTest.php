<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_directed_to_the_login_page_from_the_home_page(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }
}
