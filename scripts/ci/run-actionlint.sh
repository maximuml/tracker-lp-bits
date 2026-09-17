#!/usr/bin/env bash
# Download pinned actionlint and lint all workflow files. Invalid
# workflow files surface as 0-job failed runs on every push (see the
# `cancel-progress` bug fixed in W8-05) — lint them like code.
set -euo pipefail

AL_VER=1.7.12
AL_SHA256=8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8
curl -sSfL "https://github.com/rhysd/actionlint/releases/download/v${AL_VER}/actionlint_${AL_VER}_linux_amd64.tar.gz" -o /tmp/actionlint.tgz
echo "${AL_SHA256}  /tmp/actionlint.tgz" | sha256sum -c -
tar xzf /tmp/actionlint.tgz -C /tmp actionlint
# -shellcheck= disables the embedded shellcheck pass: the repo does not
# enforce shellcheck style; this gate exists for schema errors (invalid
# keys, bad contexts) that produce phantom 0-job failures.
/tmp/actionlint -shellcheck= .github/workflows/*.yml
