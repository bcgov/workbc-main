FROM ubuntu:jammy

ENV TERM linux
ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update && apt-get install -y \
  wget \
  libxml2-utils \
  siege \
  python3-pip \
  && rm -rf /var/lib/apt/lists/*

RUN pip3 install locust

WORKDIR /app

CMD ["/bin/bash"]
