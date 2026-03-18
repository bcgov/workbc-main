resource "aws_s3_bucket" "workbc_s33_dev3" {
  bucket = "workbc-dev3-bucket"
}

resource "aws_s3_bucket_acl" "workbc_s33_acl" {
  bucket = aws_s3_bucket.workbc_s33_dev3.id
  acl    = "private"
}

resource "aws_s3_bucket_policy" "allow_access_from_cloudfront" {
  bucket = aws_s3_bucket.workbc_s33_dev3.id
  policy = data.aws_iam_policy_document.allow_access_from_cloudfront.json
}

data "aws_iam_policy_document" "allow_access_from_cloudfront" {
  statement {
  
    sid = "AllowCloudFrontServicePrincipal"
    
    principals {
      type        = "Service"
      identifiers = ["cloudfront.amazonaws.com"]
    }
	  
    actions = ["s3:GetObject"]

    resources = [
      "${aws_s3_bucket.workbc_s33_dev3.arn}/*",
    ]
	
	condition {
	  test = "StringEquals"
	  variable = "AWS:SourceArn"
	  values = ["${aws_cloudfront_distribution.workbc3[0].arn}"]
	}
  }
	

}
