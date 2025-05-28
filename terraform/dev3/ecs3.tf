	
resource "aws_ecs_task_definition" "cron-job" {
  family                   = "workbc-drupal-cron-task3"
  execution_role_arn       = data.aws_iam_role.ecs_task_execution_role.arn
  task_role_arn            = data.aws_iam_role.workbc_container_role.arn
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = var.fargate_cpu
  memory                   = var.fargate_memory
  tags                     = var.common_tags

  container_definitions = jsonencode([
	{
		essential   = true
		name        = "cron-runner"
		image       = "${var.app_repo}/drupal-cron:1.0"
		networkMode = "awsvpc"
		
		logConfiguration = {
			logDriver = "awslogs"
			options = {
				awslogs-create-group  = "true"
				awslogs-group         = "/ecs/workbc-cron-runner3"
				awslogs-region        = var.aws_region
				awslogs-stream-prefix = "ecs"
			}
		}		

		
		environment = [
			{
				name = "Cron_Url",
        #Todo
				value = "https://d2jl0994t9760k.cloudfront.net/cron/vNlYnxjqJe1cK9KaV4DO8LNiaHrIGA9z8PfluY11h-uW79PBFQ9vsS9EVnC-Bsy6ZnBE9luRqA"
			}
		]
	}
  ])
  
}

resource "aws_cloudwatch_event_rule" "cron" {
	name = "drupal_cron_schedule3"
	schedule_expression = "rate(5 minutes)"
}

resource "aws_cloudwatch_event_target" "ecs_scheduled_task" {
  arn      = aws_ecs_cluster.main.arn
  rule     = aws_cloudwatch_event_rule.cron.id
  role_arn = data.aws_iam_role.workbc_events_role.arn

  ecs_target {
    task_count          = 1
    task_definition_arn = aws_ecs_task_definition.cron-job.arn
    launch_type         = "FARGATE"
    network_configuration {
      assign_public_ip = false
      security_groups  = [data.aws_security_group.app.id]
      subnets          = module.network.aws_subnet_ids.app.ids
    }
  }
}
