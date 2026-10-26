<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman utama di-redirect ke POS.
     */
    public function test_root_redirects_to_pos(): void
    {
        $respons = $this->get('/');

        $respons->assertRedirect(route('pos'));
    }
}
