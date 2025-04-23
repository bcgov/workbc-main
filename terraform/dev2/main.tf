
locals {
  common_tags        = var.common_tags
  create_ecs_service = var.app_image == "" ? 0 : 1
  #conn_str           = "http://${data.aws_alb.ssot2.dns_name}:3000"
  conn_str           = "http://${data.aws_alb.ssot.dns_name}:3000"
  #conn_str           = "https://workbc-ssot.b89n0c-dev.nimbus.cloud.gov.bc.ca"
  jb_api_url         = "https://dev2-api-jobboard.workbc.ca"
  jb_api_internal_url = "https://workbc-jb-dev2.b89n0c-dev.nimbus.cloud.gov.bc.ca"
}
