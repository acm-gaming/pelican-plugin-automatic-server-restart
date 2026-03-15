<?php

namespace Acmgaming\AutomaticServerRestart\Providers;

use Acmgaming\AutomaticServerRestart\Console\Commands\ProcessAutomaticServerRestartCommand;
use Acmgaming\AutomaticServerRestart\Models\AutomaticServerRestartSetting;
use App\Enums\HeaderActionPosition;
use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Filament\Server\Pages\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class AutomaticServerRestartPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Settings::registerCustomHeaderActions(
            HeaderActionPosition::After,
            Action::make('automaticServerRestart')
                ->label('Auto Restart')
                ->icon(TablerIcon::Clock)
                ->visible(fn (\App\Models\Server $server) => user()?->can(SubuserPermission::SettingsRename, $server))
                ->modal()
                ->modalHeading('Automatic server restart')
                ->modalSubmitActionLabel('Save')
                ->schema(function (\App\Models\Server $server): array {
                    $setting = AutomaticServerRestartSetting::query()
                        ->where('server_id', $server->id)
                        ->first();

                    return [
                        Toggle::make('enabled')
                            ->live()
                            ->label('Enable automatic restart')
                            ->default((bool) $setting?->enabled),
                        TextInput::make('restart_time')
                            ->label('Restart time')
                            ->type('time')
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->rule('date_format:H:i')
                            ->helperText('Interpreted in your timezone (' . (user()?->timezone ?? 'UTC') . ').')
                            ->default($setting?->restart_time),
                        TextInput::make('announcement_command')
                            ->label('Announcement command (optional)')
                            ->helperText('Sent 1 minute before restart. Leave blank to skip.')
                            ->maxLength(65535)
                            ->default($setting?->announcement_command),
                    ];
                })
                ->action(function (array $data, \App\Models\Server $server): void {
                    AutomaticServerRestartSetting::query()->updateOrCreate(
                        ['server_id' => $server->id],
                        [
                            'enabled' => (bool) Arr::get($data, 'enabled', false),
                            'restart_time' => Arr::get($data, 'enabled') ? Arr::get($data, 'restart_time') : null,
                            'timezone' => user()?->timezone ?? config('app.timezone', 'UTC'),
                            'announcement_command' => filled(Arr::get($data, 'announcement_command')) ? trim(Arr::get($data, 'announcement_command')) : null,
                        ]
                    );

                    Notification::make()
                        ->title('Automatic restart settings saved.')
                        ->success()
                        ->send();
                })
        );

        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command(ProcessAutomaticServerRestartCommand::class)
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }
}
