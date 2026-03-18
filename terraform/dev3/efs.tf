resource "aws_efs_file_system" "workbc3" {
  creation_token                  = "workbc-efs3"
  encrypted                       = true

  tags = merge(
    {
      Name = "workbc-efs3"
    },
    var.common_tags
  )
}

resource "aws_efs_mount_target" "data_azA" {
  file_system_id  = aws_efs_file_system.workbc3.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[0]
  security_groups = [data.aws_security_group.app.id]
}

resource "aws_efs_mount_target" "data_azB" {
  file_system_id  = aws_efs_file_system.workbc3.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[1]
  security_groups = [data.aws_security_group.app.id]
}
  
resource "aws_efs_backup_policy" "workbc-efs-backups-policy" {
  file_system_id = aws_efs_file_system.workbc3.id

  backup_policy {
    status = "ENABLED"
  }
}
