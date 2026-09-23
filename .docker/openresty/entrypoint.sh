#!/bin/sh
set -e

# 定义颜色
COLOR_RED='\033[0;31m'
COLOR_GREEN='\033[0;32m'
COLOR_YELLOW='\033[1;33m'
COLOR_BLUE='\033[0;34m'
COLOR_RESET='\033[0m'

# 封装彩色输出函数
echo_info() {
  echo -e "${COLOR_BLUE}[INFO]${COLOR_RESET} $*"
}

echo_success() {
  echo -e "${COLOR_GREEN}[SUCCESS]${COLOR_RESET} $*"
}

echo_warn() {
  echo -e "${COLOR_YELLOW}[WARN]${COLOR_RESET} $*"
}

echo_error() {
  echo -e "${COLOR_RED}[ERROR]${COLOR_RESET} $*"
}

if [ -z "$NP_DOMAIN" ]; then
  echo_error "❌ 错误：必须设置 NP_DOMAIN 环境变量！"
  exit 1
fi

echo_info "NP_DOMAIN: $NP_DOMAIN"

# 生成配置 (render-config.sh doubles as the cert watcher's re-render step)
/usr/local/bin/render-config.sh

# Reload nginx when certbot drops or renews certs under /certs.
/usr/local/bin/cert-watcher.sh &

openresty -T

exec openresty -g 'daemon off;'
