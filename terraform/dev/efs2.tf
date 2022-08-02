resource "aws_efs_file_system" "solr" {
  creation_token                  = "solr-efs"
  encrypted                       = true

  tags = merge(
    {
      Name = "solr-efs"
    },
    var.common_tags
  )
}

resource "aws_efs_access_point" "solr" {
  file_system_id  = aws_efs_file_system.solr.id
  
  root_directory {
      creation_info {
          owner_uid = "0"
          owner_gid = "0"
          permissions = "0777"
      }
    
      path  = "/data2"
  }
  
  tags = merge(
    {
        Name        = "ap-data"
    },
    var.common_tags
  )
  
}

resource "aws_efs_mount_target" "data_azA2" {
  file_system_id  = aws_efs_file_system.solr.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[0]
  security_groups = [data.aws_security_group.app.id]
}

resource "aws_efs_mount_target" "data_azB2" {
  file_system_id  = aws_efs_file_system.solr.id
  subnet_id       = sort(module.network.aws_subnet_ids.data.ids)[1]
  security_groups = [data.aws_security_group.app.id]
}
