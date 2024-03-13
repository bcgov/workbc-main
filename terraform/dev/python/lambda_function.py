import botocore
import boto3
import os


def lambda_handler(event, lambda_context):
    errorcount = 0
    healthcheck_id = os.environ['HealthCheckId']
    client = boto3.client('route53')
    
    try:
        response = client.get_health_check_status(
            HealthCheckId=healthcheck_id,
        )  
    except botocore.exceptions.ClientError as err:
        raise SystemExit(err)

    status = response["HealthCheckObservations"]

    for region in range(0,len(status)):
        healthcheck_status = status[region]["StatusReport"]["Status"]
        if "Success" not in healthcheck_status:
            errorcount = errorcount + 1

    if errorcount > 2 :
        raise SystemExit("The Route53 Health Check failed for at least 2 regions")
    else :
        SystemExit(0)