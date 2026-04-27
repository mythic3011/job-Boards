<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AvatarFallbackContractTest extends TestCase
{
    public function test_avatar_component_uses_data_driven_fallback_instead_of_inline_error_handling(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/avatar.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-avatar-fallback-id', $contents);
        $this->assertStringContainsString('data-avatar-image', $contents);
        $this->assertStringContainsString('object-cover hidden', $contents);
        $this->assertStringNotContainsString('x-on:error', $contents);
    }

    public function test_avatar_javascript_handles_both_image_load_and_error_states(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/js/avatar.js');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-avatar-fallback-id', $contents);
        $this->assertStringContainsString('document.addEventListener(', $contents);
        $this->assertStringContainsString('"load"', $contents);
        $this->assertStringContainsString('"error"', $contents);
        $this->assertStringContainsString('"livewire:init"', $contents);
        $this->assertStringContainsString('livewire.hook("morph.updated"', $contents);
    }

    public function test_file_input_uses_the_same_data_driven_avatar_fallback_contract(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/file-input.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('data-avatar-fallback-id', $contents);
        $this->assertStringContainsString('data-avatar-image', $contents);
        $this->assertStringContainsString('object-cover hidden', $contents);
    }

    public function test_application_views_use_the_shared_avatar_component_instead_of_raw_profile_image_markup(): void
    {
        $userView = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/applications/show.blade.php');
        $adminView = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/applications/show.blade.php');

        $this->assertIsString($userView);
        $this->assertIsString($adminView);

        $this->assertStringContainsString('<x-ui.avatar', $userView);
        $this->assertStringContainsString('<x-ui.avatar', $adminView);
        $this->assertStringNotContainsString("route('images.profile'", $userView);
        $this->assertStringNotContainsString("route('images.profile'", $adminView);
    }
}
