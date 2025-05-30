# ecs.tf

resource "aws_ecs_cluster" "main" {
  name               = "workbc-cluster3"
  #capacity_providers = ["FARGATE_SPOT"]
}

resource "aws_ecs_cluster_capacity_providers" "main" {
    cluster_name =  aws_ecs_cluster.main.name
    capacity_providers = ["FARGATE_SPOT"]

    default_capacity_provider_strategy {
      weight            = 100
      capacity_provider = "FARGATE_SPOT"	
  }

  #tags = var.common_tags
}

resource "aws_ecs_task_definition" "app" {
  count                    = local.create_ecs_service
  family                   = "workbc-task3"
  execution_role_arn       = data.aws_iam_role.ecs_task_execution_role.arn
  task_role_arn            = data.aws_iam_role.workbc_container_role.arn
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = var.fargate_cpu
  memory                   = var.fargate_memory
  tags                     = var.common_tags
  volume {
    name = "contents"
    efs_volume_configuration  {
        file_system_id = aws_efs_file_system.workbc3.id
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
				awslogs-group         = "/ecs/${var.app_name}-dev3/init"
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
				awslogs-group         = "/ecs/${var.app_name}-dev3/drupal"
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
				value = "drupal_dev3"
			},
			{
				name = "POSTGRES_SSOT",
				value = "ssot"
			},
			{
				name = "AWS_BUILD_NAME",
				value = "aws"
			},
			{
				name = "POSTGRES_HOST",
				value = "${data.aws_rds_cluster.postgres.endpoint}"
			},
			{
				name = "SSOT_URL",
				value = "${local.conn_str}"
			},
			{
				name = "JOBBOARD_API_URL",
				value = "${local.jb_api_url}"
			},
			{
				name = "JOBBOARD_API_INTERNAL_URL",
				value = "${local.jb_api_internal_url}"
			},
			{
				name = "PROJECT_ENVIRONMENT",
				value = "aws-dev3"
			},
			{
				name = "REDIS_HOST",
				value = "${aws_elasticache_replication_group.workbc3_redis_rg.primary_endpoint_address}"
			},
			{
				name = "REDIS_PORT",
				value = "6379"
			},
			{
				name = "CF_DIST_ID",
				value = "${aws_cloudfront_distribution.workbc3[0].id}"
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
			},
			{
				name = "POSTGRES_ADM_USER",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:adm_username::"
			},
			{
				name = "POSTGRES_ADM_PWD",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:adm_password::"
			},
			{
				name = "JOBBOARD_GOOGLE_MAPS_KEY",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds2.arn}:gm_ref::"
			},
			{
				name = "DRUPAL_ADM_PWD",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:drupal_adm_password::"
			},
			{
				name = "LMMU_PWD",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:lmmu_password::"
			},
		        {
				name = "LMMU_USERNAME",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:lmmu_username::"
			},
			{
				name = "GITHUB_API_TOKEN",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:github_api_token::"
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
		image       = "${var.app_repo}/nginx:2.2"
		networkMode = "awsvpc"

		logConfiguration = {
			logDriver = "awslogs"
			options = {
				awslogs-create-group  = "true"
				awslogs-group         = "/ecs/${var.app_name}-dev3/nginx"
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

		logConfiguration = {
			logDriver = "awslogs"
			options = {
				awslogs-create-group  = "true"
				awslogs-group         = "/ecs/${var.app_name}-dev3/drush"
				awslogs-region        = var.aws_region
				awslogs-stream-prefix = "ecs"
			}
		}

		entryPoint = ["sh", "-c"]
		command = ["drush cr; drush updb -y; drush cim -y; drush deploy:hook -y; drush cr;"]
		environment = [
			{
				name = "POSTGRES_PORT",
				value = "5432"
			},
			{
				name = "POSTGRES_DB",
				value = "drupal_dev3"
			},
			{
				name = "POSTGRES_SSOT",
				value = "ssot"
			},
			{
				name = "AWS_BUILD_NAME",
				value = "aws"
			},
			{
				name = "POSTGRES_HOST",
				value = "${data.aws_rds_cluster.postgres.endpoint}"
			},
			{
				name = "SSOT_URL",
				value = "${local.conn_str}"
			},
			{
				name = "JOBBOARD_API_URL",
				value = "${local.jb_api_url}"
			},
			{
				name = "JOBBOARD_API_INTERNAL_URL",
				value = "${local.jb_api_internal_url}"
			},
			{
				name = "PROJECT_ENVIRONMENT",
				value = "aws-dev3"
			},
			{
				name = "REDIS_HOST",
				value = "${aws_elasticache_replication_group.workbc3_redis_rg.primary_endpoint_address}"
			},
			{
				name = "REDIS_PORT",
				value = "6379"
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
			},
			{
				name = "JOBBOARD_GOOGLE_MAPS_KEY",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds2.arn}:gm_ref::"
			},
			{
				name = "POSTGRES_ADM_USER",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:adm_username::"
			},
			{
				name = "POSTGRES_ADM_PWD",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:adm_password::"
			},
			{
				name = "DRUPAL_ADM_PWD",
				valueFrom = "${data.aws_secretsmanager_secret_version.creds.arn}:drupal_adm_password::"
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
	}/*,
	{
		essential   = false
		name        = "pdf"
		image       = "${var.app_repo}/pdf:0.8"
		networkMode = "awsvpc"

		logConfiguration = {
			logDriver = "awslogs"
			options = {
				awslogs-create-group  = "true"
				awslogs-group         = "/ecs/${var.app_name}-dev3/pdf"
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
				value = "drupal_dev3"
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

	}*/
  ])
}

resource "aws_ecs_service" "main" {
  count                             = local.create_ecs_service
  name                              = "workbc-service3"
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
