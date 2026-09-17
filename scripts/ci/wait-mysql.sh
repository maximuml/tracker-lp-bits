#!/usr/bin/env bash
# Wait for the MySQL service container to accept connections.
set -euo pipefail

for i in $(seq 1 60); do
    nc -z 127.0.0.1 3306 && break
    sleep 1
done
nc -z 127.0.0.1 3306 || { echo "MySQL did not become ready in time"; exit 1; }
