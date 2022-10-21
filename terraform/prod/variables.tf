# variables.tf

variable "target_env" {
  description = "AWS workload account env (e.g. dev, test, prod, sandbox, unclass)"
}

variable "target_aws_account_id" {
  description = "AWS workload account id"
}

variable "aws_region" {
  description = "The AWS region things are created in"
  default     = "ca-central-1"
}

variable "ecs_task_execution_role_name" {
  description = "ECS task execution role name"
  default     = "workbcEcsTaskExecutionRole"
}

#variable "ecs_auto_scale_role_name" {
#  description = "ECS auto scale role Name"
#  default     = "startupSampleEcsAutoScaleRole"
#}

#variable "az_count" {
#  description = "Number of AZs to cover in a given region"
#  default     = "2"
#}

variable "app_name" {
  description = "Name of the application"
  type        = string
  default     = "workbc"
}

variable "app_image" {
  description = "Docker image to run in the ECS cluster. _Note_: there is a blank default value, which will cause service and task resource creation to be supressed unless an image is specified."
  type        = string
  default     = ""
}

variable "app_repo" {
  description = "ECR docker image repo"
  type        = string
  default     = "266795317183.dkr.ecr.ca-central-1.amazonaws.com"
}

variable "app_port" {
  description = "Port exposed by the docker image to redirect traffic to"
  default     = 443
}

variable "app_count" {
  description = "Number of docker containers to run"
  default     = 1
}

#variable "container_name" {
#  description = "Container name"
#  default     = "sample-client-app"
#}

variable "health_check_path" {
  default = "/index.html"
}

variable "fargate_cpu" {
  description = "Fargate instance CPU units to provision (1 vCPU = 1024 CPU units)"
  default     = 2048
}

variable "fargate_memory" {
  description = "Fargate instance memory to provision (in MiB)"
  default     = 4096
}

#variable "budget_amount" {
#  description = "The amount of spend for the budget. Example: enter 100 to represent $100"
#  default     = "100.0"
#}

#variable "budget_tag" {
#  description = "The Cost Allocation Tag that will be used to build the monthly budget. "
#  default     = "Project=Startup Sample"
#}

variable "common_tags" {
  description = "Common tags for created resources"
  default = {
    Application = "WorkBC"
  }
}

variable "service_names" {
  description = "List of service names to use as subdomains"
  default     = ["workbc"]
  type        = list(string)
}

variable "alb_name" {
  description = "Name of the internal alb"
  default     = "default"
  type        = string
}

variable "cloudfront" {
  description = "enable or disable the cloudfront distrabution creation"
  type        = bool
}

variable "cloudfront_origin_domain" {
  description = "domain name of the app"
  type        = string
}
