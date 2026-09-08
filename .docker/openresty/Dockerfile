# T-13: Digest-pinned for reproducible builds. Update via Renovate.
FROM openresty/openresty:alpine@sha256:9a80f210927074afde534ae008b843a1afa259b4fb3183e152151a600a8d1f45

# 安装基础依赖
RUN apk add --no-cache gettext bash curl openssl socat

# 拷贝 entrypoint
COPY .docker/openresty/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy nginx config, site templates, and lua scripts into the image.
# In dev, these are bind-mounted from .docker/openresty/. In production,
# they must be baked into the image because named volumes are empty
# on first mount and would hide the image content.
COPY .docker/openresty/nginx.conf /usr/local/openresty/nginx/conf/nginx.conf
COPY .docker/openresty/sites/ /etc/nginx/conf.d/sites/
COPY .docker/openresty/lua/ /usr/local/openresty/lua/

CMD ["/bin/sh", "/usr/local/bin/entrypoint.sh"]
