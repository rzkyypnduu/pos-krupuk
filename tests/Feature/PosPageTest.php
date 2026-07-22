<?php

namespace Tests\Feature;

use Tests\TestCase;

class PosPageTest extends TestCase
{
    public function test_pos_page_loads(): void
    {
        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('POS Krupuk');
    }
}
