FROM fedora:43

RUN dnf install -y nginx wget \
	&& dnf clean all \
	&& rm -rf /var/cache/dnf

COPY front/ /usr/share/nginx/html/
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
