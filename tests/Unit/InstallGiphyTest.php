<?php

namespace Ghanem\Bee\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Ghanem\Bee\Tests\TestCase;

class InstallBeeTest extends TestCase
{
    /** @test */
    function the_install_command_copies_the_configuration()
    {
        // make sure we're starting from a clean state
        if (File::exists(config_path('bee.php'))) {
            unlink(config_path('bee.php'));
        }

        $this->assertFalse(File::exists(config_path('bee.php')));

        Artisan::call('bee:install');

        $this->assertTrue(File::exists(config_path('bee.php')));
    }
}
