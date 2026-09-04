# T-13: Digest-pinned for reproducible builds. Update via Renovate.
FROM openresty/openresty:alpine@sha256:9a80f210927074afde534ae008b843a1afa259b4fb3183e152151a600a8d1f45

# 安装基础依赖
RUN apk add --no-cache gettext bash curl openssl socat

# 拷贝 entrypoint
COPY .docker/openresty/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

CMD ["/bin/sh", "/usr/local/bin/entrypoint.sh"]
