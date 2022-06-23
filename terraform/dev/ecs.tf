# ecs.tf

resource "aws_ecs_cluster" "main" {
  name               = "workbc-cluster"
  capacity_providers = ["FARGATE_SPOT"]

  default_capacity_provider_strategy {
    capacity_provider = "FARGATE_SPOT"
    weight            = 100
  }

  tags = var.common_tags
}

resource "aws_ecs_task_definition" "app" {
  count                    = local.create_ecs_service
  family                   = "workbc-task"
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
		name        = "init"
		image       = var.app_image
		networkMode = "awsvpc"
		entryPoint = ["sh",	"-c"]
		command = ["cp -rf /code/. /app; ln -s /contents/public /app/web/sites/default/files; ln -s /contents/private /app/private"]
		mountPoints = [
			{
				containerPath = "/contents",
				sourceVolume = "contents"
			},
			{
				containerPath = "/app",
				sourceVolume = "app"
			}
		]
		volumesFrom = []
		logConfiguration = {
			logDriver = "awslogs"
			options = {
				awslogs-create-group  = "true"
				awslogs-group         = "/ecs/${var.app_name}"
				awslogs-region        = var.aws_region
				awslogs-stream-prefix = "ecs"
			}
		}
	},
	{
		essential   = true
		name        = "drupal"
		image       = var.app_image
		networkMode = "awsvpc"
		
		logConfiguration = {
			logDriver = "awslogs"
			options = {
				awslogs-create-group  = "true"
				awslogs-group         = "/ecs/${var.app_name}"
				awslogs-region        = var.aws_region
				awslogs-stream-prefix = "ecs"
			}
		}		

		portMappings = [
			{
				hostPort = 9000
				protocol = "tcp"
				containerPort = 9000
			}
		]
		
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
				name = "AWS_BUILD_NAME",
				value = "aws"
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
			},
			{
				containerPath = "/app",
				sourceVolume = "app"
			}
		]
		volumesFrom = []
		
		dependsOn = [
			{
				containerName = "init"
				condition = "COMPLETE"
			}
		]
	},
	{
		essential   = true
		name        = "nginx"
		image       = "${var.app_repo}/nginx:2.0"
		networkMode = "awsvpc"
		
		logConfiguration = {
			logDriver = "awslogs"
			options = {
				awslogs-create-group  = "true"
				awslogs-group         = "/ecs/${var.app_name}"
				awslogs-region        = var.aws_region
				awslogs-stream-prefix = "ecs"
			}
		}		

		portMappings = [
			{
				hostPort = 443
				protocol = "tcp"
				containerPort = 443
			}
		]

		mountPoints = [
			{
				containerPath = "/contents",
				sourceVolume = "contents"
			},
			{
				containerPath = "/app",
				sourceVolume = "app"
			}
		]
		volumesFrom = []
		
		dependsOn = [
			{
				containerName = "init"
				condition = "COMPLETE"
			}
		]

	},
	{
		essential   = false
		name        = "drush"
		image       = var.app_image
		networkMode = "awsvpc"

		entryPoint = ["sh", "-c"]
		command = ["drush cr; drush updb -y; drush cr; drush cim -y;"]
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
				name = "AWS_BUILD_NAME",
				value = "aws"
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
			},
			{
				containerPath = "/app",
				sourceVolume = "app"
			}
		]
		volumesFrom = []
		dependsOn = [
			{
				containerName = "init"
				condition = "COMPLETE"
			}
		]
	}
  ])
}

resource "aws_ecs_service" "main" {
  count                             = local.create_ecs_service
  name                              = "workbc-service"
  cluster                           = aws_ecs_cluster.main.id
  task_definition                   = aws_ecs_task_definition.app[count.index].arn
  desired_count                     = var.app_count
  enable_ecs_managed_tags           = true
  propagate_tags                    = "TASK_DEFINITION"
  health_check_grace_period_seconds = 60
  wait_for_steady_state             = false
  enable_execute_command            = true


  capacity_provider_strategy {
    capacity_provider = "FARGATE_SPOT"
    weight            = 100
  }


  network_configuration {
    security_groups  = [data.aws_security_group.app.id]
    subnets          = module.network.aws_subnet_ids.app.ids
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = aws_alb_target_group.app.id
    container_name   = "nginx"
    container_port   = var.app_port
  }

  depends_on = [data.aws_alb_listener.front_end, aws_iam_role_policy_attachment.ecs_task_execution_role]

  tags = var.common_tags
}
