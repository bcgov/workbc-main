# Redis

data "aws_security_group" "redis_security_group" {
	name	=	"redis_sg"
}

resource "aws_elasticache_replication_group" "workbc2_redis_rg" {
	automatic_failover_enabled	=	true
	preferred_cache_cluster_azs	=	["ca-central-1a", "ca-central-1b"]
	replication_group_id		=	"workbc2-rep-group"
	description			=	"Redis replication group for Drupal DEV2"
	node_type			=	"cache.t2.micro"
	num_cache_clusters		=	2
	engine_version			=	"6.x"
	parameter_group_name		=	"default.redis6.x"
	port				=	6379
	
	lifecycle {
		ignore_changes	=	[num_cache_clusters]
	}
	
	subnet_group_name		=	aws_elasticache_subnet_group.default.name
	security_group_ids		=	[data.aws_security_group.redis_security_group.id]
}

resource "aws_elasticache_cluster" "replica2" {
	count 		= 	1
	cluster_id	=	"workbc2-rep-group-${count.index}"
	replication_group_id	=	aws_elasticache_replication_group.workbc2_redis_rg.id
}

resource "aws_elasticache_subnet_group" "default" {
	name		=	"redis-subnet-group-drupal-dev2"
	subnet_ids	=	module.network.aws_subnet_ids.app.ids
}


