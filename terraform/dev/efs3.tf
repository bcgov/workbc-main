resource "aws_efs_file_system" "pgadmin" {
  creation_token                  = "pgadmin-efs"
  encrypted                       = true

  tags = merge(
    {
      Name = "pgadmin-efs"
    },
    var.common_tags
  )
}

resource "aws_efs_access_point" "pgadmin" {
  file_system_id  = aws_efs_file_system.pgadmin.id
  
  root_directory {
      creation_info {
          owner_uid = "5050"
          owner_gid = "5050"
          permissions = "0777"
      }
    
      path  = "/data"
  }
  
  tags = merge(
    {
        Name        = "pgadmin-data"
    },
    var.common_tags
  )
  
}

resource "aws_efs_mount_target" "data_azA2" {
  file_system_id  = aws_efs_file_system.pgadmin.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[0]
  security_groups = [data.aws_security_group.app.id]
}

resource "aws_efs_mount_target" "data_azB2" {
  file_system_id  = aws_efs_file_system.pgadmin.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[1]
  security_groups = [data.aws_security_group.app.id]
}
