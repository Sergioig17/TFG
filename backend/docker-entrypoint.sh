#!/bin/bash
set -euo pipefail

# Configure nginx to listen on $PORT (Railway provides PORT)
PORT="${PORT:-80}"

cat > /etc/nginx/conf.d/default.conf <<'EOF'
server {
    listen ${PORT};
    server_name _;

    root /var/www/html/public;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

# Ensure nginx can read files
chown -R www-data:www-data /var/www/html || true

# Start php-fpm in background
php-fpm -D

# Start nginx in foreground
nginx -g 'daemon off;'
