from locust import HttpUser, task, between
import urllib
import random

class QuickstartUser(HttpUser):
    # Wait between 7 and 15 seconds per request per user
    wait_time = between(7, 15)

    # Download the list of urls
    response = urllib.request.urlopen('https://raw.githubusercontent.com/bcgov/workbc-main/develop/src/test/urls-aws-prod.txt')
    urls = [url.strip() for url in response.readlines() if url.strip()]

    @task(1)
    def random_page(self):
        self.client.get("%s" % (random.choice(self.urls)))
