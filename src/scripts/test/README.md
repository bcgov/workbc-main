WorkBC Load Testing
===================

This folder contains tools to load-test the WorkBC site. It uses [`siege`](https://github.com/JoeDog/siege) as the load-testing engine.

It is recommended to run Siege v4.1.6 or greater. This version [includes a fix to show URLs that cause errors](https://github.com/JoeDog/siege/issues/216) - you will need to [download the source package and compile it yourself](https://download.joedog.org/siege/) if the packaged version is earlier.

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

# cases.txt
This file contains additional target URLs such as POST commands or multiples of URLs that should be hit more often.

Make sure to start each target URL with a forward-slash `/` which will get prefixed with the value of `BASE_URL`.

```
/contact-us POST name=homer&email=test@localhost.com&message=hello&inquiry_type=job-seeker
/
/
/
/
/
/plan-career
/plan-career
/plan-career
/plan-career
/plan-career
```

# locustfile.py
As a parallel experiment, the alternative load-testing tool [Locust](https://locust.io/) can be used:
```
locust -H $BASE_URL --autostart
```
Then open http://0.0.0.0:8089/

# Troubleshooting
Your testing machine will encounter socket errors as your ramp up the concurrent users. You need to tune your kernel parameters to accommodate higher numbers, e.g.
- https://askubuntu.com/questions/46339/how-could-i-tune-the-linux-kernel-parameters-so-that-socket-could-be-recycled-fr
- https://stackoverflow.com/questions/880557/socket-accept-too-many-open-files
