server {
    listen   80;
    listen   [::]:80 default ipv6only=on;
    server_name  localhost;

    root   "{{LANDO_WEBROOT}}";
    index index.php index.html index.htm;

    # Front controller. Lando's stock vhost has no try_files fallback, so any
    # route that is not a real file (e.g. /health) 404s at nginx before Slim
    # ever sees the request.
    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+?\.php)(/.*)$;
        fastcgi_pass fpm:9000;
        fastcgi_index  index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_buffers 256 128k;
        fastcgi_connect_timeout 300s;
        fastcgi_send_timeout 300s;
        fastcgi_read_timeout 300s;
        include fastcgi_params;
    }
}
