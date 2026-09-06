# Runtime binaries (`rr`, `frankenphp`)

The project root may contain pre-built Octane server binaries:

| File   | Size  | Source                                  | Purpose                      |
|--------|-------|-----------------------------------------|------------------------------|
| `rr`   | ~60 MB | `./vendor/bin/rr get-binary` (RoadRunner) | Local Octane dev server    |
| `frankenphp` | ~163 MB | [FrankenPHP releases](https://github.com/dunglas/frankenphp/releases) | Alternative Octane server |

Both are **git-ignored** (see `.gitignore`) and are **not** committed to the
repository. They are convenience copies for local development and should never
be committed.

## How to obtain them

### RoadRunner (default — `OCTANE_SERVER=roadrunner`)

```bash
composer install
./vendor/bin/rr get-binary
```

This downloads the correct RoadRunner binary for your platform into the
project root.

### FrankenPHP (alternative — `OCTANE_SERVER=frankenphp`)

```bash
# Download the standalone binary from GitHub releases
# https://github.com/dunglas/frankenphp/releases
# Place it in the project root as `frankenphp`
chmod +x frankenphp
```

The FrankenPHP worker script is at `public/frankenphp-worker.php`.

## CI

CI does **not** use these local binaries. The `octane.yml` workflow downloads
RoadRunner fresh on each run via `./vendor/bin/rr get-binary`.

## Docker

The Docker stack uses PHP-FPM (not Octane) for the `php` service. Octane is
optional and intended for local development or dedicated production deployments
that run `php artisan octane:start` outside the Docker Compose stack.

## Cleaning up

```bash
rm -f rr frankenphp
```

They will be re-downloaded by the commands above when needed.
