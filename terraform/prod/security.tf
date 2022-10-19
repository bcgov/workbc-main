# security.tf

# ALB Security Group: Edit to restrict access to the application
data "aws_security_group" "web" {
  name = "Web_sg"
}

data "aws_security_group" "app" {
  name = "App_sg"
}

data "aws_security_group" "data" {
  name = "Data_sg"
}

# Traffic to the ECS cluster should only come from the ALB
data "aws_security_group" "ecs_tasks" {
  name        = "workbc-cc-ecs-tasks-security-group"
}


data "aws_security_group" "efs_security_group" {
  name        = "workbc-cc-efs-security-group"
}