WorkBC Load Testing
===================

This folder contains tools to load-test the WorkBC site. It uses [`siege`](https://github.com/JoeDog/siege) as the load-testing engine.

# Getting Started
```
docker-compose up
docker-compose exec test bash
./load-test.sh
```

# Configuration
- `BASE_URL` environment variable is used to transform a sitemap.xml file to a list of URLs that `siege` should target.
- `siege.conf` controls the `siege` running parameters.
