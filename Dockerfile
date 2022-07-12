FROM 266795317183.dkr.ecr.ca-central-1.amazonaws.com/drupal-base:2.0
RUN apt-get update && apt-get install -y \
  postgresql-client \
  && rm -rf /var/lib/apt/lists/*
COPY src /code
ARG GITHUB_SHA=unknown
ENV GITHUB_SHA=$GITHUB_SHA
RUN chmod -R g+rwX /code
RUN cd /code && rm -rf .git && AWS_BUILD_NAME=1 composer install
