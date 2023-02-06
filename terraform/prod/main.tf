
locals {
  common_tags        = var.common_tags
  create_ecs_service = var.app_image == "" ? 0 : 1
  conn_str           = "http://${data.aws_alb.ssot.dns_name}:3000"
  #jb_api_url         = "https://aws-api-jobboard.workbc.ca"
  jb_api_url         = "https://api-jobboard.workbc.ca"
  jb_api_internal_url = "https://workbc-jb.b89n0c-prod.nimbus.cloud.gov.bc.ca"
}
