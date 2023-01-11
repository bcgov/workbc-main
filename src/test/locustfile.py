from locust import HttpUser, task, between
import urllib
import random
from io import TextIOWrapper

class QuickstartUser(HttpUser):
    # Wait between 7 and 15 seconds per request per user
    wait_time = between(7, 15)

    # Download the list of urls
    request = urllib.request.Request('https://raw.githubusercontent.com/bcgov/workbc-main/develop/src/test/urls-aws-prod.txt')
    request.add_header('Pragma', 'no-cache')
    response = TextIOWrapper(urllib.request.urlopen(request))
    urls = [url.strip() for url in response.readlines() if url.strip()]

    @task(1)
    def random_page(self):
        self.client.get("%s" % (random.choice(self.urls)))
