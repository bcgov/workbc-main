#TODO
/*
resource "aws_s3_bucket" "workbc_s33" {
  bucket = "workbc-pdf-link-check-bucket"
}

/*resource "aws_s3_bucket_acl" "workbc_s33_acl" {
  bucket = aws_s3_bucket.workbc_s33.id
  acl    = "private"
}*/

resource "aws_s3_bucket_policy" "allow_access_from_other_accounts_and_cloudfront" {
  bucket = aws_s3_bucket.workbc_s33.id
  policy = data.aws_iam_policy_document.allow_access_from_other_accounts_and_cloudfront.json
}

data "aws_iam_policy_document" "allow_access_from_other_accounts_and_cloudfront" {
  statement {
  
    sid = "AllowCloudFrontServicePrincipal"
    
    principals {
      type        = "Service"
      identifiers = ["cloudfront.amazonaws.com"]
    }
	  
    actions = ["s3:GetObject"]

    resources = [
      "${aws_s3_bucket.workbc_s33.arn}/*",
    ]
	
	condition {
	  test = "StringEquals"
	  variable = "AWS:SourceArn"
	  values = ["${aws_cloudfront_distribution.workbc-pdf-link-check[0].arn}"]
	}
  }
  
  statement {
    
    principals {
      type        = "AWS"
      identifiers = ["${aws_iam_role.workbc_container_role.arn}"]
    }
	  
    principals {
      type        = "AWS"
      identifiers = ["arn:aws:iam::054099626264:role/workbc_container_role"]
    }

    principals {
      type        = "AWS"
      identifiers = ["arn:aws:iam::846410483170:role/workbc_container_role"]
    }

    actions = [
      "s3:ListBucket",
      "s3:GetBucketLocation",
    ]

    resources = [
      "${aws_s3_bucket.workbc_s33.arn}",
    ]
  }
	
  statement {
    principals {
      type        = "AWS"
      identifiers = ["${aws_iam_role.workbc_container_role.arn}"]
    }
	  
    principals {
      type        = "AWS"
      identifiers = ["arn:aws:iam::054099626264:role/workbc_container_role"]
    }

    principals {
      type        = "AWS"
      identifiers = ["arn:aws:iam::846410483170:role/workbc_container_role"]
    }

    actions = [
      "s3:GetObjectAttributes",
      "s3:GetObject",
      "s3:PutObject",
      "s3:ListMultipartUploadParts",
      "s3:AbortMultipartUpload",
    ]

    resources = [
      "${aws_s3_bucket.workbc_s33.arn}/*",
    ]
  }
}
*/