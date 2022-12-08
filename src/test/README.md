WorkBC Load Testing
===================

This folder contains tools to load-test the WorkBC site. It uses [`siege`](https://github.com/JoeDog/siege) as the load-testing engine.

# Getting Started
```
docker-compose up
docker-compose exec test bash
./load-test.sh > siege.log 2>&1
```

Output looks like the following:
```
[...]
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.01 secs:     743 bytes ==> GET  http://workbc.docker.localhost:8000/core/assets/vendor/jquery.ui/themes/base/core.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.01 secs:    1085 bytes ==> GET  http://workbc.docker.localhost:8000/modules/custom/calendar_listview/js/cv-custom.js?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     160 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/classy/css/components/ui-dialog.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     104 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/stable/css/system/components/nowrap.module.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     149 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/classy/css/components/textarea.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     430 bytes ==> GET  http://workbc.docker.localhost:8000/themes/custom/workbc/js/scrollbooster-init.js?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     233 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/stable/css/system/components/js.module.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     124 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/classy/css/components/tablesort.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     197 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/classy/css/components/tableselect.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     157 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/stable/css/system/components/item-list.module.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     159 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/classy/css/components/tabledrag.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.01 secs:     160 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/classy/css/components/pager.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.14 secs:   39720 bytes ==> GET  https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     173 bytes ==> GET  http://workbc.docker.localhost:8000/themes/custom/workbc/js/submenu-underlay.js?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.01 secs:     666 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/stable/css/system/components/hidden.module.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     151 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/classy/css/components/more-link.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.01 secs:     122 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/stable/css/system/components/details.module.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.01 secs:     310 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/classy/css/components/menu.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.00 secs:     222 bytes ==> GET  http://workbc.docker.localhost:8000/core/themes/stable/css/system/components/clearfix.module.css?rmlcjq
[Thu, 2022-12-08 23:29:02] HTTP/1.1 200     0.01 secs:   56282 bytes ==> GET  http://workbc.docker.localhost:8000/themes/contrib/bootstrap5/dist/bootstrap/5.1.3/dist/js/bootstrap.bundle.js?v=5.1.3
[...]
Lifting the server siege...
Transactions:		      257537 hits
Availability:		      100.00 %
Elapsed time:		      299.11 secs
Data transferred:	    10892.26 MB
Response time:		        0.03 secs
Transaction rate:	      861.01 trans/sec
Throughput:		       36.42 MB/sec
Concurrency:		       24.28
Successful transactions:      257539
Failed transactions:	           8
Longest transaction:	        8.09
Shortest transaction:	        0.00
```

# Configuration
- `BASE_URL` environment variable targets a specific Drupal installation and queries its `/sitemap.xml` file (default: `http://workbc.docker.localhost:8000`)
- `siege.conf` controls the `siege` running parameters.
