# T-13: Digest-pinned for reproducible builds. Update via Renovate.
FROM openresty/openresty:alpine@sha256:b3a6f1f432eabdbda4adcb6ec3e6461e621782eaa82dbe67154ddd1afd109569

# 安装基础依赖
RUN apk add --no-cache gettext~=0.24 bash~=5.3 curl~=8.22 openssl~=3.5 socat~=1.8

# CVE-2026-93990: base image ships libexpat 2.8.4; apk add does not
# upgrade packages already present in the base layer, so pull the fix
# explicitly until the pinned digest is bumped past it.
RUN apk add --no-cache 'expat>=2.8.5' 'libexpat>=2.8.5'

# CVE-2026-46675: same for libpng — base layer carries a pre-1.6.59
# build (png_read_end use-after-free); pull the patched one.
RUN apk add --no-cache 'libpng>=1.6.59'

# 拷贝 entrypoint
COPY .docker/openresty/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY .docker/openresty/render-config.sh /usr/local/bin/render-config.sh
COPY .docker/openresty/cert-watcher.sh /usr/local/bin/cert-watcher.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/render-config.sh /usr/local/bin/cert-watcher.sh

# Copy nginx config, site templates, and lua scripts into the image.
# In dev, these are bind-mounted from .docker/openresty/. In production,
# they must be baked into the image because named volumes are empty
# on first mount and would hide the image content.
COPY .docker/openresty/nginx.conf /usr/local/openresty/nginx/conf/nginx.conf
COPY .docker/openresty/sites/ /etc/nginx/conf.d/sites/
COPY .docker/openresty/lua/ /usr/local/openresty/lua/

# Remove the default openresty vhost — it conflicts with our app.conf
# (both listen on port 80 with server_name localhost).
RUN rm -f /etc/nginx/conf.d/default.conf

CMD ["/bin/sh", "/usr/local/bin/entrypoint.sh"]
