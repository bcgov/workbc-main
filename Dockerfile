FROM 075458558257.dkr.ecr.ca-central-1.amazonaws.com/drupal-base:4.2
COPY src /code
ARG GITHUB_SHA=unknown
ENV GITHUB_SHA=$GITHUB_SHA

ARG TIMEOUT
ARG MAX_CHILDREN
ARG START_SERVERS
ARG MIN_SPARE
ARG MAX_SPARE
ARG SPAWN_RATE
RUN sed -i '/;slowlog/c\slowlog = /var/log/slow.log' /usr/local/etc/php-fpm.d/www.conf
RUN sed -i "/;request_slowlog_timeout/c\request_slowlog_timeout = $TIMEOUT" /usr/local/etc/php-fpm.d/www.conf
RUN sed -i "/pm.max_children = 5/c\pm.max_children = $MAX_CHILDREN" /usr/local/etc/php-fpm.d/www.conf
RUN sed -i "/pm.start_servers = 2/c\pm.start_servers = $START_SERVERS" /usr/local/etc/php-fpm.d/www.conf
RUN sed -i "/pm.min_spare_servers = 1/c\pm.min_spare_servers = $MIN_SPARE" /usr/local/etc/php-fpm.d/www.conf
RUN sed -i "/pm.max_spare_servers = 3/c\pm.max_spare_servers = $MAX_SPARE" /usr/local/etc/php-fpm.d/www.conf
RUN sed -i "/;pm.max_spawn_rate/c\pm.max_spawn_rate = $SPAWN_RATE" /usr/local/etc/php-fpm.d/www.conf

RUN chmod -R g+rwX /code
RUN cd /code && rm -rf .git && composer install && composer update
