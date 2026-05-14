# pls

A simple CLI helper for managing Docker Compose commands across Canyon GBS projects.

## Installation

### Prerequisites

- Docker and Docker Compose must be installed on your system
- **macOS**: [Install Docker Desktop](https://docs.docker.com/desktop/mac/install/)
- **Linux**: [Install Docker Engine](https://docs.docker.com/engine/install/)
- **Windows**: Install [WSL2](https://docs.microsoft.com/en-us/windows/wsl/install) first, then install Docker inside your Linux shell (see below)

### Install pls

Run this command in your terminal (no root permissions required):

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/canyongbs/common/main/pls/tools/install.sh)"
```

The installer will:

1. Download the `pls` script to `~/.pls/bin/pls`
2. Detect your shell (bash/zsh)
3. Prompt you to add `~/.pls/bin` to your PATH

#### Windows (WSL2)

Run `wsl` from a Windows terminal to enter the Linux shell, then run the same install command above. Always run `pls` from the Linux shell.

### Update

```bash
pls update
```

## Usage

```
pls [command] [options]
```

### Commands

| Command         | Description                                                                 |
| --------------- | --------------------------------------------------------------------------- |
| `build`         | Build Docker images                                                         |
| `up`            | Start Docker containers (`-d` for detached mode)                            |
| `stop`          | Stop Docker containers                                                      |
| `down`          | Stop containers and remove resources (`-v` to remove volumes)               |
| `logs`          | Show logs (`-f` to follow, pass service names to filter)                    |
| `exec`          | Execute a command in a running container                                    |
| `shell`         | Start a bash shell as www-data (pass service name, defaults to `app`)       |
| `rshell`        | Start a bash shell as root (pass service name, defaults to `app`)           |
| `ih`            | Run a command in a local-cli container with the same environment as the app |
| `npmsetup`      | Run `npm ci`, fix ownership, and `npm run build`                            |
| `composersetup` | Run `composer install` and fix ownership                                    |
| `update`        | Update pls to the latest version                                            |

### Examples

```bash
pls up -d                 # Start containers in detached mode
pls logs app -f           # Follow logs for the app service
pls shell                 # Open a shell in the app container
pls exec app php artisan  # Run artisan in the app container
pls down -v               # Stop everything and remove volumes
```

### Options

Any additional options are passed directly to the underlying `docker compose` commands.

```bash
pls --version   # Display the version of pls
pls --help      # Show help
```
