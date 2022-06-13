<?php

namespace Drupal\ses_mailer\Plugin\Mail;

use Aws\Exception\AwsException;
use Aws\Ses\SesClient;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AWS SES mailer implementation.
 *
 * @Mail(
 *   id = "ses_mail",
 *   label = @Translation("AWS SES mailer"),
 *   description = @Translation("Sends the message as plain text, using AWS SES.")
 * )
 */
class SesMailer extends PluginBase implements MailInterface, ContainerFactoryPluginInterface {

  /**
   * The SES Client.
   *
   * @var \Aws\Ses\SesClient
   */
  protected $sesClient;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * SesMailer constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Aws\Ses\SesClient $ses_client
   *   The SES Client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SesClient $ses_client, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sesClient = $ses_client;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ses_mailer.ses_client'),
      $container->get('logger.channel.ses_mailer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function format(array $message) {
    // Join the body array into one string.
    $message['body'] = implode("\n\n", $message['body']);
    // Convert any HTML to plain-text.
    $message['body'] = MailFormatHelper::htmlToText($message['body']);
    // Wrap the mail body for sending.
    $message['body'] = MailFormatHelper::wrapMail($message['body']);
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message) {
    $result = [];
    $result['error'] = FALSE;
    try {
      // Credentials are set in environment variables.
      $response = $this->sesClient->sendEmail([
        'Destination' => [
          'ToAddresses' => [$message['to']],
        ],
        'Message' => [
          'Body' => [
            'Text' => [
              'Data' => $message['body'],
            ],
          ],
          'Subject' => [
            'Data' => $message['subject'],
          ],
        ],
        'ReplyToAddresses' => [$message['from']],
        'ReturnPath' => $message['reply-to'],
        'Source' => $message['from'],
      ]);
      $this->logger->info('Successfully sent email from %from to %to with message ID %id', [
        '%from' => $message['from'],
        '%to' => $message['to'],
        '%id' => $response->get('MessageId'),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('%type: @message in %function (line %line of %file)', Error::decodeException($e));
      if ($e instanceof AwsException) {
        $result['message'] = $e->getAwsErrorType();
        $result['errorCode'] = $e->getAwsErrorCode();
      }
      else {
        $result['message'] = $e->getMessage();
        $result['errorCode'] = $e->getCode();
      }
      $result['error'] = TRUE;
    }
    return $result;
  }

}
