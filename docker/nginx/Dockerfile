FROM openresty/openresty:alpine

RUN apk add --no-cache openssl apache2-utils curl tar && \
    mkdir -p /usr/local/openresty/lualib/resty && \
    curl -sL "https://raw.githubusercontent.com/ledgetech/lua-resty-http/master/lib/resty/http.lua" \
        -o /usr/local/openresty/lualib/resty/http.lua && \
    curl -sL "https://raw.githubusercontent.com/ledgetech/lua-resty-http/master/lib/resty/http_connect.lua" \
        -o /usr/local/openresty/lualib/resty/http_connect.lua && \
    curl -sL "https://raw.githubusercontent.com/ledgetech/lua-resty-http/master/lib/resty/http_headers.lua" \
        -o /usr/local/openresty/lualib/resty/http_headers.lua

# Extract cs-nginx-bouncer Lua files
RUN curl -sL "https://github.com/crowdsecurity/cs-nginx-bouncer/releases/download/v1.1.5/crowdsec-nginx-bouncer.tgz" \
    -o /tmp/cs-bouncer.tgz \
    && tar -xzf /tmp/cs-bouncer.tgz -C /tmp \
    && mkdir -p /usr/local/lua/crowdsec/plugins/crowdsec \
    && cp /tmp/crowdsec-nginx-bouncer-v1.1.5-rc1/lua-mod/lib/crowdsec.lua /usr/local/lua/crowdsec/crowdsec.lua \
    && cp /tmp/crowdsec-nginx-bouncer-v1.1.5-rc1/lua-mod/lib/plugins/crowdsec/*.lua /usr/local/lua/crowdsec/plugins/crowdsec/ \
    && mkdir -p /var/lib/crowdsec/lua/templates \
    && cp /tmp/crowdsec-nginx-bouncer-v1.1.5-rc1/lua-mod/templates/ban.html /var/lib/crowdsec/lua/templates/ban.html \
    && mkdir -p /etc/crowdsec/bouncers \
    && rm -rf /tmp/cs-bouncer* /tmp/crowdsec-nginx-bouncer*

COPY entrypoint.sh /docker-entrypoint-custom.sh
RUN chmod +x /docker-entrypoint-custom.sh

ENTRYPOINT ["/docker-entrypoint-custom.sh"]
