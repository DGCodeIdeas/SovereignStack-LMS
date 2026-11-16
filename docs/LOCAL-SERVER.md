# Local Server — Persistent Toggle

`scripts/lms-server` manages a dedicated Apache instance for the local LMS on
`http://127.0.0.1:8082`. It is a systemd system service, enabled at boot, and
runs as the current user (`dgci`). It does not touch the system Apache, Caddy,
Tengine or the system MariaDB configuration.

Privileged operations use **`pkexec`**, never `sudo`.

## Prerequisites

- `apache2` with `libapache2-mod-php8.4` installed
- `systemd`, `pkexec`, `curl`
- MariaDB active; this instance is enabled at boot
- `~/www/SovereignStack-LMS/.env.local` present with the local database and base URL
  (the file is gitignored and must not be committed)

## Commands

```bash
cd ~/www/SovereignStack-LMS

./scripts/lms-server enable      # install + enable at boot + start (pkexec)
./scripts/lms-server toggle      # start if stopped, stop if running (pkexec)
./scripts/lms-server status      # service state + /health + /ready
./scripts/lms-server start       # start now (pkexec)
./scripts/lms-server stop        # stop now (pkexec)
./scripts/lms-server restart     # restart now (pkexec)
./scripts/lms-server disable     # stop + disable at boot (pkexec)
./scripts/lms-server logs        # last 100 journal lines
./scripts/lms-server config      # render + Apache syntax check
./scripts/lms-server doctor      # dependency/env/port/readiness checks
./scripts/lms-server uninstall   # remove unit (keeps config/data)
```

`enable` prompts through the desktop polkit agent. The first run creates and
starts the service; subsequent runs re-render the config and restart the
service, so config changes take effect.

## What persists

| Path | Purpose |
|---|---|
| `~/.local/share/sovereignstack-lms/apache/httpd.conf` | Rendered Apache config |
| `~/.local/share/sovereignstack-lms/apache/logs/` | Apache error/access logs |
| `~/.local/share/sovereignstack-lms/apache/run/` | PID, mutex, scoreboard |
| `~/.local/share/sovereignstack-lms/php-sessions/` | PHP session files for the `dgci` service user |
| `/etc/systemd/system/sovereignstack-lms.service` | Installed systemd unit |

The service runs as `dgci`; the app data (`~/www/SovereignStack-LMS`) and the
MariaDB `lms` database persist across reboot. The old `/tmp` instance is not
used.

## Port and host

Defaults: `LMS_HOST=127.0.0.1`, `LMS_PORT=8082`. Override before `enable`:

```bash
LMS_PORT=8083 ./scripts/lms-server enable
```

If the port changes, update `APP_BASE_URL` in `.env.local` to match, then run
`./scripts/lms-server enable` again.

The listener is deliberately bound to loopback only. Do not expose this
development instance publicly.

## Boot behaviour

`systemctl enable` installs a `multi-user.target` unit. At boot systemd starts
the service after `network-online.target` and `mariadb.service`, under user
`dgci`. The unit has `ConditionPathExists=.../.env.local`, so it will not start
without the local environment file. `Restart=on-failure` restarts Apache if it
exits unexpectedly.

## Troubleshooting

```bash
./scripts/lms-server doctor
./scripts/lms-server logs
tail -n 100 ~/.local/share/sovereignstack-lms/apache/logs/error.log
tail -n 100 ~/.local/share/sovereignstack-lms/apache/logs/vhost-error.log
tail -n 100 ~/www/SovereignStack-LMS/var/log/dev.log
```

- **Port busy:** stop the other listener or use `LMS_PORT`.
- **Service starts then exits:** check `doctor`, `.env.local`, the Apache logs,
  and whether MariaDB is active.
- **pkexec does not prompt:** start the command from a graphical terminal
  session (polkit needs an authentication agent).
- **Login/session failures:** the service writes sessions under
  `~/.local/share/sovereignstack-lms/php-sessions`; the rendered config sets
  `session.save_path` there explicitly.

## Security notes

- `pkexec` is used only for installing the unit and for `systemctl` actions.
- No password or secret is stored in the script or systemd unit.
- `.env.local` remains gitignored and must not be committed.
- This is a local development server, not a production deployment.
