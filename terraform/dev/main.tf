
locals {
  common_tags        = var.common_tags
  create_ecs_service = var.app_image == "" ? 0 : 1
  conn_str           = "http://${data.aws_alb.ssot.dns_name}:3000"
}
