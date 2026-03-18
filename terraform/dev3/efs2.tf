resource "aws_efs_file_system" "solr3" {
  creation_token                  = "solr-efs3"
  encrypted                       = true

  tags = merge(
    {
      Name = "solr-efs3"
    },
    var.common_tags
  )
}

resource "aws_efs_access_point" "solr3" {
  file_system_id  = aws_efs_file_system.solr3.id
  
  root_directory {
      creation_info {
          owner_uid = "0"
          owner_gid = "0"
          permissions = "0777"
      }
    
      path  = "/data"
  }
  
  tags = merge(
    {
        Name        = "ap-data"
    },
    var.common_tags
  )
  
}

resource "aws_efs_mount_target" "data_azA3" {
  file_system_id  = aws_efs_file_system.solr3.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[0]
  security_groups = [data.aws_security_group.app.id]
}

resource "aws_efs_mount_target" "data_azB3" {
  file_system_id  = aws_efs_file_system.solr3.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[1]
  security_groups = [data.aws_security_group.app.id]
}
