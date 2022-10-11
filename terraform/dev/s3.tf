resource "aws_s3_bucket" "workbc_s3" {
  bucket = "workbc-backup-restore-bucket"
}

resource "aws_s3_bucket_acl" "workbc_s3_acl" {
  bucket = aws_s3_bucket.workbc_s3.id
  acl    = "private"
}