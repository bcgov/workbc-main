resource "aws_s3_bucket" "workbc_s32" {
  bucket = "workbc-dev-bucket"
}

resource "aws_s3_bucket_acl" "workbc_s32_acl" {
  bucket = aws_s3_bucket.workbc_s32.id
  acl    = "private"
}

resource "aws_s3_bucket_policy" "allow_access_from_cloudfront" {
  bucket = aws_s3_bucket.workbc_s32.id
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
      "${aws_s3_bucket.workbc_s32.arn}/*",
    ]
	
	condition {
	  test = "StringEquals"
	  variable = "AWS:SourceArn"
	  values = ["${aws_cloudfront_distribution.workbc.arn}"]
	}
  }
	

}
