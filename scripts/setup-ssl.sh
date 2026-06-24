#!/bin/bash
set -euo pipefail

DOMAIN="kztusdt.kz"
WWW_DOMAIN="www.kztusdt.kz"
NGINX_SITE="/etc/nginx/sites-available/crypto-exchange"
ENV_FILE="/var/www/crypto-exchange/backend/.env"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-admin@kztusdt.kz}"

if ! command -v certbot >/dev/null; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y -qq certbot python3-certbot-nginx
fi

cat > "$NGINX_SITE" <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name kztusdt.kz www.kztusdt.kz;

    server_tokens off;

    root /var/www/crypto-exchange/backend/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location ~* ^/(README\.md|readme\.md|CHANGELOG\.md)$ {
        deny all;
        return 404;
    }
    location ~* \.md$ {
        deny all;
        return 404;
    }
    location = /up {
        deny all;
        return 404;
    }
    location = /internal/health {
        allow 127.0.0.1;
        allow ::1;
        deny all;
    }
    location ~* ^/(composer\.(json|lock)|package(-lock)?\.json)$ {
        deny all;
        return 404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf "$NGINX_SITE" /etc/nginx/sites-enabled/crypto-exchange
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl reload nginx

if [[ ! -d "/etc/letsencrypt/live/$DOMAIN" ]]; then
  certbot --nginx \
    -d "$DOMAIN" \
    -d "$WWW_DOMAIN" \
    --non-interactive \
    --agree-tos \
    --email "$CERTBOT_EMAIL" \
    --redirect
else
  certbot renew --quiet
  systemctl reload nginx
fi

sed -i "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" "$ENV_FILE"
cd /var/www/crypto-exchange/backend
php artisan config:cache

echo "HTTPS enabled: https://$DOMAIN"
echo "Certificate: /etc/letsencrypt/live/$DOMAIN/fullchain.pem"
