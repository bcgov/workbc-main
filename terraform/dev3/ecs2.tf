# ecs.tf

resource "aws_ecs_cluster" "solr3" {
  name               = "solr-cluster3"
  #capacity_providers = ["FARGATE_SPOT"]
}

resource "aws_ecs_cluster_capacity_providers" "solr3" {
    cluster_name =  aws_ecs_cluster.main.name
    capacity_providers = ["FARGATE_SPOT"]

    default_capacity_provider_strategy {
      weight            = 100
      capacity_provider = "FARGATE_SPOT"	
  }
}

resource "aws_ecs_task_definition" "solr3" {
  count                    = local.create_ecs_service
  family                   = "solr-task3"
  execution_role_arn       = data.aws_iam_role.ecs_task_execution_role.arn
  task_role_arn            = data.aws_iam_role.workbc_container_role.arn
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = var.fargate_cpu
  memory                   = var.fargate_memory
  tags                     = var.common_tags
  volume {
    name = "data"
    efs_volume_configuration  {
        file_system_id = aws_efs_file_system.solr3.id
	transit_encryption = "ENABLED"
	authorization_config {
	    iam = "ENABLED"
	    access_point_id = aws_efs_access_point.solr3.id
	}
    }
  }

  container_definitions = jsonencode([
  {
		essential   = true
		name        = "solr"
		image       = "${var.app_repo}/solr:0.3"
		networkMode = "awsvpc"
		
		logConfiguration = {
			logDriver = "awslogs"
			options = {
				awslogs-create-group  = "true"
				awslogs-group         = "/ecs/${var.app_name}-dev3/solr3"
				awslogs-region        = var.aws_region
				awslogs-stream-prefix = "ecs"
			}
		}		

		portMappings = [
			{
				hostPort = 8983
				protocol = "tcp"
				containerPort = 8983
			}
		]
		
		environment = [
			{
				name = "SOLR_CORE_NAME",
				value = "workbc"
			},
			{
				name = "SOLR_CORE_NAME2",
				value = "CareerTrek"
			}
		]

		mountPoints = [
			{
				containerPath = "/var/solr/data",
				sourceVolume = "data"
			}
		]
		volumesFrom = []	
	}
  ])
}

resource "aws_ecs_service" "solr3" {
  count                             = local.create_ecs_service
  name                              = "solr-service3"
  cluster                           = aws_ecs_cluster.solr3.id
  task_definition                   = aws_ecs_task_definition.solr3[count.index].arn
  desired_count                     = var.app_count
  enable_ecs_managed_tags           = true
  propagate_tags                    = "TASK_DEFINITION"
  health_check_grace_period_seconds = 60
  wait_for_steady_state             = false
  enable_execute_command            = true
  deployment_maximum_percent        = 100
  deployment_minimum_healthy_percent = 0


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
    target_group_arn = aws_alb_target_group.solr3.id
    container_name   = "solr"
    container_port   = 8983
  }

  depends_on = [aws_alb_listener.solr3, aws_iam_role_policy_attachment.ecs_task_execution_role]

  tags = var.common_tags
}
