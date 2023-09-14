# cloudfront.tf

resource "aws_cloudfront_distribution" "workbc-pdf-link-check" {

  count = var.cloudfront ? 1 : 0


  origin {
    domain_name = aws_s3_bucket.workbc_s33.bucket_regional_domain_name
	origin_id = "PDF-Link-Check-Results"
	origin_access_control_id = aws_cloudfront_origin_access_control.oac.id
  }

  enabled         = true
  is_ipv6_enabled = true
  comment         = "WorkBC PDF Link Check"

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
	  
  }
	
  

  price_class = "PriceClass_100"

  restrictions {
    geo_restriction {
      restriction_type = "whitelist"
      locations = ["CA"]
    }
  }

  viewer_certificate {
    cloudfront_default_certificate = true
  }

  tags = var.common_tags
  
}

output "cloudfront_url2" {
  value = "https://${aws_cloudfront_distribution.workbc-pdf-link-check[0].domain_name}"
}
