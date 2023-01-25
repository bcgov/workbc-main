# rds

data "aws_rds_cluster" "postgres" {
  cluster_identifier      = "ceu-postgres-cluster"
}

data "aws_secretsmanager_secret_version" "creds" {
  secret_id = "workbc-cc-db-creds"
}

#data "aws_secretsmanager_secret_version" "creds2" {
#  secret_id = "workbc-jb-db-creds"
#}
