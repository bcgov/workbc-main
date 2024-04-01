# rds

resource "aws_db_subnet_group" "data_subnet" {
  name                   = "data-subnet-prod"
  subnet_ids             = module.network.aws_subnet_ids.data.ids

  tags = var.common_tags
}

resource "aws_rds_cluster" "postgres2" {
  cluster_identifier      = "workbc-postgres-cluster"
  engine                  = "aurora-postgresql"
  engine_version          = "13.8"
  master_username         = local.db_creds.adm_username
  master_password         = local.db_creds.adm_password
  backup_retention_period = 5
  preferred_backup_window = "07:00-09:00"
  db_subnet_group_name    = aws_db_subnet_group.data_subnet.name
  kms_key_id              = data.aws_kms_key.workbc-kms-key.arn
  storage_encrypted       = true
  vpc_security_group_ids  = [data.aws_security_group.data.id]
  skip_final_snapshot     = true
  final_snapshot_identifier = "workbc-finalsnapshot"
  multi_az = true
  
  serverlessv2_scaling_configuration {
    max_capacity = 16.0
    min_capacity = 4.0
  }

  tags = var.common_tags
}

# create this manually
data "aws_secretsmanager_secret_version" "creds" {
  secret_id = "workbc-cc-db-creds"
}

locals {
  db_creds = jsondecode(
    data.aws_secretsmanager_secret_version.creds.secret_string
  )
}
  
resource "aws_rds_cluster_instance" "postgres2" {
  count = 2
  cluster_identifier = aws_rds_cluster.postgres2.id
  instance_class     = "db.serverless"
  engine             = aws_rds_cluster.postgres2.engine
  engine_version     = aws_rds_cluster.postgres2.engine_version
}
