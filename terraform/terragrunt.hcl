locals {
  tfc_hostname     = "app.terraform.io"
  tfc_organization = "bcgov"
  project          = get_env("LICENSE_PLATE")
  environment      = reverse(split("/", get_terragrunt_dir()))[0]
  app_image        = get_env("app_image", "")
  app_repo         = split("/", get_env("app_image"))[0]
}

generate "remote_state" {
  path      = "backend.tf"
  if_exists = "overwrite"
  contents  = <<EOF
terraform {
  backend "s3" {
    bucket = "terraform-remote-state-${local.project}-${local.environment}"
    key = "workbc-main.tfstate"
    region = "ca-central-1"
    dynamodb_table = "terraform-remote-state-lock-${local.project}"
    encrypt = true
  }
}
EOF
}

generate "tfvars" {
  path              = "terragrunt.auto.tfvars"
  if_exists         = "overwrite"
  disable_signature = true
  contents          = <<-EOF
  app_image = "${local.app_image}"
  app_repo = "${local.app_repo}"
EOF
}

generate "provider" {
  path      = "provider.tf"
  if_exists = "overwrite"
  contents  = <<EOF
  provider "aws" {
    region  = var.aws_region
  }
EOF
}
