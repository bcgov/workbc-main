resource "aws_efs_file_system" "workbc" {
  creation_token                  = "workbc-efs"
  encrypted                       = true

  tags = merge(
    {
      Name = "workbc-efs"
    },
    var.common_tags
  )
}

resource "aws_efs_mount_target" "data_azA" {
  file_system_id  = aws_efs_file_system.workbc.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[0]
  security_groups = [data.aws_security_group.app.id]
}

resource "aws_efs_mount_target" "data_azB" {
  file_system_id  = aws_efs_file_system.workbc.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[1]
  security_groups = [data.aws_security_group.app.id]
}
