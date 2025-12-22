<?php

namespace Ashurov\SQLConsole;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Ashurov\SQLConsole\Filament\Server\Pages\SQLConsole;

class SQLConsolePlugin implements Plugin
{
    public function getId(): string
    {
        return 'sqlconsole';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'server') {
            $panel->pages([
                SQLConsole::class,
            ]);
        }
        
        $panel->discoverPages(in: __DIR__ . '/Filament/Server/Pages', for: 'Ashurov\\SQLConsole\\Filament\\Server\\Pages');
    }

    public function boot(Panel $panel): void
    {
    }
}