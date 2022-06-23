FROM 266795317183.dkr.ecr.ca-central-1.amazonaws.com/drupal-base:2.0
COPY src /code
RUN chmod -R g+rwX /code
RUN cd /code && rm -rf .git && composer install && composer update
