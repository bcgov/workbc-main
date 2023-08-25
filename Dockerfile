FROM 075458558257.dkr.ecr.ca-central-1.amazonaws.com/drupal-base:4.2
COPY src /code
ARG GITHUB_SHA=unknown
ENV GITHUB_SHA=$GITHUB_SHA

RUN chmod -R g+rwX /code
RUN cd /code && rm -rf .git && composer install && composer update
