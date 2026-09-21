<?php

declare(strict_types=1);

abstract class SendoraOnboardingTest extends SendoraAutomationsTest
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($GLOBALS['sendora_test_options'][Sendora_Onboarding::FINISHED_OPTION]);
        delete_option(Sendora_Onboarding::FINISHED_OPTION);
    }

    public function test_onboarding_sanitize_and_defaults(): void
    {
        $this->assertTrue(class_exists('Sendora_Onboarding'));
        $state = Sendora_Onboarding::sanitize([
            'completed' => '1',
            'wizard_step' => 99,
            'channels_seen' => 'on',
        ]);
        $this->assertTrue($state['completed']);
        $this->assertTrue($state['channels_seen']);
        $this->assertSame(4, $state['wizard_step']);
        $this->assertFalse($state['dismissed']);
    }

    public function test_partial_sanitize_onboarding(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = [
            'api_base' => 'https://api.sendora.com.br',
            'api_key' => 'sk_keep',
        ];
        $saved = Sendora_Settings::sanitize([
            '_partial' => 'onboarding',
            'onboarding' => [
                'completed' => '0',
                'dismissed' => '0',
                'channels_seen' => '1',
                'test_sent' => '0',
                'wizard_step' => 2,
            ],
        ]);
        $this->assertSame('sk_keep', $saved['api_key']);
        $this->assertTrue($saved['onboarding']['channels_seen']);
        $this->assertSame(2, $saved['onboarding']['wizard_step']);
    }

    public function test_wizard_due_and_dismiss(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = [
            'api_key' => 'sk_x',
            'onboarding' => Sendora_Onboarding::defaults(),
        ];
        $this->assertTrue(Sendora_Onboarding::is_wizard_due());
        Sendora_Onboarding::dismiss();
        $this->assertFalse(Sendora_Onboarding::is_wizard_due());
        $this->assertFalse(Sendora_Onboarding::show_checklist());
        $this->assertTrue(Sendora_Onboarding::state()['dismissed']);
        $this->assertTrue(Sendora_Onboarding::state()['completed']);
    }

    public function test_complete_also_hides_forever(): void
    {
        $GLOBALS['sendora_test_options']['sendora_settings'] = [
            'api_key' => 'sk_x',
            'onboarding' => Sendora_Onboarding::defaults(),
        ];
        unset($GLOBALS['sendora_test_options'][Sendora_Onboarding::FINISHED_OPTION]);
        Sendora_Onboarding::complete();
        $this->assertFalse(Sendora_Onboarding::is_wizard_due());
        $this->assertFalse(Sendora_Onboarding::show_checklist());
        $this->assertTrue(Sendora_Onboarding::is_finished());
        $this->assertTrue(Sendora_Onboarding::state()['dismissed']);
        // set_step must not reopen after finish
        Sendora_Onboarding::update(['wizard_step' => 2]);
        $this->assertFalse(Sendora_Onboarding::is_wizard_due());
        $this->assertTrue(Sendora_Onboarding::state()['completed']);
    }

    public function test_admin_registers_onboarding_ajax(): void
    {
        $GLOBALS['sendora_test_actions'] = [];
        (new Sendora_Admin())->run();
        $this->assertArrayHasKey('wp_ajax_sendora_onboarding', $GLOBALS['sendora_test_actions']);
    }
}
