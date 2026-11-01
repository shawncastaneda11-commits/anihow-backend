<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_the_privacy_policy_is_public(): void
    {
        config(['anihow.seller_help.email' => 'help@example.com']);

        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Data Privacy Act of 2012')
            ->assertSee('help@example.com')
            ->assertSee(route('legal.delete-account', ['lang' => 'en']), false);

        $this->get('/privacy?lang=fil')
            ->assertOk()
            ->assertSee('Patakaran sa Privacy')
            ->assertSee('Ano ang kinokolekta namin');
    }

    public function test_the_account_deletion_page_is_public_and_gives_both_ways_to_ask(): void
    {
        config(['anihow.seller_help.email' => 'help@example.com']);

        $this->get('/delete-account')
            ->assertOk()
            ->assertSee('Delete your AniHow account')
            ->assertSee('Request account deletion')
            ->assertSee('mailto:help@example.com', false)
            ->assertSee('Deleted user')
            ->assertSee('removed within 30 days');

        $this->get('/delete-account?lang=fil')
            ->assertOk()
            ->assertSee('Burahin ang iyong AniHow account');
    }
}
