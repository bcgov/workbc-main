# Route53 health check
resource "aws_route53_health_check" "workbc" {
  fqdn              = "workbc.b89n0c-prod.nimbus.cloud.gov.bc.ca"
  port              = 443
  type              = "HTTPS"
  resource_path     = "/"
  failure_threshold = "5"
  request_interval  = "30"

  tags = {
    Name = "workbc-alb-health-check"
  }
}


resource "aws_iam_role" "lambda_role" {
  name               = "route53_HealthCheck"
  assume_role_policy = <<EOF
{
"Version": "2012-10-17",
"Statement": [
   {
    "Action": "sts:AssumeRole",
    "Principal": {
    "Service": "lambda.amazonaws.com"
    },
    "Effect": "Allow",
    "Sid": ""
   }
]
}
EOF
}

resource "aws_iam_role_policy" "lambda_policy" {
  name     = "route53_HealthCheck_policy"
  role     = aws_iam_role.lambda_role.id
  policy = jsonencode({
    "Version" : "2012-10-17",
    "Statement" : [
      {
        "Effect" : "Allow",
        "Action" : [
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ],
        "Resource" : "*"
      },
      {
        "Sid" : "GetHealthCheck",
        "Effect" : "Allow",
        "Action" : [
          "route53:GetHealthCheckStatus",
          "route53:GetHealthCheck"
        ],
        "Resource" : "${aws_route53_health_check.workbc.arn}"
      }
    ]

  })
}

# Create an archive form the Lambda source code,
# filtering out unneeded files.
data "archive_file" "lambda_source_package" {
  type        = "zip"
  source_dir  = "${path.module}/python/"
  output_path = "${path.module}/python/healthCheck.zip"

  excludes = [
    "__pycache__",
    "core/__pycache__",
    "tests"
  ]

  # This is necessary, since archive_file is now a
  # `data` source and not a `resource` anymore.
  # Use `depends_on` to wait for the "install dependencies"
  # task to be completed.
  depends_on = [null_resource.canary_install_dependencies]
}

resource "null_resource" "lamda_install_dependencies" {
  provisioner "local-exec" {
    command = "pip3 install -r ${path.module}/python/requirements.txt -t ${path.module}/ --upgrade"
  }
  # Only re-run this if the dependencies or their versions
  # have changed since the last deployment with Terraform
  triggers = {
    dependencies_versions = filemd5("${path.module}/requirements.txt")
  }
}

resource "aws_lambda_function" "healthcheck" {
  filename         = data.archive_file.lambda_source_package.output_path
  function_name    = "route53_healthCheck"
  role             = aws_iam_role.lambda_role.arn
  handler          = "lambda_function.lambda_handler"
  description      = "Lambda Function to survey the route53 health check of the alb"
  runtime          = "python3.8"
  timeout          = 30
  source_code_hash = data.archive_file.lambda_source_package.output_base64sha256

  environment {
    variables = {
      HealthCheckId = aws_route53_health_check.workbc.id
    }
  }

  tags = local.common_tags
}

resource "aws_cloudwatch_event_rule" "schedule" {
  name                = "monitoring_lambda_trigger"
  description         = "Schedule for the monitoring of the route53 healthcheck by the Lambda"
  schedule_expression = "rate(5 minutes)"
}

resource "aws_cloudwatch_event_target" "schedule_lambda" {
  rule      = aws_cloudwatch_event_rule.schedule.name
  target_id = "healthcheck"
  arn       = aws_lambda_function.healthcheck.arn
}

resource "aws_lambda_permission" "allow_events_bridge_to_run_lambda" {
  statement_id  = "AllowExecutionFromCloudWatch"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.healthcheck.function_name
  principal     = "events.amazonaws.com"
}

resource "aws_cloudwatch_metric_alarm" "canary_lambda" {
  alarm_name          = "Canary_lamdbda_failed_executions"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "1"
  metric_name         = "Errors"
  namespace           = "AWS/Lambda"
  period              = "120"
  statistic           = "Maximum"
  threshold           = "0"
  alarm_description   = "Monitor canary lambda for errors"
  alarm_actions       = ["arn:aws:sns:ca-central-1:873424993519:Synthetics-WorkBC2"]
  dimensions = {
    FunctionName = aws_lambda_function.healthcheck.function_name
  }
}
