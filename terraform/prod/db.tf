# rds

data "aws_rds_cluster" "postgres" {
  cluster_identifier      = "ceu-postgres-cluster"
}

data "aws_secretsmanager_secret_version" "creds" {
  secret_id = "workbc-cc-db-creds"
}

#locals {
#  db_creds = jsondecode(
#    data.aws_secretsmanager_secret_version.creds.secret_string
#  )
#  conn_str = "postgres://${local.db_creds.adm_username}:${local.db_creds.adm_password}@${data.aws_rds_cluster.postgres.endpoint}:5432/ssot"
#}
