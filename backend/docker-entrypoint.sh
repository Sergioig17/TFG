#!/bin/bash
set -e

# Use PORT env var from Railway (fallback to 80)
PORT="${PORT:-80}"

cat > /etc/apache2/ports.conf <<EOF
# If you just change the port or add more, be sure to update the VirtualHost
Listen ${PORT}
<IfModule ssl_module>
    Listen 443
</IfModule>
<IfModule mod_gnutls.c>
    Listen 443
</IfModule>
EOF

cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:${PORT}>
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

exec apache2-foreground
