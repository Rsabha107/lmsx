<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    /**
     * Azure (and any TLS-terminating proxy) forwards over plain HTTP with
     * X-Forwarded-Proto. If that header isn't honoured, every generated URL
     * comes out as http:// and the browser blocks it as mixed content.
     */
    public function test_a_forwarded_https_request_is_treated_as_secure(): void
    {
        $this->get('/login', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'scpma.azurewebsites.net',
        ])->assertOk();

        $this->assertTrue(request()->isSecure(), 'Request behind an HTTPS proxy should be secure.');
        $this->assertStringStartsWith('https://', url('/'));
    }

    public function test_a_plain_http_request_is_not_promoted_to_secure(): void
    {
        $this->get('/login')->assertOk();

        $this->assertFalse(request()->isSecure());
    }
}
