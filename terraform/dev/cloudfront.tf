# cloudfront.tf

resource "random_integer" "cf_origin_id" {
  min = 1
  max = 100
}

resource "aws_cloudfront_origin_access_control" "oac" {
  name = "oac"
  description = "OAC Policy"
  origin_access_control_origin_type = "s3"
  signing_behavior = "always"
  signing_protocol = "sigv4"
}

resource "aws_cloudfront_cache_policy" "custom" {
  name	      = "WorkBC-cache-policy"
  comment     = "WorkBC main site cache policy"
  default_ttl = 300
  max_ttl     = 31536000
  min_ttl     = 1
  parameters_in_cache_key_and_forwarded_to_origin {
    cookies_config {
      cookie_behavior = "all"
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
    cookie_behavior = "none"
  }
    headers_config {
        header_behavior = "whitelist"
        headers {
            items = ["CloudFront-Is-Tablet-Viewer","CloudFront-Is-Mobile-Viewer","CloudFront-Is-Desktop-Viewer"]
        }
    }
        query_strings_config {
            query_string_behavior = "none"
        }

    }

data "aws_cloudfront_cache_policy" "custom" {
    name = "WorkBC-cache-policy"
}

data "aws_cloudfront_origin_request_policy" "custom" {
    name = "WorkBC-origin-request-policy"
}



resource "aws_cloudfront_distribution" "workbc" {

  count = var.cloudfront ? 1 : 0

  origin {
    custom_origin_config {
      http_port              = 80
      https_port             = 443
      origin_protocol_policy = "https-only"
      origin_ssl_protocols = ["TLSv1.2"]
      origin_keepalive_timeout = 60
      origin_read_timeout = 60
    }

    domain_name = var.cloudfront_origin_domain
    origin_id   = random_integer.cf_origin_id.result
	
	custom_header {
	  name = "X-Forwarded-Host"
	  #value = "aws.workbc.ca"
	  #value = "aws-dev.workbc.ca"
    value = "dev.workbc.ca"	
	}
	
  }
	
  origin {
        domain_name = aws_s3_bucket.workbc_s32.bucket_regional_domain_name
	origin_id = "SDPR-Contents"
	origin_access_control_id = aws_cloudfront_origin_access_control.oac.id
  }

  enabled         = true
  is_ipv6_enabled = true
  comment         = "WorkBC"

  default_cache_behavior {
    allowed_methods = [
      "DELETE",
      "GET",
      "HEAD",
      "OPTIONS",
      "PATCH",
      "POST",
    "PUT"]
    cached_methods = ["GET", "HEAD"]

    target_origin_id = random_integer.cf_origin_id.result
    cache_policy_id = aws_cloudfront_cache_policy.custom.id
    origin_request_policy_id = aws_cloudfront_origin_request_policy.custom.id


    forwarded_values {
      query_string = true

      cookies {
        forward = "all"
      }
    }

    viewer_protocol_policy = "redirect-to-https"
    min_ttl                = 0
    default_ttl            = 86400
    max_ttl                = 31536000
	
    # SimpleCORS
    response_headers_policy_id = "60669652-455b-4ae9-85a4-c4c02393f86c"
	  
	  #This cloudfront function redirects aws.workbc.ca to dev.workbc.ca -- 301
    function_association {
      event_type   = "viewer-request"
      function_arn = "arn:aws:cloudfront::873424993519:function/pearldevcfredirect"
    }
  }
	
  ordered_cache_behavior {
        path_pattern = "/getmedia/*"
        allowed_methods = [
        "DELETE",
        "GET",
        "HEAD",
        "OPTIONS",
        "PATCH",
        "POST",
        "PUT"]
        cached_methods = ["GET", "HEAD"]
	target_origin_id = "SDPR-Contents"
	cache_policy_id = "658327ea-f89d-4fab-a63d-7e88639e58f6"
	viewer_protocol_policy = "redirect-to-https"
  }
  
    ordered_cache_behavior {
        path_pattern = "/WorkBC-Template/*"
        allowed_methods = [
        "DELETE",
        "GET",
        "HEAD",
        "OPTIONS",
        "PATCH",
        "POST",
        "PUT"]
        cached_methods = ["GET", "HEAD"]
        target_origin_id = "SDPR-Contents"
	cache_policy_id = "658327ea-f89d-4fab-a63d-7e88639e58f6"
	viewer_protocol_policy = "redirect-to-https"
  }

  price_class = "PriceClass_100"

  restrictions {
    geo_restriction {
      #restriction_type = "whitelist"
      #locations = ["CA"]
      restriction_type = "none"
      locations        = []
    }
  }

  tags = var.common_tags
  
  #aliases = ["aws.workbc.ca"]
  #aliases = ["aws-dev.workbc.ca", "aws.workbc.ca"]
  aliases = ["dev.workbc.ca", "aws.workbc.ca"]	

  viewer_certificate {
    acm_certificate_arn = "arn:aws:acm:us-east-1:873424993519:certificate/d624b356-1ebd-496c-b4da-ba9b489baafc"
    minimum_protocol_version = "TLSv1.2_2021"
    ssl_support_method = "sni-only"
  }
	
    # Associate the CloudFront distribution with the existing WAF web ACL by ARN
    # This regulates users' frequent access to the website
    web_acl_id = "arn:aws:wafv2:us-east-1:873424993519:global/webacl/workbc-dev-waf/2bc96095-ff24-4602-95fe-484051c3271e"	 
}

output "cloudfront_url" {
  value = "https://${aws_cloudfront_distribution.workbc[0].domain_name}"
}

