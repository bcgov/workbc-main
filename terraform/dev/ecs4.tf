resource "aws_ecs_task_definition" "pdf-link-job" {
  family                   = "workbc-pdf-link-task"
  execution_role_arn       = aws_iam_role.ecs_task_execution_role.arn
  task_role_arn            = aws_iam_role.workbc_container_role.arn
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = var.fargate_cpu
  memory                   = var.fargate_memory
  tags                     = var.common_tags
  volume {
    name = "contents"
    efs_volume_configuration  {
        file_system_id = aws_efs_file_system.workbc.id
    }
  }
  volume {
    name = "app"
  }

  container_definitions = jsonencode([
	{
		essential   = false
		name        = "pdf"
		image       = "${var.app_repo}/pdf:0.8"
		networkMode = "awsvpc"

		logConfiguration = {
			logDriver = "awslogs"
			options = {
				awslogs-create-group  = "true"
				awslogs-group         = "/ecs/${var.app_name}/pdf"
				awslogs-region        = var.aws_region
				awslogs-stream-prefix = "ecs"
			}
		}

		environment = [
			{
				name = "POSTGRES_PORT",
				value = "5432"
			},
			{
				name = "POSTGRES_DB",
				value = "drupal"
			},
			{
				name = "POSTGRES_HOST",
				value = "${data.aws_rds_cluster.postgres.endpoint}"
			}			
		]
		secrets = [
			{
				name = "POSTGRES_USER",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:username::"
			},
			{
				name = "POSTGRES_PASSWORD",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:password::"
			}
		]

		mountPoints = [
			{
				containerPath = "/contents",
				sourceVolume = "contents"
			}
		]
		volumesFrom = []

	}
  ])
}