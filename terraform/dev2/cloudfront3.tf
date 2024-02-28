#cloudfront.tf
/*
resource "aws_cloudfront_cache_policy" "custom" {
  name	      = "WorkBC-cache-policy"
  comment     = "WorkBC main site cache policy"
  default_ttl = 300
  max_ttl     = 31536000
  min_ttl     = 1
  parameters_in_cache_key_and_forwarded_to_origin {
    cookies_config {
      cookie_behavior = "none"
    }
    headers_config {
      header_behavior = "none"
    }
    query_strings_config {
       query_string_behavior = "all"
    }
    enable_accept_encoding_brotli = true
    enable_accept_encoding_gzip = true 

  }
}

resource "aws_cloudfront_origin_request_policy" "custom" {
  name    = "WorkBC-origin-request-policy"
  comment = "Origin request settings to test CF tablet CF mobile CF desktop"
  cookies_config {
    cookie_behavior = "all"
  }
    headers_config {
        header_behavior = "allExcept"
        headers {
            items = ["Host"]
        }
    }
        query_strings_config {
            query_string_behavior = "none"
        }

    }
*/