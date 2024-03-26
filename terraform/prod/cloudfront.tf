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
/*
data "aws_cloudfront_cache_policy" "custom" {
    name = "WorkBC-cache-policy"
    depends_on = [aws_cloudfront_cache_policy.custom]
}

data "aws_cloudfront_origin_request_policy" "custom" {
    name = "WorkBC-origin-request-policy"
    depends_on = [aws_cloudfront_origin_request_policy.custom]
}*/


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
	  value = "www.workbc.ca"
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

    #forwarded_values {
    # query_string = true

    #  cookies {
    #    forward = "all"
    #  }
    #}

    viewer_protocol_policy = "redirect-to-https"
    min_ttl                = 0
    default_ttl            = 86400
    max_ttl                = 31536000
	
    # SimpleCORS
    response_headers_policy_id = "60669652-455b-4ae9-85a4-c4c02393f86c"

  	#This cloudfront function redirects workbc.ca to www.workbc.ca -- 301
    function_association {
      event_type   = "viewer-request"
      function_arn = "arn:aws:cloudfront::846410483170:function/redirectApex2www"
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
      restriction_type = "none"
      locations        = []
    }
  }

  tags = var.common_tags
  
  aliases = ["www.workbc.ca", "workbc.ca"]

  viewer_certificate {
    acm_certificate_arn = "arn:aws:acm:us-east-1:846410483170:certificate/2e5a86c9-e425-4e9f-ae5e-f55dbbb07be9"
    minimum_protocol_version = "TLSv1.2_2021"
    ssl_support_method = "sni-only"
  }
    # Associate the CloudFront distribution with the existing WAF web ACL by ARN
    # This regulates users' frequent access to the website  
    web_acl_id = "arn:aws:wafv2:us-east-1:846410483170:global/webacl/workbc-protection/fcd24911-4d7d-4257-b05c-7988b6b16dc2"
    depends_on = [aws_cloudfront_cache_policy.custom, aws_cloudfront_origin_request_policy.custom]
}

output "cloudfront_url" {
  value = "https://${aws_cloudfront_distribution.workbc[0].domain_name}"
}

