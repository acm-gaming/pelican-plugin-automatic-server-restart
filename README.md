# Automatic Server Restart

A Pelican Panel plugin that lets you schedule daily restarts for your game servers. You pick a time, and the plugin handles the rest — including an optional warning message so your players aren't caught off guard.

## What it does

- Adds an **Auto Restart** button to each server's settings page
- Lets you set a daily restart time per server (uses your account's timezone)
- Optionally sends an announcement command to the server 1 minute before restarting, so players get a heads-up
- Runs quietly in the background — checks every minute and restarts any servers that are due
- Only restarts servers that are currently running (won't try to restart a stopped or errored server)

## Requirements

- A working [Pelican Panel](https://pelican.dev) installation with plugin support
- The panel's scheduler must be running (`php artisan schedule:run` via cron)

## Installation

1. Download the latest release zip from the [Releases](../../releases) page
2. Extract it into your Pelican `plugins/` directory — the folder should be named `automatic-server-restart`
3. Go to the admin area in Pelican and enable the plugin
4. Run `php artisan migrate` to create the settings table

That's it — the plugin is ready to use.

## How to use it

1. Open any server in the Pelican panel
2. Go to the **Settings** page
3. You'll see a new **Auto Restart** button in the header — click it
4. In the modal that opens, configure your restart:
   - **Enable automatic restart** — flip this on
   - **Restart time** — pick the time of day you want the server to restart (24-hour format, e.g. `04:00` for 4 AM). This uses whatever timezone is set on your Pelican account.
   - **Announcement command** _(optional)_ — a command to send to the server console 1 minute before the restart happens. For example, you might use `say Server restarting in 1 minute!` for a Minecraft server. Leave it blank if you don't need a warning.
5. Hit **Save**

The plugin will check every minute if any servers are due for a restart. When the time matches, it sends the announcement command (if configured), waits 60 seconds, then restarts the server.

## Permissions

The Auto Restart button is only visible to users who have the **Settings Rename** permission for that server. Admins and server owners will see it by default.

## Troubleshooting

**The server isn't restarting at the scheduled time**
- Make sure the panel's cron job is running. You should have something like `* * * * * cd /var/www/pelican && php artisan schedule:run >> /dev/null 2>&1` in your crontab.
- Check that the restart is enabled and the time is set correctly for your timezone.
- The server must be in a running state — the plugin won't restart stopped or errored servers.

**The announcement command isn't showing up in-game**
- Make sure the command you're using is valid for your game. Different games use different console commands for broadcasting messages.

## Contributing

This project uses [Conventional Commits](https://www.conventionalcommits.org/) for versioning. Releases are automated with `release-please`.

- `fix: ...` — patch release
- `feat: ...` — minor release
- `feat!: ...` or `BREAKING CHANGE: ...` — major release
