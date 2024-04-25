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
		essential   = true
		name        = "pdf"
		image       = "${var.app_repo}/pdf:0.9"
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
				value = "${aws_rds_cluster.postgres2.endpoint}"
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

resource "aws_cloudwatch_event_rule" "cron2" {
	name = "pdf_cron_schedule"
	schedule_expression = "cron(0 2 1 * ? *)"
	#is_enabled = false
}

resource "aws_cloudwatch_event_target" "ecs_scheduled_task2" {
  arn      = aws_ecs_cluster.main.arn
  rule     = aws_cloudwatch_event_rule.cron2.id
  role_arn = aws_iam_role.workbc_events_role.arn

  ecs_target {
    task_count          = 1
    task_definition_arn = aws_ecs_task_definition.pdf-link-job.arn
    launch_type         = "FARGATE"
    network_configuration {
      assign_public_ip = false
      security_groups  = [data.aws_security_group.app.id]
      subnets          = module.network.aws_subnet_ids.app.ids
    }
  }
}
