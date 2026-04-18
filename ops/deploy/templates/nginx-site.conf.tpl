server {
    listen 80;
    listen [::]:80;
    server_name __DEPLOY_DOMAIN__;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name __DEPLOY_DOMAIN__;

    ssl_certificate __DEPLOY_NGINX_CERT_PATH__;
    ssl_certificate_key __DEPLOY_NGINX_KEY_PATH__;

    location / {
        proxy_pass __DEPLOY_NGINX_PROXY_PASS__;
        proxy_ssl_verify off;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Request-Id $request_id;
    }
}
