resource "aws_synthetics_canary" "workbc-monitor" {
  name                 = "workbcmonitor2"
  artifact_s3_location = "s3://cw-syn-results-873424993519-ca-central-1/canary/ca-central-1/workbcmonitor2-572-6ce5cc6d373e"
  execution_role_arn   = "arn:aws:iam::873424993519:role/service-role/CloudWatchSyntheticsRole-workbcmonitor2-572-6ce5cc6d373e"
  handler              = "apiCanaryBlueprint.handler"
  zip_file             = "cwsyn-workbcmonitor2-ee246144-167d-4242-852f-5305034f963f-162957ef-bb5e-4f35-9680-cb70463f5fed.zip"
  runtime_version      = "syn-nodejs-puppeteer-5.1"

  schedule {
    expression = "rate(5 minutes)"
  }
}
