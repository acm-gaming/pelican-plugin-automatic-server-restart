<?php

namespace Acmgaming\AutomaticServerRestart\Console\Commands;

use Acmgaming\AutomaticServerRestart\Models\AutomaticServerRestartSetting;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class ProcessAutomaticServerRestartCommand extends Command
{
    protected $signature = 'p:server:auto-restart:process';

    protected $description = 'Process automatic server restart settings and restart servers when they are due.';

    public function handle(DaemonServerRepository $daemonServerRepository): int
    {
        $settings = AutomaticServerRestartSetting::query()
            ->with('server')
            ->where('enabled', true)
            ->whereNotNull('restart_time')
            ->whereRelation('server', fn (Builder $builder) => $builder->whereNull('status'))
            ->get();

        $this->sendAnnouncements($settings);
        $this->restartDueServers($settings, $daemonServerRepository);

        return self::SUCCESS;
    }

    /**
     * Send announcement commands to servers that are 1 minute away from their restart time.
     */
    private function sendAnnouncements(\Illuminate\Support\Collection $settings): void
    {
        $settings
            ->filter(fn (AutomaticServerRestartSetting $setting): bool => filled($setting->announcement_command)
                && $setting->server instanceof Server
                && now($setting->timezone)->addMinute()->format('H:i') === $setting->restart_time)
            ->each(function (AutomaticServerRestartSetting $setting): void {
                try {
                    $setting->server->send($setting->announcement_command);
                } catch (Throwable $exception) {
                    report($exception);

                    $this->warn("Failed to send announcement command to server #{$setting->server->id}: {$exception->getMessage()}");
                }
            });
    }

    /**
     * Restart servers whose restart time matches the current minute.
     */
    private function restartDueServers(\Illuminate\Support\Collection $settings, DaemonServerRepository $daemonServerRepository): void
    {
        $settings
            ->filter(fn (AutomaticServerRestartSetting $setting): bool => $setting->server instanceof Server
                && now($setting->timezone)->format('H:i') === $setting->restart_time
                && (!$setting->last_restarted_at || !$setting->last_restarted_at->isToday()))
            ->each(function (AutomaticServerRestartSetting $setting) use ($daemonServerRepository): void {
                try {
                    $daemonServerRepository->setServer($setting->server)->power('restart');

                    $setting->update(['last_restarted_at' => now()]);
                } catch (Throwable $exception) {
                    report($exception);

                    $this->error("Failed to restart server #{$setting->server->id}: {$exception->getMessage()}");
                }
            });
    }
}
