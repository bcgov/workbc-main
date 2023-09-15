resource "aws_synthetics_canary" "workbc-monitor" {
  name                 = "workbc-monitor"
  artifact_s3_location = "s3://cw-syn-results-846410483170-ca-central-1/canary/ca-central-1/workbc-monitor-0ee-9322b2780894/"
  execution_role_arn   = "arn:aws:iam::846410483170:role/service-role/CloudWatchSyntheticsRole-workbc-monitor-0ee-9322b2780894"
  handler              = "apiCanaryBlueprint.handler"
  zip_file             = "cwsyn-workbc-monitor-9e6d6483-f35b-4278-a2f2-3923b0ba4d18-3fe0e213-2ee4-42e6-8221-68283779a4d7.zip"
  runtime_version      = "syn-nodejs-puppeteer-5.1"

  schedule {
    expression = "rate(5 minutes)"
  }
  timeout_in_seconds = 300
}