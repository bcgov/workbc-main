FROM 266795317183.dkr.ecr.ca-central-1.amazonaws.com/drupal-base:2.2
COPY src /code
ARG GITHUB_SHA=unknown
ENV GITHUB_SHA=$GITHUB_SHA

#To install yarn in the drupal container
RUN curl https://deb.nodesource.com/setup_12.x | bash
RUN curl https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt-get update && apt-get install -y nodejs yarn postgresql-client

RUN chmod -R g+rwX /code
RUN cd /code && rm -rf .git && composer install
